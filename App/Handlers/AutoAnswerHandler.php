<?php

namespace App\Handlers;

use App\Services\KeyboardBuilder;

class AutoAnswerHandler extends BaseHandler
{
    private int $itemsPerPage = 5;

    public function handleSettingsCallback(string $data): void
    {
        $this->handleToggle(
            'auto-answer',
            $data,
            'avto',
            fn(string $newValue) => $this->renderSettings($newValue)
        );
    }

    private function renderSettings(string $value): void
    {
        $res = $value === 'on' ? 'Yoqilgan!' : 'O\'chirilgan!';
        $keyboard = KeyboardBuilder::make();

        if ($value === 'on') {
            $keyboard->rowSingle("Sozlamalar", "asozlash", "5341715473882955310");
        }

        $keyboard->toggleButtons($value, 'avto')->backButton('settings');

        $this->editCallback(
            "<tg-emoji emoji-id='5341715473882955310'>⚙️</tg-emoji> <b>Avto javob sozlamalaridasiz!</b>\n\n" .
            "<i>— Hozirgi holat: $res</i>\n\n" .
            "<tg-emoji emoji-id='5406745015365943482'>⬇️</tg-emoji> <b>Chatlarda foydalanish:</b>\n\n" .
            "<i>Barcha chatlarda avtomatik tarzda, maxsus yozish (typing) kechikishi bilan amalga oshiriladi! Foydalanuvchilar bot o'rniga haqiqiy odam javob beryapti deb o'ylaydi!</i>",
            ['parse_mode' => 'html', 'reply_markup' => $keyboard->toJson()]
        );
    }

    public function handleManageCallback(): void
    {
        if (!$this->isAdminCallback()) {
            $this->denyCallback();
            return;
        }

        $keyboard = KeyboardBuilder::make()
            ->rowSingle("Ro'yxat", "list", "5222444124698853913")
            ->row(
                KeyboardBuilder::btn("Qo'shish", "add", "5397916757333654639"),
                KeyboardBuilder::btn("O'chirish", "delete", "5445267414562389170")
            )
            ->rowSingle("Tahrirlash", "rename", "5395444784611480792")
            ->backButton('avto')
            ->toJson();

        $this->editCallback(
            "<tg-emoji emoji-id='5406745015365943482'>⬇️</tg-emoji> <b>Quyidagilardan birini tanlang!</b>",
            ['parse_mode' => 'html', 'reply_markup' => $keyboard]
        );

        $this->storage->clearStep($this->update->getCallbackMessageChatId());
    }

    public function handleListCallback(string $data): void
    {
        if (!$this->isAdminCallback()) {
            $this->denyCallback();
            return;
        }

        $page = $this->parsePage($data, 'list');
        $this->renderList($data, $page, 'list', 'formatsiz', 'Formatli', 'Formatsiz');
    }

    public function handleListRawCallback(string $data): void
    {
        if (!$this->isAdminCallback()) {
            $this->denyCallback();
            return;
        }

        $page = $this->parsePage($data, 'formatsiz');
        $this->renderList($data, $page, 'formatsiz', 'list', 'Formatsiz', 'Formatli', true);
    }

