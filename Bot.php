<?php

namespace App;

use App\Api\TelegramApi;
use App\Api\Update;
use App\Services\StorageService;
use App\Services\KeyboardBuilder;
use App\Handlers\{
    SettingsHandler,
    CalculatorHandler,
    AutoAnswerHandler,
    MessageHistoryHandler,
    StatisticsHandler,
    CurrencyHandler,
    TimedMediaHandler
};

class Bot
{
    private TelegramApi $api;
    private Update $update;
    private StorageService $storage;
    private int $adminId;
    private string $adminUser;
    private string $botName;
    private array $handlers = [];

    public function __construct(array $config)
    {
        date_default_timezone_set($config['timezone'] ?? 'Asia/Tashkent');

        $this->api = new TelegramApi($config['token']);
        $this->update = Update::fromInput();
        $this->storage = new StorageService($config['db'] ?? [], $config['encryption_key'] ?? '');
        $this->adminId = $config['admin_id'];
        $this->adminUser = $config['admin_user'];

        $this->storage->initSettings();

        $this->botName = $config['bot_name'] ?? 'Bot';

        $this->initHandlers();
        $this->initCommands();
    }

    private function initHandlers(): void
    {
        $args = [$this->api, $this->update, $this->storage, $this->adminId, $this->botName];

        $this->handlers = [
            'settings' => new SettingsHandler(...$args),
            'calculator' => new CalculatorHandler(...$args),
            'autoAnswer' => new AutoAnswerHandler(...$args),
            'history' => new MessageHistoryHandler(...$args),
            'statistics' => new StatisticsHandler(...$args),
            'currency' => new CurrencyHandler(...$args),
            'timedMedia' => new TimedMediaHandler(...$args),
        ];
    }

    private function initCommands(): void
    {
        if ($this->storage->getSetting('commands_set') !== '1') {
            $this->api->call('setMyCommands', [
                'commands' => [
                    ['command' => 'start', 'description' => 'Botni ishga tushirish'],
                    ['command' => 'settings', 'description' => 'Bot sozlamalari'],
                ]
            ]);
            $this->storage->setSetting('commands_set', '1');
        }
    }

    public function run(): void
    {


        $this->handleBusinessConnection();
        $this->handleEditedBusinessMessage();
        $this->handleDeletedBusinessMessages();
        $this->handleBusinessMessage();
        $this->handleCallbackQuery();
        $this->handleMessage();
    }

    private function handleBusinessConnection(): void
    {
        if (!$this->update->hasBusinessConnection()) return;

        $enabled = $this->update->isBusinessConnectionEnabled();
        $userId = $this->update->getBusinessConnectionUserId();
        $text = $enabled
            ? "<tg-emoji emoji-id='5206607081334906820'>✔️</tg-emoji> <b>Hisobingizga {$this->botName} muvaffaqiyatli ulandi!</b>"
            : "<tg-emoji emoji-id='5260293700088511294'>⛔️</tg-emoji> <b>Hisobingizdan {$this->botName} olib tashlandi!</b>";

        $this->api->sendMessage($userId, $text, ['parse_mode' => 'html']);
    }

    private function handleEditedBusinessMessage(): void
    {
        if (!$this->update->hasEditedBusinessMessage()) return;
        $this->handlers['history']->handleEditedMessage();

        $text = $this->update->getEditedText() ?? $this->update->getEditedCaption() ?? '';
        $chatId = $this->update->getEditedChatId();
        $fromId = $this->update->getEditedFromId();
        $step = $this->storage->getStep($chatId);

        if ($fromId === $this->adminId) {
            $this->dispatchAdminCommand($text, $step, true);
        }
    }

    private function handleDeletedBusinessMessages(): void
    {
        if (!$this->update->hasDeletedBusinessMessages()) return;
        $this->handlers['history']->handleDeletedMessages();
    }

    private function handleBusinessMessage(): void
    {
        if (!$this->update->hasBusinessMessage()) return;

        $this->handlers['history']->saveIncomingMessage();

        $text = $this->update->getBusinessText() ?? $this->update->getBusinessCaption() ?? '';
        $chatId = $this->update->getBusinessChatId();
        $fromId = $this->update->getBusinessFromId();
        $step = $this->storage->getStep($chatId);

        if ($fromId === $this->adminId) {
            $this->dispatchAdminCommand($text, $step, true);
            return;
        }

        if (!empty($text)) {
            $this->handlers['autoAnswer']->handleIncomingMessage();
        }
    }

