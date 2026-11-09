<?php

namespace App\Handlers;

use App\Services\KeyboardBuilder;

class StoryHandler extends BaseHandler
{
    public function handleMainCallback(): void
    {
        if (!$this->isAdminCallback()) {
            $this->denyCallback();
            return;
        }

        $cid = $this->update->getCallbackMessageChatId();
        $this->storage->clearStep($cid);

        $this->editCallback(
            "<tg-emoji emoji-id='5348125953090403204'>▶️</tg-emoji> <b>Hikoyalar bo'limidasiz!</b>\n\n<i>Quyidagi amallardan birini tanlang:</i>",
            ['parse_mode' => 'html', 'reply_markup' => $this->mainKeyboard()]
        );
    }

    private function mainKeyboard(): string
    {
        return KeyboardBuilder::make()
            ->rowSingle("Yangi hikoya qo'shish", "hikoya-qosh", "5348125953090403204")
            ->rowSingle("Hikoyalar ro'yxati", "hikoya-royxat", "5231200819986047254")
            ->backButton('settings')
            ->toJson();
    }

    public function handleAddCallback(): void
    {
        if (!$this->isAdminCallback()) {
            $this->denyCallback();
            return;
        }

        $cid = $this->update->getCallbackMessageChatId();
        $this->storage->setStep($cid, 'hikoya-media');

        $this->editCallback(
            "<tg-emoji emoji-id='5348125953090403204'>▶️</tg-emoji> <b>Yangi hikoya qo'shish</b>\n\n<i>Rasm yoki video yuboring. Hikoya uchun faqat rasm yoki video qabul qilinadi.</i>",
            ['parse_mode' => 'html', 'reply_markup' => KeyboardBuilder::make()->backButton('hikoya')->toJson()]
        );
    }

    public function handleMediaStep(): void
    {
        $chatId = $this->update->getChatId();
        $bcId = $this->storage->getActiveBusinessConnectionId();

        if (!$bcId) {
            $this->api->sendMessage($chatId,
                "<tg-emoji emoji-id='5447644880824181073'>⚠️</tg-emoji> <b>Aktiv biznes ulanish topilmadi!</b>\n\n<i>Avval Telegram biznes sozlamalarida botni ulang.</i>",
                ['parse_mode' => 'html', 'reply_markup' => KeyboardBuilder::make()->backButton('hikoya')->toJson()]
            );
            return;
        }

        $msg = $this->update->getRawData()->message ?? null;

        $fileId = null;
        $fileType = null;

        $fileSize = 0;
        $duration = 0;

        if (!empty($msg->photo)) {
            $photo = end($msg->photo);
            $fileId = $photo->file_id;
            $fileType = 'photo';
            $fileSize = $photo->file_size ?? 0;
        } elseif (!empty($msg->video)) {
            $fileId = $msg->video->file_id;
            $fileType = 'video';
            $fileSize = $msg->video->file_size ?? 0;
            $duration = $msg->video->duration ?? 0;
        }

        if (!$fileId) {
            $this->api->sendMessage($chatId,
                "<tg-emoji emoji-id='5447644880824181073'>⚠️</tg-emoji> <b>Noto'g'ri fayl turi!</b>\n\n<i>Faqat rasm yoki video yuboring.</i>",
                ['parse_mode' => 'html', 'reply_markup' => KeyboardBuilder::make()->backButton('hikoya')->toJson()]
            );
            return;
        }

        if ($fileSize > 20 * 1024 * 1024) {
            $mb = round($fileSize / (1024 * 1024), 1);
            $this->api->sendMessage($chatId,
                "<tg-emoji emoji-id='5447644880824181073'>⚠️</tg-emoji> <b>Fayl hajmi juda katta ({$mb} MB)!</b>\n\n<i>Telegram Bot API cheklovi tufayli botlar faqat 20 MB gacha bo'lgan fayllarni yuklay oladi. Iltimos, hajmi 20 MB dan kichik bo'lgan video yoki rasm yuboring.</i>",
                ['parse_mode' => 'html', 'reply_markup' => KeyboardBuilder::make()->backButton('hikoya')->toJson()]
            );
            return;
        }

        if ($fileType === 'video' && $duration > 60) {
            $this->api->sendMessage($chatId,
                "<tg-emoji emoji-id='5447644880824181073'>⚠️</tg-emoji> <b>Video davomiyligi juda uzun ({$duration} soniya)!</b>\n\n<i>Telegram hikoyalar uchun videolar 60 soniyadan oshmasligi kerak.</i>",
                ['parse_mode' => 'html', 'reply_markup' => KeyboardBuilder::make()->backButton('hikoya')->toJson()]
            );
            return;
        }

        $storyId = $this->storage->saveStory([
            'business_connection_id' => $bcId,
            'file_id' => $fileId,
            'file_type' => $fileType,
            'caption' => null,
            'active_period' => 86400,
            'post_to_chat_page' => 0,
            'protect_content' => 0,
            'scheduled_at' => null,
            'status' => 'draft',
        ]);

        $this->storage->setStep($chatId, "hikoya-sarlavha&{$storyId}");

        $keyboard = KeyboardBuilder::make()
            ->rowSingle("O'tkazib yuborish", "hikoya-skip={$storyId}", "5416117059207572332")
            ->backButton('hikoya')
            ->toJson();

        $this->api->sendMessage($chatId,
            "<tg-emoji emoji-id='5395444784611480792'>✏️</tg-emoji> <b>Sarlavha (caption) yuboring:</b>\n\n<i>Agar sarlavhasiz yubormoqchi bo'lsangiz, «O'tkazib yuborish» tugmasini bosing:</i>",
            ['parse_mode' => 'html', 'reply_markup' => $keyboard]
        );
    }