    private function renderList(string $data, int $page, string $prefix, string $altPrefix, string $currentLabel, string $altLabel, bool $escape = false): void
    {
        $words = $this->getMigratedWords();

        if (empty($words)) {
            $this->showNotFound('asozlash');
            return;
        }

        $total = count($words);
        $totalPages = max(1, (int)ceil($total / $this->itemsPerPage));
        $page = max(1, min($totalPages, $page));

        if (!$this->checkPageBounds($page, $totalPages, $data, $prefix)) return;


        $typeMap = [
            'text' => 'Matn', 'photo' => 'Rasm', 'video' => 'Video', 'voice' => 'Ovozli x.',
            'audio' => 'Audio', 'document' => 'Hujjat', 'video_note' => 'Yumaloq v.',
            'sticker' => 'Stiker', 'animation' => 'GIF'
        ];

        $offset = ($page - 1) * $this->itemsPerPage;
        $pagedWords = array_slice($words, $offset, $this->itemsPerPage, true);

        $text = "<table bordered striped>";
        $text .= "<tr><th>Kalit so'z</th><th>Holat</th><th>Turi</th><th>Javob</th></tr>";
        
        foreach ($pagedWords as $word => $dataArr) {
            $rawType = $dataArr['type'] ?? 'text';
            $type = $typeMap[$rawType] ?? 'Media';
            $match = $dataArr['match_type'] === 'exact' ? 'Aniq' : 'Ixtiyoriy';
            
            if ($escape) {
                $content = htmlspecialchars($dataArr['content'] ?? '');
            } else {
                $content = strip_tags($dataArr['content'] ?? '', '<b><i><u><s><strike><del><code><pre><a><span><tg-emoji>');
            }

            if (empty(trim($content)) && $rawType !== 'text') {
                if (!empty($dataArr['file_name'])) {
                    $content = htmlspecialchars($dataArr['file_name']);
                } else {
                    $content = "[$type]";
                }
            }
            
            $text .= "<tr><td>{$word}</td><td align='center'>{$match}</td><td align='center'>{$type}</td><td>{$content}</td></tr>";
        }
        $text .= "</table>";

        $keyboard = KeyboardBuilder::make()
            ->pagination($page, $totalPages, $prefix)
            ->rowSingle("{$altLabel} ko'rish", $altPrefix, "5222444124698853913")
            ->backButton('asozlash')
            ->toJson();

        $htmlText = $text;
        
        $cid = $this->update->getCallbackMessageChatId();
        $mid = $this->update->getCallbackMessageId();
        
        $this->api->deleteMessage($cid, $mid);
        $this->api->sendRichMessage($cid, ['html' => $htmlText], ['reply_markup' => $keyboard]);
    }

    public function handleAddCallback(): void
    {
        if (!$this->isAdminCallback()) {
            $this->denyCallback();
            return;
        }

        $cid = $this->update->getCallbackMessageChatId();
        $this->storage->setStep($cid, 'add');

        $this->editCallback(
            "<tg-emoji emoji-id='5443038326535759644'>💬</tg-emoji> <b>Kerakli kalit so'zni yuboring:</b>",
            ['parse_mode' => 'html', 'reply_markup' => KeyboardBuilder::make()->backButton('asozlash')->toJson()]
        );
    }