    private function dispatchAdminCommand(string $text, string $step, bool $isBusiness = false): void
    {
        if (!empty($step)) {
            if ($isBusiness) return;

            match (true) {
                $step === 'add'                        => $this->handlers['autoAnswer']->handleAddWordStep(),
                str_starts_with($step, 'sozlar&')     => $this->handlers['autoAnswer']->handleSaveAnswerStep($step),
                str_starts_with($step, 'editkey&')    => $this->handlers['autoAnswer']->handleRenameKeywordStep($step),
                $step === 'vaqtli-command'             => $this->handlers['timedMedia']->handleSaveCommandStep(),
                default                                => null,
            };
            return;
        }

        match (true) {
            in_array($text, ['/settings', '/start']) && !$isBusiness   => $this->handlers['settings']->handleSettingsCommand(),
            $text === '.ping'       => $this->handlePingCommand(),
            $text === '.memory'     => $this->handleMemoryCommand(),
            $text === '.currency'   => $this->handlers['currency']->handleCurrencyCommand(),
            $this->storage->getSetting('timed-command') !== '' && trim($text) === trim($this->storage->getSetting('timed-command'))
                                    => $this->handlers['timedMedia']->handleSaveTimedMedia(),
            mb_stripos($text, '.math ') !== false       => $this->handlers['calculator']->handleMathCommand($text),
            default                 => null,
        };
    }

    private function handleCallbackQuery(): void
    {
        if (!$this->update->hasCallbackQuery()) return;

        $data = $this->update->getCallbackData() ?? '';

        match (true) {

            $data === 'settings' => $this->handlers['settings']->handleSettingsCallback(),
            $data === 'yopish' => $this->handlers['settings']->handleCloseCallback(),

            str_starts_with($data, 'kalkulyator') => $this->handlers['calculator']->handleSettingsCallback($data),

            str_starts_with($data, 'statistika') => $this->handlers['statistics']->handleStatisticsCallback($data),

            str_starts_with($data, 'avto') => $this->handlers['autoAnswer']->handleSettingsCallback($data),
            $data === 'asozlash' => $this->handlers['autoAnswer']->handleManageCallback(),
            $data === 'list' || str_starts_with($data, 'list&page=') => $this->handlers['autoAnswer']->handleListCallback($data),
            $data === 'formatsiz' || str_starts_with($data, 'formatsiz&page=') => $this->handlers['autoAnswer']->handleListRawCallback($data),
            $data === 'add' => $this->handlers['autoAnswer']->handleAddCallback(),
            str_starts_with($data, 'amatch_') => $this->handlers['autoAnswer']->handleMatchTypeCallback($data),
            $data === 'delete' || str_starts_with($data, 'delete&page=') => $this->handlers['autoAnswer']->handleDeleteCallback($data),
            mb_stripos($data, 'atanla=') !== false => $this->handlers['autoAnswer']->handleDeleteWordCallback($data),
            $data === 'rename' || str_starts_with($data, 'rename&page=') => $this->handlers['autoAnswer']->handleRenameCallback($data),
            mb_stripos($data, 'rtanla=') !== false => $this->handlers['autoAnswer']->handleWordDetailCallback($data),
            mb_stripos($data, 'edit_key=') !== false => $this->handlers['autoAnswer']->handleEditKeyCallback($data),
            mb_stripos($data, 'edit_ans=') !== false => $this->handlers['autoAnswer']->handleEditAnswerCallback($data),
            mb_stripos($data, 'toggle_match=') !== false => $this->handlers['autoAnswer']->handleToggleMatchCallback($data),
            str_starts_with($data, 'valyuta') => $this->handlers['currency']->handleSettingsCallback($data),

            str_starts_with($data, 'vaqtli-media') => $this->handlers['timedMedia']->handleSettingsCallback($data),
            $data === 'vaqtli-sozlash' => $this->handlers['timedMedia']->handleManageCallback(),
            $data === 'vaqtli-cmd-set' => $this->handlers['timedMedia']->handleSetCommandCallback(),

            str_starts_with($data, 'tahrirlangan') => $this->handlers['history']->handleEditedSettingsCallback($data),
            str_starts_with($data, 'ochirilgan') => $this->handlers['history']->handleDeletedSettingsCallback($data),

            $data === 'none' => null,
            default => null,
        };
    }