    public function handleCaptionStep(string $step): void
    {
        $parts = explode('&', $step, 2);
        $storyId = (int)($parts[1] ?? 0);
        $chatId = $this->update->getChatId();
        $text = $this->update->getText() ?? '';

        if ($storyId <= 0) {
            $this->storage->clearStep($chatId);
            return;
        }

        $caption = trim($text) === '/skip' ? null : $text;
        $this->storage->updateStoryCaption($storyId, $caption);
        $this->storage->setStep($chatId, "hikoya-davr&{$storyId}");

        $keyboard = KeyboardBuilder::make()
            ->row(
                KeyboardBuilder::btn("6 soat", "hikoya-davr={$storyId}&21600"),
                KeyboardBuilder::btn("12 soat", "hikoya-davr={$storyId}&43200")
            )
            ->row(
                KeyboardBuilder::btn("24 soat", "hikoya-davr={$storyId}&86400"),
                KeyboardBuilder::btn("48 soat", "hikoya-davr={$storyId}&172800")
            )
            ->backButton('hikoya')
            ->toJson();

        $this->api->sendMessage($chatId,
            "<tg-emoji emoji-id='5341715473882955310'>⚙️</tg-emoji> <b>Hikoya davomiyligini tanlang:</b>\n\n<i>Hikoya qancha vaqt ko'rinib turishi kerak?</i>",
            ['parse_mode' => 'html', 'reply_markup' => $keyboard]
        );
    }

    public function handleSkipCaptionCallback(string $data): void
    {
        if (!$this->isAdminCallback()) {
            $this->denyCallback();
            return;
        }

        $storyId = (int)str_replace('hikoya-skip=', '', $data);
        $cid = $this->update->getCallbackMessageChatId();

        if ($storyId <= 0) {
            $this->storage->clearStep($cid);
            $this->handleMainCallback();
            return;
        }

        $this->storage->updateStoryCaption($storyId, null);
        $this->storage->setStep($cid, "hikoya-davr&{$storyId}");

        $keyboard = KeyboardBuilder::make()
            ->row(
                KeyboardBuilder::btn("6 soat", "hikoya-davr={$storyId}&21600"),
                KeyboardBuilder::btn("12 soat", "hikoya-davr={$storyId}&43200")
            )
            ->row(
                KeyboardBuilder::btn("24 soat", "hikoya-davr={$storyId}&86400"),
                KeyboardBuilder::btn("48 soat", "hikoya-davr={$storyId}&172800")
            )
            ->backButton('hikoya')
            ->toJson();

        $this->editCallback(
            "<tg-emoji emoji-id='5341715473882955310'>⚙️</tg-emoji> <b>Hikoya davomiyligini tanlang:</b>\n\n<i>Hikoya qancha vaqt ko'rinib turishi kerak?</i>",
            ['parse_mode' => 'html', 'reply_markup' => $keyboard]
        );
    }