    public function handleAddWordStep(): void
    {
        $mediaItems = $this->update->getMediaItems();
        if (!empty($mediaItems)) {
            $this->editBusiness(
                "<tg-emoji emoji-id='5447644880824181073'>⚠️</tg-emoji> <b>Kalit so'z faqat oddiy matn bo'lishi kerak!</b>\n\n" .
                "<i>Iltimos, faqat matn yuboring (rasm, video va boshqa fayllar qabul qilinmaydi).</i>",
                ['parse_mode' => 'html', 'reply_markup' => KeyboardBuilder::make()->backButton('asozlash')->toJson()]
            );
            return;
        }

        $wordText = trim($this->update->getText());

        if (empty($wordText)) {
            $this->editBusiness(
                "<tg-emoji emoji-id='5447644880824181073'>⚠️</tg-emoji> <b>Kalit so'z bo'sh bo'lishi mumkin emas!</b>",
                ['parse_mode' => 'html', 'reply_markup' => KeyboardBuilder::make()->backButton('asozlash')->toJson()]
            );
            return;
        }

        if (!$this->isPlainText($wordText)) {
            $this->editBusiness(
                "<tg-emoji emoji-id='5447644880824181073'>⚠️</tg-emoji> <b>Kalit so'zda HTML teglar yoki maxsus belgilar bo'lishi mumkin emas!</b>\n\n" .
                "<i>Faqat oddiy matn kiriting.</i>",
                ['parse_mode' => 'html', 'reply_markup' => KeyboardBuilder::make()->backButton('asozlash')->toJson()]
            );
            return;
        }

        $chatId = $this->update->getChatId();
        $words = $this->getMigratedWords();
        $normalized = $this->normalizeText($wordText);

        foreach ($words as $key => $_) {
            if ($this->normalizeText($key) === $normalized) {
                $this->api->sendMessage($chatId,
                    "<tg-emoji emoji-id='5447644880824181073'>⚠️</tg-emoji> $wordText <b>qabul qilinmadi!</b>\n\n" .
                    "<i>Ushbu so'zdan avval foydalanilgan! Boshqa so'z kiriting:</i>",
                    ['parse_mode' => 'html', 'reply_markup' => KeyboardBuilder::make()->backButton('asozlash')->toJson()]
                );
                return;
            }
        }

        $this->storage->setStep($chatId, "amatch_wait&{$wordText}");

        $keyboard = KeyboardBuilder::make()
            ->row(
                KeyboardBuilder::btn("Aniq moslik", "amatch_exact", "5206607081334906820"),
                KeyboardBuilder::btn("Ixtiyoriy moslik", "amatch_contains", "5231012545799666522")
            )
            ->backButton('asozlash')
            ->toJson();

        $this->api->sendMessage($chatId,
            "<tg-emoji emoji-id='5206607081334906820'>✔️</tg-emoji> $wordText <b>qabul qilindi!</b>\n\n" .
            "<tg-emoji emoji-id='5406745015365943482'>⬇️</tg-emoji> <b>Endi esa qidiruv turini tanlang:</b>\n\n" .
            "<b>Aniq moslik:</b> Faqatgina aniq shu so'z yozilgandagina javob beradi.\n" .
            "<b>Ixtiyoriy moslik:</b> Gap orasida kelsa ham javob beraveradi.",
            ['parse_mode' => 'html', 'reply_markup' => $keyboard]
        );
    }

    public function handleMatchTypeCallback(string $data): void
    {
        if (!$this->isAdminCallback()) {
            $this->denyCallback();
            return;
        }

        $chatId = $this->update->getCallbackMessageChatId();
        $step = $this->storage->getStep($chatId);
        
        if (!str_starts_with($step, 'amatch_wait&')) {
            $this->showNotFound('asozlash');
            return;
        }

        $wordText = explode('&', $step, 2)[1];
        $matchType = str_replace('amatch_', '', $data);

        $this->storage->setStep($chatId, "sozlar&{$matchType}&{$wordText}");

        $this->editCallback(
            "<tg-emoji emoji-id='5206607081334906820'>✔️</tg-emoji> <b>Qidiruv turi belgilandi!</b> (" . ($matchType === 'exact' ? "Aniq" : "Ixtiyoriy") . ")\n\n" .
            $this->placeholderHelp() .
            "\n\n<tg-emoji emoji-id='5395444784611480792'>✏️</tg-emoji> <b>Endi esa, ushbu so'z uchun kerakli javobni yuboring:</b>\n\n" .
            "<i>Siz faqat matn emas, balki barcha turdagi fayllarni yuborishingiz mumkin: Rasm, Video, Ovozli xabar, Hujjat, Stiker, GIF (Animatsiya) yoki Yumaloq video (Video note)! Matnli medialarga sarlavha qoldirishni ham unutmang.</i>",
            ['parse_mode' => 'html', 'disable_web_page_preview' => true, 'reply_markup' => KeyboardBuilder::make()->backButton('asozlash')->toJson()]
        );
    }