    private function handleMessage(): void
    {
        if (!$this->update->hasMessage()) return;

        $text = $this->update->getText() ?? '';
        $chatId = $this->update->getChatId();
        $fromId = $this->update->getFromId();

        if ($text === '/start') {
            if ($fromId === $this->adminId) {
                if (!$this->api->isBusinessUpdatesAllowed()) {
                    $this->api->sendMessage($chatId,
                        "<tg-emoji emoji-id='5447644880824181073'>⚠️</tg-emoji> " .
                        "<i><b>Diqqat!</b> Botingiz webhook sozlamalarida business xabarlar ruxsat etilmagan. " .
                        "Iltimos, webhook'ni yangilang, aks holda bot ishlamaydi.</i>",
                        ['parse_mode' => 'html']
                    );
                }
            } else {
                $firstName = htmlspecialchars($this->update->getFirstName() ?? 'Foydalanuvchi');
                $lastName = htmlspecialchars($this->update->getLastName() ?? '');
                $fullName = trim("{$firstName} {$lastName}");
                $nameLink = "<a href='tg://user?id={$fromId}'>{$fullName}</a>";

                $adminInfo = $this->api->getChat($this->adminId);
                $adminFirst = ($adminInfo && isset($adminInfo->result)) ? ($adminInfo->result->first_name ?? 'Admin') : 'Admin';
                $adminLast = ($adminInfo && isset($adminInfo->result)) ? ($adminInfo->result->last_name ?? '') : '';
                $adminFullName = trim($adminFirst . " " . $adminLast) ?: 'Admin';

                $welcomeText = "<tg-emoji emoji-id='5472055112702629499'>👋</tg-emoji> <b>Assalomu alaykum, {$nameLink}!</b>\n\n" .
                    "<i>Ushbu bot — shaxsiy <b>{$this->botName}</b> hisobini boshqarish va xizmat ko'rsatish uchun mo'ljallangan.</i>\n\n" .
                    "<i>Savol, taklif yoki murojaatlaringiz bo'lsa, quyidagi tugma orqali bog'lanishingiz mumkin:</i>";

                $this->api->sendMessage($chatId, $welcomeText, [
                    'parse_mode' => 'html',
                    'reply_markup' => json_encode(['inline_keyboard' => [
                        [[
                            'text' => $adminFullName,
                            'url' => "https://t.me/{$this->adminUser}",
                        ]]
                    ]]),
                ]);
            }
        }

        if ($fromId === $this->adminId) {
            $step = $this->storage->getStep($chatId);
            $this->dispatchAdminCommand($text, $step);
        }
    }

    private function handlePingCommand(): void
    {
        $start = microtime(true);
        $bid = $this->update->getBusinessConnectionId() ?? $this->update->getEditedBusinessId();
        $cid = $bid ? ($this->update->getBusinessChatId() ?? $this->update->getEditedChatId()) : $this->update->getChatId();
        $mid = $bid ? ($this->update->getBusinessMessageId() ?? $this->update->getEditedMessageId()) : $this->update->getMessageId();

        $actionParams = ['chat_id' => $cid, 'action' => 'typing'];
        if ($bid) $actionParams['business_connection_id'] = $bid;
        
        $this->api->call('sendChatAction', $actionParams);
        
        $ping = number_format((microtime(true) - $start) * 1000, 2);
        $text = "<tg-emoji emoji-id='5256263173928926820'>🚀</tg-emoji> <b>O'rtacha yuklanish:</b> {$ping} ms";

        if ($bid) {
            $this->api->editBusinessMessage($bid, $cid, $mid, $text, ['parse_mode' => 'html']);
        } else {
            $this->api->sendMessage($cid, $text, ['parse_mode' => 'html']);
        }
    }

    private function handleMemoryCommand(): void
    {
        $memory = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
        $text = "<tg-emoji emoji-id='5257969839313526622'>📂</tg-emoji> <b>Xotira iste'moli:</b> {$memory} MB\n\n" .
                "<i>Bot hozirda {$memory} megabayt xotiradagi joydan foydalanmoqda!</i>";
        
        $bid = $this->update->getBusinessConnectionId() ?? $this->update->getEditedBusinessId();
        $cid = $bid ? ($this->update->getBusinessChatId() ?? $this->update->getEditedChatId()) : $this->update->getChatId();
        $mid = $bid ? ($this->update->getBusinessMessageId() ?? $this->update->getEditedMessageId()) : $this->update->getMessageId();

        $actionParams = ['chat_id' => $cid, 'action' => 'typing'];
        if ($bid) $actionParams['business_connection_id'] = $bid;
        
        $this->api->call('sendChatAction', $actionParams);

        if ($bid) {
            $this->api->editBusinessMessage($bid, $cid, $mid, $text, ['parse_mode' => 'html']);
        } else {
            $this->api->sendMessage($cid, $text, ['parse_mode' => 'html']);
        }
    }
}