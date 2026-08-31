<?php

namespace App\Handlers;

use App\Services\KeyboardBuilder;

class TimedMediaHandler extends BaseHandler
{
    public function handleSettingsCallback(string $data): void
    {
        $this->handleToggle('timed-media', $data, 'vaqtli-media', fn($v) => $this->renderSettings($v));
    }

    private function renderSettings(string $value): void
    {
        $res = $value === 'on' ? 'Yoqilgan!' : 'O\'chirilgan!';
        $cmd = $this->storage->getSetting('timed-command');
        $cmdText = $cmd !== '' ? "<code>$cmd</code>" : "<i>Belgilanmagan</i>";

        $keyboard = KeyboardBuilder::make();
        if ($value === 'on') $keyboard->rowSingle("Sozlamalar", "vaqtli-sozlash", "5341715473882955310");
        $keyboard->toggleButtons($value, 'vaqtli-media')->backButton('settings');

        $this->editCallback(
            "<tg-emoji emoji-id='5341715473882955310'>⚙️</tg-emoji> <b>Vaqtli media xabarlar sozlamalaridasiz!</b>\n\n" .
            "<i>— Hozirgi holat: $res\n— Buyruq matni: $cmdText</i>\n\n" .
            "<tg-emoji emoji-id='5406745015365943482'>⬇️</tg-emoji> <b>Bu qanday ishlaydi?</b>\n\n" .
            "<i>Telegram’dagi <b>«Bir marta koʻrish»</b> rejimidagi rasm yoki videolarni oʻchib ketishidan oldin saqlab qolish mumkin!\n\n" .
            "<b>Foydalanish:</b>\n" .
            "1. Kelgan vaqtli xabarga <b>javob (reply)</b> yozing.\n" .
            "2. Yuqoridagi buyruq matnini (<code>$cmdText</code>) yozib yuboring.\n" .
            "3. Bot mediani yuklab, sizga <b>shaxsiy chatda</b> xavfsiz yetkazadi.</i>",
            ['parse_mode' => 'html', 'reply_markup' => $keyboard->toJson()]
        );
    }

    public function handleManageCallback(): void
    {
        if (!$this->isAdminCallback()) {
            $this->denyCallback();
            return;
        }

        $cmd = $this->storage->getSetting('timed-command');
        $cmdText = $cmd !== '' ? "<code>$cmd</code>" : "<i>Belgilanmagan</i>";

        $keyboard = KeyboardBuilder::make()
            ->rowSingle("Buyruq matnini o'rnatish", "vaqtli-cmd-set", "5395444784611480792")
            ->backButton('vaqtli-media')
            ->toJson();

        $this->editCallback(
            "<tg-emoji emoji-id='5341715473882955310'>⚙️</tg-emoji> <b>Vaqtli media sozlamalari</b>\n\n" .
            "— Hozirgi buyruq matni: $cmdText\n\n" .
            "<i>Buyruqni o'rnatib, keyin shu matnga javob tariqasida yuboring — bot media ni saqlab beradi.</i>",
            ['parse_mode' => 'html', 'reply_markup' => $keyboard]
        );
    }

    public function handleSetCommandCallback(): void
    {
        if (!$this->isAdminCallback()) {
            $this->denyCallback();
            return;
        }

        $cid = $this->update->getCallbackMessageChatId();
        $this->storage->setStep($cid, 'vaqtli-command');

        $this->editCallback(
            "<tg-emoji emoji-id='5443038326535759644'>💬</tg-emoji> <b>Yangi buyruq matnini yuboring:</b>\n\n" .
            "<i>Masalan: <code>😅</code> yoki <code>.save</code> yoki istalgan matn</i>",
            ['parse_mode' => 'html', 'reply_markup' => KeyboardBuilder::make()->backButton('vaqtli-sozlash')->toJson()]
        );
    }

    public function handleSaveCommandStep(): void
    {
        $text = $this->update->getText() ?? '';
        $chatId = $this->update->getChatId();

        if (mb_strlen($text, 'UTF-8') > 50) {
            $this->api->sendMessage($chatId,
                "<tg-emoji emoji-id='5447644880824181073'>⚠️</tg-emoji> <b>Buyruq matni qabul qilinmadi!</b>\n\n<i>Matn 50 belgidan oshmasligi kerak.</i>",
                ['parse_mode' => 'html', 'reply_markup' => KeyboardBuilder::make()->backButton('vaqtli-sozlash')->toJson()]
            );
            return;
        }

        $this->storage->setSetting('timed-command', $text);
        $this->storage->clearStep($chatId);

        $this->api->sendMessage($chatId,
            "<tg-emoji emoji-id='5206607081334906820'>✔️</tg-emoji> <b>Buyruq matni o'rnatildi!</b>\n\n" .
            "— Yangi buyruq: <code>$text</code>\n\n" .
            "<i>Endi shu matnga javob yozib yuborsangiz, vaqtli media saqlanadi.</i>",
            ['parse_mode' => 'html', 'reply_markup' => KeyboardBuilder::make()->backButton('vaqtli-sozlash')->toJson()]
        );
    }

    public function handleSaveTimedMedia(): void
    {
        if (!$this->isAdminBusiness()) return;
        if (!$this->storage->isEnabled('timed-media')) return;

        $reply = $this->update->getBusinessReplyToMessage();
        if (!$reply) return;

        $cmd = $this->storage->getSetting('timed-command');

        if ($cmd === '') {
            $bid = $this->update->getBusinessConnectionId();
            $cid = $this->update->getBusinessChatId();
            $mid = $this->update->getBusinessMessageId();
            $this->api->editBusinessMessage($bid, $cid, $mid,
                "<tg-emoji emoji-id='5447644880824181073'>⚠️</tg-emoji> <b>Buyruq matni belgilanmagan!</b>\n\n" .
                "<i>Sozlamalardan buyruq matnini o'rnating.</i>",
                ['parse_mode' => 'html']
            );
            return;
        }

        $text = $this->update->getBusinessText() ?? '';
        if (trim($text) !== trim($cmd)) return;

        $this->storage->ensureMediaDir();

        $media = null;

        if (isset($reply->photo)) {
            $f = end($reply->photo);
            $media = $this->api->downloadFile($f->file_id, '.jpg', 'storage/data/media');
            if ($media) $this->api->sendPhoto($this->adminId, curl_file_create($media));

        } elseif (isset($reply->document)) {
            $ext = '.' . pathinfo($reply->document->file_name, PATHINFO_EXTENSION);
            $media = $this->api->downloadFile($reply->document->file_id, $ext, 'storage/data/media');
            if ($media) $this->api->sendDocument($this->adminId, curl_file_create($media));

        } elseif (isset($reply->video)) {
            $media = $this->api->downloadFile($reply->video->file_id, '.mp4', 'storage/data/media');
            if ($media) $this->api->sendVideo($this->adminId, curl_file_create($media));

        } elseif (isset($reply->voice)) {
            $media = $this->api->downloadFile($reply->voice->file_id, '.ogg', 'storage/data/media');
            if ($media) $this->api->sendVoice($this->adminId, curl_file_create($media));

        } elseif (isset($reply->audio)) {
            $media = $this->api->downloadFile($reply->audio->file_id, '.mp3', 'storage/data/media');
            if ($media) $this->api->sendAudio($this->adminId, curl_file_create($media));

        } elseif (isset($reply->video_note)) {
            $media = $this->api->downloadFile($reply->video_note->file_id, '.mp4', 'storage/data/media');
            if ($media) $this->api->sendVideoNote($this->adminId, curl_file_create($media));
        }

        $this->storage->clearMediaFolder('storage/data/media');
    }
}