    public function handleSaveAnswerStep(string $step): void
    {
        $chatId = $this->update->getChatId();
        $parts = explode('&', $step, 3);
        $matchType = $parts[1];
        $wordKey = $parts[2];
        
        $text = $this->update->getText();
        $mediaItems = $this->update->getMediaItems();
        
        $answerData = [
            'type' => 'text',
            'content' => $text ?? '',
            'file_id' => null,
            'match_type' => $matchType
        ];

        if (!empty($mediaItems)) {
            $media = $mediaItems[0];
            $answerData['type'] = $media['type'];
            $answerData['file_id'] = $media['file_id'];
            $answerData['content'] = $media['caption'] ?? '';
            if (isset($media['file_name'])) {
                $answerData['file_name'] = $media['file_name'];
            }
        }

        if ($answerData['type'] === 'text' || !empty($answerData['content'])) {
            if (!$this->isValidHtml($answerData['content'])) {
                $this->api->sendMessage($chatId,
                    "<tg-emoji emoji-id='5447644880824181073'>⚠️</tg-emoji> <b>Qabul qilinmadi!</b>\n\n<i>Matnda yoxud sarlavhada sintaktik HTML xatolar bor! Iltimos, barcha teglarni to'g'ri yopilganiga ishonch hosil qilib, qaytadan yuboring.</i>",
                    ['parse_mode' => 'html', 'reply_markup' => KeyboardBuilder::make()->backButton('asozlash')->toJson()]
                );
                return;
            }
        }

        $words = $this->getMigratedWords();
        $words[$wordKey] = $answerData;
        $this->storage->saveWords($words);

        $this->storage->clearStep($chatId);

        $typeMap = [
            'text' => 'Matn', 'photo' => 'Rasm', 'video' => 'Video', 'voice' => 'Ovozli x.',
            'audio' => 'Audio', 'document' => 'Hujjat', 'video_note' => 'Yumaloq v.',
            'sticker' => 'Stiker', 'animation' => 'GIF'
        ];
        $typeName = $typeMap[$answerData['type']] ?? 'Media';
        $matchLabel = $matchType === 'exact' ? 'Aniq' : 'Ixtiyoriy';

        $contentPreview = '';
        if (!empty($answerData['content'])) {
            $contentPreview = "\n<b>↳</b> " . htmlspecialchars(mb_substr(strip_tags($answerData['content']), 0, 80));
        } elseif (!empty($answerData['file_name'])) {
            $contentPreview = "\n<b>↳</b> " . htmlspecialchars($answerData['file_name']);
        }

        $words = $this->getMigratedWords();
        $words[$wordKey] = $answerData;
        $this->storage->saveWords($words);
        $this->storage->clearStep($chatId);

        $this->api->sendMessage($chatId,
            "<tg-emoji emoji-id='5206607081334906820'>✔️</tg-emoji> <b>Saqlandi!</b>\n\n" .
            "<b>Kalit so'z:</b> <code>{$wordKey}</code>\n" .
            "<b>Tur:</b> {$typeName} | <b>Moslik:</b> {$matchLabel}" .
            $contentPreview,
            ['parse_mode' => 'html', 'reply_markup' => KeyboardBuilder::make()->backButton('asozlash')->toJson()]
        );
    }
    