    public function handlePeriodCallback(string $data): void
    {
        if (!$this->isAdminCallback()) {
            $this->denyCallback();
            return;
        }

        $parts = explode('=', $data, 2);
        $rest = $parts[1] ?? '';
        $restParts = explode('&', $rest, 2);
        $storyId = (int)($restParts[0] ?? 0);
        $period = (int)($restParts[1] ?? 86400);

        $validPeriods = [21600, 43200, 86400, 172800];
        if (!in_array($period, $validPeriods, true)) $period = 86400;

        $story = $this->storage->getStoryById($storyId);
        if (!$story) {
            $this->denyCallback();
            return;
        }

        $stmt = $this->storage->getPdo()->prepare("UPDATE stories SET active_period = :p WHERE id = :id");
        $stmt->execute([':p' => $period, ':id' => $storyId]);

        $cid = $this->update->getCallbackMessageChatId();
        $this->storage->clearStep($cid);

        $periodLabels = [21600 => '6 soat', 43200 => '12 soat', 86400 => '24 soat', 172800 => '48 soat'];
        $label = $periodLabels[$period] ?? '24 soat';

        $keyboard = KeyboardBuilder::make()
            ->rowSingle("Hoziroq yuklash", "hikoya-post={$storyId}", "5206607081334906820")
            ->rowSingle("Rejalash (vaqt kiriting)", "hikoya-rejalashtir={$storyId}", "5397782960512444700")
            ->backButton('hikoya')
            ->toJson();

        $this->editCallback(
            "<tg-emoji emoji-id='5341715473882955310'>⚙️</tg-emoji> <b>Davomiylik:</b> {$label}\n\n<i>Hoziroq yuklamoqchimisiz yoki rejalashtirmoqchimisiz?</i>",
            ['parse_mode' => 'html', 'reply_markup' => $keyboard]
        );
    }

    public function handlePostCallback(string $data): void
    {
        if (!$this->isAdminCallback()) {
            $this->denyCallback();
            return;
        }

        $storyId = (int)str_replace('hikoya-post=', '', $data);
        $story = $this->storage->getStoryById($storyId);

        if (!$story) {
            $this->editCallback(
                "<tg-emoji emoji-id='5260293700088511294'>⛔️</tg-emoji> <b>Hikoya topilmadi!</b>",
                ['parse_mode' => 'html', 'reply_markup' => KeyboardBuilder::make()->backButton('hikoya')->toJson()]
            );
            return;
        }

        if ($story['status'] === 'posted') {
            $this->api->answerCallbackQuery($this->update->getCallbackQueryId(), "⚠️ Ushbu hikoya allaqachon joylangan!", true);
            $this->handleViewStoryCallback("hikoya-view={$storyId}");
            return;
        }

        $result = $this->postStoryToTelegram($story);

        if ($result && $result->ok) {
            $tgStoryId = $result->result->id ?? null;
            $this->storage->updateStoryStatus($storyId, 'posted', $tgStoryId);

            $this->editCallback(
                "<tg-emoji emoji-id='5206607081334906820'>✔️</tg-emoji> <b>Hikoya muvaffaqiyatli yuklandi!</b>",
                ['parse_mode' => 'html', 'reply_markup' => KeyboardBuilder::make()->backButton('hikoya-royxat')->toJson()]
            );
        } else {
            $this->storage->updateStoryStatus($storyId, 'failed');
            $desc = htmlspecialchars($result->description ?? "Telegram API xatoligi yuz berdi.");
            $this->editCallback(
                "<tg-emoji emoji-id='5260293700088511294'>⛔️</tg-emoji> <b>Hikoya yuklanmadi!</b>\n\n<i>{$desc}</i>",
                ['parse_mode' => 'html', 'reply_markup' => KeyboardBuilder::make()->backButton('hikoya-royxat')->toJson()]
            );
        }
    }

    public function handleScheduleCallback(string $data): void
    {
        if (!$this->isAdminCallback()) {
            $this->denyCallback();
            return;
        }

        $storyId = (int)str_replace('hikoya-rejalashtir=', '', $data);
        $story = $this->storage->getStoryById($storyId);

        if (!$story) {
            $this->denyCallback();
            return;
        }

        $cid = $this->update->getCallbackMessageChatId();
        $this->storage->clearStep($cid);

        $keyboard = $this->buildSchedulePresetKeyboard($storyId, false);

        $this->editCallback(
            "<tg-emoji emoji-id='5397782960512444700'>⏰</tg-emoji> <b>Hikoya qachon yuklansin?</b>\n\n<i>Tezkor vaqtlardan birini tanlang yoki qo'lda kiriting:</i>",
            ['parse_mode' => 'html', 'reply_markup' => $keyboard]
        );
    }

    private function buildSchedulePresetKeyboard(int $storyId, bool $isEdit = false): string
    {
        $tz = new \DateTimeZone('Asia/Tashkent');
        $now = new \DateTime('now', $tz);
        $hour = (int)$now->format('H');
        $ep = $isEdit ? "&edit=1" : "";

        $kb = KeyboardBuilder::make();
        $kb->row(
            KeyboardBuilder::btn("+1 soat", "hikoya-preset={$storyId}&p=1h{$ep}"),
            KeyboardBuilder::btn("+3 soat", "hikoya-preset={$storyId}&p=3h{$ep}")
        );

        if ($hour < 20) {
            $kb->row(
                KeyboardBuilder::btn("Bugun 21:00", "hikoya-preset={$storyId}&p=today_21{$ep}"),
                KeyboardBuilder::btn("Ertaga 09:00", "hikoya-preset={$storyId}&p=tom_09{$ep}")
            );
            $kb->row(
                KeyboardBuilder::btn("Ertaga 14:00", "hikoya-preset={$storyId}&p=tom_14{$ep}"),
                KeyboardBuilder::btn("Ertaga 20:00", "hikoya-preset={$storyId}&p=tom_20{$ep}")
            );
        } else {
            $kb->row(
                KeyboardBuilder::btn("Ertaga 09:00", "hikoya-preset={$storyId}&p=tom_09{$ep}"),
                KeyboardBuilder::btn("Ertaga 14:00", "hikoya-preset={$storyId}&p=tom_14{$ep}")
            );
            $kb->row(
                KeyboardBuilder::btn("Ertaga 20:00", "hikoya-preset={$storyId}&p=tom_20{$ep}"),
                KeyboardBuilder::btn("Indinga 09:00", "hikoya-preset={$storyId}&p=after_09{$ep}")
            );
        }

        $manualCallback = $isEdit ? "hikoya-manual-edit-time={$storyId}" : "hikoya-manual-time={$storyId}";
        $backCallback = $isEdit ? "hikoya-view={$storyId}" : "hikoya";

        $kb->rowSingle("Qo'lda kiritish", $manualCallback, "5395444784611480792");
        $kb->backButton($backCallback);

        return $kb->toJson();
    }

    public function handleQuickPresetCallback(string $data): void
    {
        if (!$this->isAdminCallback()) {
            $this->denyCallback();
            return;
        }

        $isEdit = str_contains($data, '&edit=1');
        $cleanData = str_replace('&edit=1', '', $data);
        $param = str_replace('hikoya-preset=', '', $cleanData);
        [$idStr, $preset] = explode('&p=', $param, 2);
        $id = (int)$idStr;

        $story = $this->storage->getStoryById($id);
        if (!$story) {
            $this->denyCallback();
            return;
        }

        if ($story['status'] === 'posted') {
            $this->api->answerCallbackQuery($this->update->getCallbackQueryId(), "⚠️ Joylangan hikoyani tahrirlab bo'lmaydi!", true);
            $this->handleViewStoryCallback("hikoya-view={$id}");
            return;
        }

        $tz = new \DateTimeZone('Asia/Tashkent');
        $now = new \DateTime('now', $tz);

        $target = match($preset) {
            '1h' => (clone $now)->modify('+1 hour'),
            '3h' => (clone $now)->modify('+3 hours'),
            'today_21' => (clone $now)->setTime(21, 0, 0),
            'tom_09' => (clone $now)->modify('+1 day')->setTime(9, 0, 0),
            'tom_14' => (clone $now)->modify('+1 day')->setTime(14, 0, 0),
            'tom_20' => (clone $now)->modify('+1 day')->setTime(20, 0, 0),
            'after_09' => (clone $now)->modify('+2 days')->setTime(9, 0, 0),
            default => null,
        };

        if (!$target || $target->getTimestamp() <= time()) {
            $target = (clone $now)->modify('+1 hour');
        }

        $scheduledAt = $target->getTimestamp();
        $stmt = $this->storage->getPdo()->prepare("UPDATE stories SET scheduled_at = :sat, status = 'pending' WHERE id = :id");
        $stmt->execute([':sat' => $scheduledAt, ':id' => $id]);

        $cid = $this->update->getCallbackMessageChatId();
        $this->storage->clearStep($cid);

        $formatted = $target->format('d.m.Y H:i');
        $backCallback = $isEdit ? "hikoya-view={$id}" : "hikoya";
        $keyboard = KeyboardBuilder::make()
            ->backButton($backCallback)
            ->toJson();

        $this->editCallback(
            "<tg-emoji emoji-id='5206607081334906820'>✔️</tg-emoji> <b>Hikoya vaqti yangilandi!</b>\n\n— Yangi vaqt: <code>{$formatted}</code>",
            ['parse_mode' => 'html', 'reply_markup' => $keyboard]
        );
    }

    public function handleManualTimeCallback(string $data): void
    {
        if (!$this->isAdminCallback()) {
            $this->denyCallback();
            return;
        }

        $storyId = (int)str_replace('hikoya-manual-time=', '', $data);
        $cid = $this->update->getCallbackMessageChatId();
        $this->storage->setStep($cid, "hikoya-vaqt&{$storyId}");

        $this->editCallback(
            "<tg-emoji emoji-id='5395444784611480792'>✏️</tg-emoji> <b>Rejalashtirilgan vaqtni kiriting:</b>\n\n<i>Format: <code>DD.MM.YYYY HH:MM</code>\n\nMasalan: <code>25.12.2026 09:00</code></i>",
            ['parse_mode' => 'html', 'reply_markup' => KeyboardBuilder::make()->backButton("hikoya-rejalashtir={$storyId}")->toJson()]
        );
    }