    public function handleDeleteCallback(string $data): void
    {
        if (!$this->isAdminCallback()) {
            $this->denyCallback();
            return;
        }

        $page = $this->parsePage($data, 'delete');
        $words = $this->getMigratedWords();

        if (empty($words)) {
            $this->showNotFound('asozlash');
            return;
        }

        $keys = array_keys($words);
        $total = count($keys);
        $totalPages = max(1, (int)ceil($total / $this->itemsPerPage));
        $page = max(1, min($totalPages, $page));

        if (!$this->checkPageBounds($page, $totalPages, $data, 'delete')) return;

        $offset = ($page - 1) * $this->itemsPerPage;
        $buttons = [];

        $typeMap = [
            'text' => 'Matn', 'photo' => 'Rasm', 'video' => 'Video', 'voice' => 'Ovozli x.', 
            'audio' => 'Audio', 'document' => 'Hujjat', 'video_note' => 'Yumaloq v.', 
            'sticker' => 'Stiker', 'animation' => 'GIF'
        ];

        for ($i = $offset; $i < min($offset + $this->itemsPerPage, $total); $i++) {
            $key = $keys[$i];
            $answer = mb_substr(strip_tags($words[$key]['content']), 0, 20);
            if (empty($answer) && $words[$key]['type'] !== 'text') {
                $answer = !empty($words[$key]['file_name']) ? mb_substr($words[$key]['file_name'], 0, 20) : "Media";
            }
            $type = $typeMap[$words[$key]['type'] ?? 'text'] ?? 'Media';
            $buttons[] = [KeyboardBuilder::btn("{$type}: $key - $answer", "atanla=$key")];
        }

        $keyboard = array_merge($buttons, [
            [
                KeyboardBuilder::btn('⬅️', "delete&page=" . ($page - 1)),
                KeyboardBuilder::btn("$page/$totalPages", 'none'),
                KeyboardBuilder::btn('➡️', "delete&page=" . ($page + 1)),
            ],
            [KeyboardBuilder::btn("◀️ Orqaga", 'asozlash')],
        ]);

        $this->editCallback(
            "<tg-emoji emoji-id='5406745015365943482'>⬇️</tg-emoji> <b>O'chirish uchun tanlang!</b>",
            ['parse_mode' => 'html', 'reply_markup' => json_encode(['inline_keyboard' => $keyboard])]
        );
    }

    public function handleDeleteWordCallback(string $data): void
    {
        if (!$this->isAdminCallback()) {
            $this->denyCallback();
            return;
        }

        $word = explode('=', $data, 2)[1];
        $words = $this->getMigratedWords();
        unset($words[$word]);
        $this->storage->saveWords($words);

        $this->editCallback(
            "<tg-emoji emoji-id='5206607081334906820'>✔️</tg-emoji> <code>$word</code> <b>o'chirib tashlandi!</b>",
            ['parse_mode' => 'html', 'reply_markup' => KeyboardBuilder::make()->backButton('delete')->toJson()]
        );
        $this->storage->clearStep($this->update->getCallbackMessageChatId());
    }

    public function handleRenameCallback(string $data): void
    {
        if (!$this->isAdminCallback()) {
            $this->denyCallback();
            return;
        }

        $page = $this->parsePage($data, 'rename');
        $words = $this->getMigratedWords();

        if (empty($words)) {
            $this->showNotFound('asozlash');
            return;
        }

        $keys = array_keys($words);
        $total = count($keys);
        $totalPages = max(1, (int)ceil($total / $this->itemsPerPage));
        $page = max(1, min($totalPages, $page));

        if (!$this->checkPageBounds($page, $totalPages, $data, 'rename')) return;

        $offset = ($page - 1) * $this->itemsPerPage;
        $buttons = [];

        $typeMap = [
            'text' => 'Matn', 'photo' => 'Rasm', 'video' => 'Video', 'voice' => 'Ovozli x.', 
            'audio' => 'Audio', 'document' => 'Hujjat', 'video_note' => 'Yumaloq v.', 
            'sticker' => 'Stiker', 'animation' => 'GIF'
        ];

        for ($i = $offset; $i < min($offset + $this->itemsPerPage, $total); $i++) {
            $key = $keys[$i];
            $answer = mb_substr(strip_tags($words[$key]['content']), 0, 20);
            if (empty($answer) && $words[$key]['type'] !== 'text') {
                $answer = !empty($words[$key]['file_name']) ? mb_substr($words[$key]['file_name'], 0, 20) : "Media";
            }
            $type = $typeMap[$words[$key]['type'] ?? 'text'] ?? 'Media';
            $buttons[] = [KeyboardBuilder::btn("{$type}: $key - $answer", "rtanla=$key")];
        }

        $keyboard = array_merge($buttons, [
            [
                KeyboardBuilder::btn('⬅️', "rename&page=" . ($page - 1)),
                KeyboardBuilder::btn("$page/$totalPages", 'none'),
                KeyboardBuilder::btn('➡️', "rename&page=" . ($page + 1)),
            ],
            [KeyboardBuilder::btn("◀️ Orqaga", 'asozlash')],
        ]);

        $this->editCallback(
            "<tg-emoji emoji-id='5406745015365943482'>⬇️</tg-emoji> <b>Tahrirlash uchun tanlang!</b>",
            ['parse_mode' => 'html', 'reply_markup' => json_encode(['inline_keyboard' => $keyboard])]
        );
    }