    public function handleManualEditTimeCallback(string $data): void
    {
        if (!$this->isAdminCallback()) {
            $this->denyCallback();
            return;
        }

        $id = (int)str_replace('hikoya-manual-edit-time=', '', $data);
        $cid = $this->update->getCallbackMessageChatId();
        $this->storage->setStep($cid, "hikoya-edit-vaqt&{$id}");

        $this->editCallback(
            "<tg-emoji emoji-id='5395444784611480792'>✏️</tg-emoji> <b>Yangi yuklanish vaqtini kiriting:</b>\n\n" .
            "<i>Format: <code>Kun.Oy.Yil Soat:Daqiqa</code>\nMasalan: <code>15.09.2026 14:30</code></i>",
            ['parse_mode' => 'html', 'reply_markup' => KeyboardBuilder::make()->backButton("hikoya-edit-vaqt={$id}")->toJson()]
        );
    }

    public function handleScheduleTimeStep(string $step): void
    {
        $parts = explode('&', $step, 2);
        $storyId = (int)($parts[1] ?? 0);
        $chatId = $this->update->getChatId();
        $text = trim($this->update->getText() ?? '');

        if ($storyId <= 0) {
            $this->storage->clearStep($chatId);
            return;
        }

        $tz = new \DateTimeZone('Asia/Tashkent');
        $dt = \DateTime::createFromFormat('d.m.Y H:i', $text, $tz);
        if (!$dt || $dt->getTimestamp() <= time()) {
            $this->api->sendMessage($chatId,
                "<tg-emoji emoji-id='5447644880824181073'>⚠️</tg-emoji> <b>Noto'g'ri vaqt formati yoki o'tib ketgan vaqt!</b>\n\n<i>Format: <code>DD.MM.YYYY HH:MM</code>\nMasalan: <code>25.12.2026 09:00</code></i>",
                ['parse_mode' => 'html', 'reply_markup' => KeyboardBuilder::make()->backButton('hikoya')->toJson()]
            );
            return;
        }

        $scheduledAt = $dt->getTimestamp();
        $stmt = $this->storage->getPdo()->prepare("UPDATE stories SET scheduled_at = :sat, status = 'pending' WHERE id = :id");
        $stmt->execute([':sat' => $scheduledAt, ':id' => $storyId]);

        $this->storage->clearStep($chatId);

        $formatted = $dt->format('d.m.Y H:i');
        $this->api->sendMessage($chatId,
            "<tg-emoji emoji-id='5206607081334906820'>✔️</tg-emoji> <b>Hikoya rejalashtirildi!</b>\n\n— Vaqt: <code>{$formatted}</code>\n\n<i>Belgilangan vaqtda avtomatik yuklanadi.</i>",
            ['parse_mode' => 'html', 'reply_markup' => KeyboardBuilder::make()->backButton('hikoya')->toJson()]
        );
    }

    public function handleListCallback(string $data = 'hikoya-royxat'): void
    {
        if (!$this->isAdminCallback()) {
            $this->denyCallback();
            return;
        }

        $cid = $this->update->getCallbackMessageChatId();
        $this->storage->clearStep($cid);

        $allStories = $this->storage->getAllStories();

        if (empty($allStories)) {
            $this->api->answerCallbackQuery(
                $this->update->getCallbackQueryId(),
                "⚠️ Hozircha hech qanday hikoyalar mavjud emas!",
                false
            );
            $this->handleMainCallback();
            return;
        }

        $p = $this->paginate($data, count($allStories), 5);
        if (!$p) return;

        $pagedStories = array_slice($allStories, $p['offset'], $p['perPage']);

        $text = "<tg-emoji emoji-id='5231200819986047254'>📊</tg-emoji> <b>Hikoyalar ro'yxati:</b>\n\n";
        $numRow = [];

        foreach ($pagedStories as $idx => $s) {
            $num = $p['offset'] + $idx + 1;
            $id = (int)$s['id'];

            $timeTs = !empty($s['scheduled_at'])
                ? (int)$s['scheduled_at']
                : (!empty($s['posted_at']) ? (int)$s['posted_at'] : strtotime($s['created_at'] ?? 'now'));
            $timeFormatted = date('d.m.Y H:i', $timeTs);

            if (!empty($s['caption'])) {
                $captionPart = htmlspecialchars(mb_substr($s['caption'], 0, 35));
                $lineTitle = $s['status'] === 'pending'
                    ? "⏳ <i>[{$timeFormatted}]</i> {$captionPart}"
                    : "{$captionPart}";
            } else {
                $lineTitle = $s['status'] === 'pending'
                    ? "⏳ <i>[{$timeFormatted}]</i>"
                    : "{$timeFormatted}";
            }

            $text .= "<b>{$num}.</b> {$lineTitle}\n";
            $numRow[] = KeyboardBuilder::btn((string)$num, "hikoya-view={$id}");
        }

        $keyboard = KeyboardBuilder::make()
            ->row(...$numRow)
            ->pagination($p['page'], $p['totalPages'], 'hikoya-royxat')
            ->backButton('hikoya');

        $this->editCallback($text, ['parse_mode' => 'html', 'reply_markup' => $keyboard->toJson()]);
    }

    public function handleViewStoryCallback(string $data): void
    {
        if (!$this->isAdminCallback()) {
            $this->denyCallback();
            return;
        }

        $cid = $this->update->getCallbackMessageChatId();
        $this->storage->clearStep($cid);

        $id = (int)str_replace('hikoya-view=', '', $data);
        $story = $this->storage->getStoryById($id);

        if (!$story) {
            $this->api->answerCallbackQuery($this->update->getCallbackQueryId(), "Hikoya topilmadi!", true);
            $this->handleListCallback('hikoya-royxat');
            return;
        }

        $status = match($story['status']) {
            'posted' => 'Joylangan',
            'pending' => 'Rejalashtirilgan',
            'failed' => 'Xatolik yuz bergan',
            default => 'Qoralama',
        };

        $type = $story['file_type'] === 'video' ? 'Video' : 'Rasm';
        $periodMap = [21600 => '6 soat', 43200 => '12 soat', 86400 => '24 soat', 172800 => '48 soat'];
        $period = $periodMap[(int)$story['active_period']] ?? ($story['active_period'] . ' soniya');

        $timeStr = !empty($story['scheduled_at'])
            ? date('d.m.Y H:i', (int)$story['scheduled_at'])
            : (!empty($story['posted_at']) ? date('d.m.Y H:i', (int)$story['posted_at']) : date('d.m.Y H:i', strtotime($story['created_at'] ?? 'now')));

        $caption = !empty($story['caption']) ? htmlspecialchars($story['caption']) : '<i>Mavjud emas</i>';

        $html = "<tg-emoji emoji-id='5348125953090403204'>▶️</tg-emoji> <b>Hikoya ma'lumotlari:</b>\n\n" .
                "<b>Holati:</b> {$status}\n" .
                "<b>Fayl turi:</b> {$type}\n" .
                "<b>Davomiylik:</b> {$period}\n" .
                "<b>Vaqti:</b> <code>{$timeStr}</code>\n" .
                "<b>Sarlavha:</b> {$caption}\n\n";

        $keyboard = KeyboardBuilder::make();

        if ($story['status'] !== 'posted') {
            $html .= "<i>Kerakli amalni tanlang:</i>";
            $keyboard->rowSingle("Hoziroq yuklash", "hikoya-post={$id}", "5206607081334906820");
            $keyboard->row(
                KeyboardBuilder::btn("Sarlavha", "hikoya-caption={$id}", "5395444784611480792"),
                KeyboardBuilder::btn("Vaqt", "hikoya-edit-vaqt={$id}", "5397782960512444700")
            );
            $keyboard->row(
                KeyboardBuilder::btn("Davomiylik", "hikoya-edit-davr={$id}", "5341715473882955310"),
                KeyboardBuilder::btn("O'chirish", "hikoya-ochir={$id}", "5445267414562389170")
            );
        }

        $keyboard->backButton('hikoya-royxat');

        $this->editCallback($html, ['parse_mode' => 'html', 'reply_markup' => $keyboard->toJson()]);
    }

    public function handleDeleteCallback(string $data): void
    {
        if (!$this->isAdminCallback()) {
            $this->denyCallback();
            return;
        }

        $id = (int)preg_replace('/[^0-9]/', '', str_replace('hikoya-ochir=', '', $data));
        $story = $this->storage->getStoryById($id);

        if (!$story) {
            $this->api->answerCallbackQuery($this->update->getCallbackQueryId(), "Hikoya topilmadi!", true);
            $this->handleListCallback('hikoya-royxat');
            return;
        }

        if ($story['status'] === 'posted') {
            $this->api->answerCallbackQuery($this->update->getCallbackQueryId(), "⚠️ Joylangan hikoyani o'chirib bo'lmaydi!", true);
            $this->handleViewStoryCallback("hikoya-view={$id}");
            return;
        }

        $this->storage->deleteStoryRecord($id);
        $this->api->answerCallbackQuery($this->update->getCallbackQueryId(), "Hikoya o'chirildi!", false);
        $this->handleListCallback('hikoya-royxat');
    }