    public function handleRenameWordCallback(string $data): void
    {
        if (!$this->isAdminCallback()) {
            $this->denyCallback();
            return;
        }

        $word = explode('=', $data, 2)[1];
        $chatId = $this->update->getCallbackMessageChatId();
        
        $words = $this->getMigratedWords();
        if (!isset($words[$word])) {
            $this->showNotFound('rename');
            return;
        }
        
        $matchType = $words[$word]['match_type'] ?? 'contains';
        $this->storage->setStep($chatId, "sozlar&{$matchType}&{$word}");
        
        $this->editCallback(
            "<tg-emoji emoji-id='5395444784611480792'>✏️</tg-emoji> <b>$word</b> uchun yangi javobni yuboring:\n\n" .
            $this->placeholderHelp() . "\n\n" .
            "<i>Siz faqat matn emas, balki barcha turdagi fayllarni yuborishingiz mumkin: Rasm, Video, Ovozli xabar, Hujjat, Stiker, GIF (Animatsiya) yoki Yumaloq video (Video note)!</i>",
            ['parse_mode' => 'html', 'reply_markup' => KeyboardBuilder::make()->backButton('rename')->toJson()]
        );
    }

    public function handleIncomingMessage(): void
    {
        if (!$this->storage->isEnabled('auto-answer')) return;

        $text = $this->update->getBusinessText() ?? $this->update->getBusinessCaption() ?? '';
        if (empty($text)) return;

        $words = $this->getMigratedWords();
        $responses = [];

        foreach ($words as $trigger => $data) {
            $matchType = $data['match_type'] ?? 'contains';
            
            if ($matchType === 'exact') {
                if ($this->normalizeText($text) === $this->normalizeText($trigger)) {
                    $responses[] = $data;
                }
            } else {
                $pattern = '/(*UCP)\b' . preg_quote($trigger, '/') . '\b/iu';
                if (preg_match($pattern, $text)) {
                    $responses[] = $data;
                }
            }
        }

        if (empty($responses)) return;

        $firstName = $this->update->getBusinessFromName();
        $lastName = $this->update->getBusinessFromLastName();
        $fromId = $this->update->getBusinessFromId();
        $username = $this->update->getBusinessFromUsername();
        $hour = date('H:i');
        $date = date('d.m.Y');
        
        $bid = $this->update->getBusinessConnectionId();
        $cid = $this->update->getBusinessChatId();

        foreach ($responses as $resp) {
            $content = $resp['content'] ?? '';
            $result = str_replace(
                ['%first%', '%last%', '%id%', '%username%', '%hour%', '%date%'],
                [$firstName, $lastName, $fromId, $username, $hour, $date],
                $content
            );
            
            $len = mb_strlen($result);
            $delayMs = min(3000000, max(1000000, $len * 40000));
            
            $type = $resp['type'] ?? 'text';
            
            $actionMap = [
                'text' => 'typing',
                'photo' => 'upload_photo',
                'video' => 'upload_video',
                'voice' => 'upload_voice',
                'audio' => 'upload_document',
                'document' => 'upload_document',
                'video_note' => 'upload_video_note',
                'sticker' => 'choose_sticker',
                'animation' => 'upload_document'
            ];
            $action = $actionMap[$type] ?? 'typing';
            
            $this->api->sendBusinessChatAction($bid, $cid, $action);
            usleep($delayMs);
            
            $fid = $resp['file_id'] ?? null;
            
            $opts = [
                'parse_mode' => 'html',
                'business_connection_id' => $bid
            ];
            
            if ($type === 'text') {
                $opts['disable_web_page_preview'] = true;
                $opts['business_connection_id'] = $bid;
                $this->api->sendMessage($cid, $result, $opts);
            } else {
                $opts['caption'] = $result;
                switch ($type) {
                    case 'photo': $this->api->sendPhoto($cid, $fid, $opts); break;
                    case 'video': $this->api->sendVideo($cid, $fid, $opts); break;
                    case 'voice': $this->api->sendVoice($cid, $fid, $opts); break;
                    case 'audio': $this->api->sendAudio($cid, $fid, $opts); break;
                    case 'document': $this->api->sendDocument($cid, $fid, $opts); break;
                    case 'video_note': $this->api->sendVideoNote($cid, $fid, $opts); break;
                    case 'sticker': $this->api->sendSticker($cid, $fid, $opts); break;
                    case 'animation': $this->api->sendAnimation($cid, $fid, $opts); break;
                }
            }
        }
    }