    public function handleEditPeriodCallback(string $data): void
    {
        if (!$this->isAdminCallback()) {
            $this->denyCallback();
            return;
        }

        $id = (int)str_replace('hikoya-edit-davr=', '', $data);
        $story = $this->storage->getStoryById($id);
        if (!$story) {
            $this->denyCallback();
            return;
        }

        if ($story['status'] === 'posted') {
            $this->api->answerCallbackQuery($this->update->getCallbackQueryId(), "⚠️ Joylangan hikoyani tahrirlab bo'lmaydi!", true);
            $this->handleViewStoryCallback("hikoya-view={$id}");
            return;
        }

        $keyboard = KeyboardBuilder::make()
            ->row(
                KeyboardBuilder::btn("6 soat", "hikoya-set-davr={$id}&21600"),
                KeyboardBuilder::btn("12 soat", "hikoya-set-davr={$id}&43200")
            )
            ->row(
                KeyboardBuilder::btn("24 soat", "hikoya-set-davr={$id}&86400"),
                KeyboardBuilder::btn("48 soat", "hikoya-set-davr={$id}&172800")
            )
            ->backButton("hikoya-view={$id}")
            ->toJson();

        $this->editCallback(
            "<tg-emoji emoji-id='5341715473882955310'>⚙️</tg-emoji> <b>Hikoya davomiyligini tanlang:</b>\n\n<i>Hikoya qancha vaqt ko'rinib turishi kerak?</i>",
            ['parse_mode' => 'html', 'reply_markup' => $keyboard]
        );
    }

    public function handleSetPeriodCallback(string $data): void
    {
        if (!$this->isAdminCallback()) {
            $this->denyCallback();
            return;
        }

        $param = str_replace('hikoya-set-davr=', '', $data);
        $parts = explode('&', $param, 2);
        $id = (int)($parts[0] ?? 0);
        $period = (int)($parts[1] ?? 86400);

        $validPeriods = [21600, 43200, 86400, 172800];
        if (!in_array($period, $validPeriods, true)) $period = 86400;

        $this->storage->updateStoryPeriod($id, $period);
        $this->api->answerCallbackQuery($this->update->getCallbackQueryId(), "Davomiylik yangilandi!", false);
        $this->handleViewStoryCallback("hikoya-view={$id}");
    }

    public function handleEditScheduleCallback(string $data): void
    {
        if (!$this->isAdminCallback()) {
            $this->denyCallback();
            return;
        }

        $id = (int)str_replace('hikoya-edit-vaqt=', '', $data);
        $story = $this->storage->getStoryById($id);
        if (!$story) {
            $this->denyCallback();
            return;
        }

        if ($story['status'] === 'posted') {
            $this->api->answerCallbackQuery($this->update->getCallbackQueryId(), "⚠️ Joylangan hikoyani tahrirlab bo'lmaydi!", true);
            $this->handleViewStoryCallback("hikoya-view={$id}");
            return;
        }

        $cid = $this->update->getCallbackMessageChatId();
        $this->storage->clearStep($cid);

        $keyboard = $this->buildSchedulePresetKeyboard($id, true);

        $this->editCallback(
            "<tg-emoji emoji-id='5397782960512444700'>⏰</tg-emoji> <b>Yangi yuklanish vaqtini tanlang:</b>\n\n<i>Tezkor vaqtlardan birini tanlang yoki qo'lda kiriting:</i>",
            ['parse_mode' => 'html', 'reply_markup' => $keyboard]
        );
    }

    public function handleEditScheduleTimeStep(string $step): void
    {
        $parts = explode('&', $step, 2);
        $id = (int)($parts[1] ?? 0);
        $chatId = $this->update->getChatId();
        $text = trim($this->update->getText() ?? '');

        if ($id <= 0) {
            $this->storage->clearStep($chatId);
            return;
        }

        $tz = new \DateTimeZone('Asia/Tashkent');
        $dt = \DateTime::createFromFormat('d.m.Y H:i', $text, $tz);

        if (!$dt || $dt->format('d.m.Y H:i') !== $text) {
            $this->api->sendMessage($chatId,
                "<tg-emoji emoji-id='5447644880824181073'>⚠️</tg-emoji> <b>Vaqt formati noto'g'ri!</b>\n\n<i>Iltimos, ushbu formatda yuboring: <code>15.09.2026 14:30</code></i>",
                ['parse_mode' => 'html', 'reply_markup' => KeyboardBuilder::make()->backButton("hikoya-view={$id}")->toJson()]
            );
            return;
        }

        $now = new \DateTime('now', $tz);
        if ($dt <= $now) {
            $this->api->sendMessage($chatId,
                "<tg-emoji emoji-id='5447644880824181073'>⚠️</tg-emoji> <b>Belgilangan vaqt o'tib ketgan!</b>\n\n<i>Kelajakdagi vaqtni kiriting.</i>",
                ['parse_mode' => 'html', 'reply_markup' => KeyboardBuilder::make()->backButton("hikoya-view={$id}")->toJson()]
            );
            return;
        }

        $this->storage->updateStorySchedule($id, $dt->getTimestamp());
        $this->storage->clearStep($chatId);

        $formatted = $dt->format('d.m.Y H:i');
        $this->api->sendMessage($chatId,
            "<tg-emoji emoji-id='5206607081334906820'>✔️</tg-emoji> <b>Hikoya vaqti yangilandi!</b>\n\n— Yangi vaqt: <code>{$formatted}</code>",
            ['parse_mode' => 'html', 'reply_markup' => KeyboardBuilder::make()->backButton("hikoya-view={$id}")->toJson()]
        );
    }

    public function handleEditCaptionCallback(string $data): void
    {
        if (!$this->isAdminCallback()) {
            $this->denyCallback();
            return;
        }

        $id = (int)str_replace('hikoya-caption=', '', $data);
        $story = $this->storage->getStoryById($id);

        if (!$story) {
            $this->denyCallback();
            return;
        }

        $cid = $this->update->getCallbackMessageChatId();
        $this->storage->setStep($cid, "hikoya-edit-caption&{$id}");

        $keyboard = KeyboardBuilder::make()
            ->rowSingle("O'tkazib yuborish", "hikoya-edit-skip={$id}", "5416117059207572332")
            ->backButton("hikoya-view={$id}")
            ->toJson();

        $this->editCallback(
            "<tg-emoji emoji-id='5395444784611480792'>✏️</tg-emoji> <b>Yangi sarlavha yuboring:</b>\n\n<i>Sarlavhani olib tashlash uchun «O'tkazib yuborish» tugmasini bosing:</i>",
            ['parse_mode' => 'html', 'reply_markup' => $keyboard]
        );
    }

    public function handleEditSkipCaptionCallback(string $data): void
    {
        if (!$this->isAdminCallback()) {
            $this->denyCallback();
            return;
        }

        $id = (int)str_replace('hikoya-edit-skip=', '', $data);
        $cid = $this->update->getCallbackMessageChatId();
        $this->storage->clearStep($cid);

        $story = $this->storage->getStoryById($id);
        if (!$story) {
            $this->denyCallback();
            return;
        }

        if ($story['status'] === 'posted') {
            $this->api->answerCallbackQuery($this->update->getCallbackQueryId(), "⚠️ Joylangan hikoyani tahrirlab bo'lmaydi!", true);
            $this->handleViewStoryCallback("hikoya-view={$id}");
            return;
        }

        $this->storage->updateStoryCaption($id, null);

        $this->editCallback(
            "<tg-emoji emoji-id='5206607081334906820'>✔️</tg-emoji> <b>Sarlavha olib tashlandi!</b>",
            ['parse_mode' => 'html', 'reply_markup' => KeyboardBuilder::make()->backButton("hikoya-view={$id}")->toJson()]
        );
    }

    public function handleEditCaptionStep(string $step): void
    {
        $parts = explode('&', $step, 2);
        $id = (int)($parts[1] ?? 0);
        $chatId = $this->update->getChatId();
        $text = $this->update->getText() ?? '';

        if ($id <= 0) {
            $this->storage->clearStep($chatId);
            return;
        }

        $story = $this->storage->getStoryById($id);
        if (!$story) {
            $this->storage->clearStep($chatId);
            return;
        }

        if ($story['status'] === 'posted') {
            $this->storage->clearStep($chatId);
            $this->api->sendMessage($chatId,
                "<tg-emoji emoji-id='5447644880824181073'>⚠️</tg-emoji> <b>Joylangan hikoya sarlavhasini tahrirlab bo'lmaydi!</b>",
                ['parse_mode' => 'html', 'reply_markup' => KeyboardBuilder::make()->backButton("hikoya-view={$id}")->toJson()]
            );
            return;
        }

        $caption = trim($text) === '/skip' ? null : $text;

        $this->storage->updateStoryCaption($id, $caption);
        $this->storage->clearStep($chatId);

        $this->api->sendMessage($chatId,
            "<tg-emoji emoji-id='5206607081334906820'>✔️</tg-emoji> <b>Sarlavha yangilandi!</b>",
            ['parse_mode' => 'html', 'reply_markup' => KeyboardBuilder::make()->backButton("hikoya-view={$id}")->toJson()]
        );
    }

    public function postStoryToTelegram(array $story): ?object
    {
        return $this->api->postStoryWithMedia($story);
    }
}