    private function getMigratedWords(): array
    {
        $words = $this->storage->getWords();
        $migrated = [];
        foreach ($words as $key => $value) {
            if (is_array($value)) {
                if (!isset($value['match_type'])) {
                    $value['match_type'] = 'contains';
                }
                $migrated[$key] = $value;
            } else {
                $migrated[$key] = [
                    'type' => 'text',
                    'content' => $value,
                    'file_id' => null,
                    'match_type' => 'contains'
                ];
            }
        }
        return $migrated;
    }

    private function parsePage(string $data, string $prefix): int
    {
        $needle = "{$prefix}&page=";
        return str_starts_with($data, $needle)
            ? (int)str_replace($needle, '', $data)
            : 1;
    }

    private function checkPageBounds(int $page, int $totalPages, string $data, string $prefix): bool
    {
        $prevData = "{$prefix}&page=" . ($page - 1);
        $nextData = "{$prefix}&page=" . ($page + 1);

        if ($data === $prevData && $page === 1) {
            $this->api->answerCallbackQuery($this->update->getCallbackQueryId(), "Bosh sahifadasiz!", true);
            return false;
        }
        if ($data === $nextData && $page === $totalPages) {
            $this->api->answerCallbackQuery($this->update->getCallbackQueryId(), "Mavjud emas!", true);
            return false;
        }
        return true;
    }

    private function showNotFound(string $backCallback): void
    {
        $this->showLoading();
        $this->editCallback(
            "<tg-emoji emoji-id='5260293700088511294'>⛔️</tg-emoji> <b>Ma'lumotlar topilmadi!</b>",
            ['parse_mode' => 'html', 'reply_markup' => KeyboardBuilder::make()->backButton($backCallback)->toJson()]
        );
    }

    private function showLoading(): void
    {
        $this->editCallback(
            "<tg-emoji emoji-id='5231012545799666522'>🔍</tg-emoji> <i>Qidirilmoqda...</i>",
            ['parse_mode' => 'html']
        );
    }

    private function normalizeText(string $text): string
    {
        return trim(preg_replace('/[^\p{L}\p{N}\s]/u', '', mb_strtolower($text)));
    }

    private function placeholderHelp(): string
    {
        return "<code>%first%</code> - <b>Foydalanuvchi ismi</b>\n" .
            "<code>%last%</code> - <b>Foydalanuvchi familiyasi</b>\n" .
            "<code>%id%</code> - <b>Foydalanuvchi ID si</b>\n" .
            "<code>%username%</code> - <b>Foydalanuvchi useri</b>\n" .
            "<code>%hour%</code> - <b>Soat</b>\n" .
            "<code>%date%</code> - <b>Sana</b>";
    }

    private function isPlainText(string $text): bool
    {
        return strip_tags($text) === $text && !preg_match('/[<>]/', $text);
    }
}
