<?php

namespace App\Handlers;

use App\Services\KeyboardBuilder;

class AutoAnswerHandler extends BaseHandler
{
    private int $itemsPerPage = 5;

    public function handleSettingsCallback(string $data): void
    {
        $this->storage->clearStep($this->update->getCallbackMessageChatId());
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

        $this->storage->clearStep($this->update->getCallbackMessageChatId());

        $keyboard = KeyboardBuilder::make()
            ->rowSingle("Ro'yxat", "list", "5222444124698853913")
            ->row(
                KeyboardBuilder::btn("Qo'shish", "add", "5397916757333654639"),
                KeyboardBuilder::btn("Tahrirlash", "rename", "5395444784611480792")
            )
            ->backButton('avto')
            ->toJson();

        $this->editCallback(
            "<tg-emoji emoji-id='5406745015365943482'>⬇️</tg-emoji> <b>Quyidagilardan birini tanlang!</b>",
            ['parse_mode' => 'html', 'reply_markup' => $keyboard]
        );
    }

    public function handleListCallback(string $data): void
    {
        if (!$this->isAdminCallback()) {
            $this->denyCallback();
            return;
        }

        $this->renderList($data, 'list', 'formatsiz', 'Formatsiz');
    }

    public function handleListRawCallback(string $data): void
    {
        if (!$this->isAdminCallback()) {
            $this->denyCallback();
            return;
        }

        $this->renderList($data, 'formatsiz', 'list', 'Formatli', true);
    }

    private function renderList(string $data, string $prefix, string $altPrefix, string $altLabel, bool $escape = false): void
    {
        $words = $this->storage->getWords();

        if (empty($words)) {
            $this->showNotFound('asozlash');
            return;
        }

        $p = $this->paginate($data, count($words), $this->itemsPerPage);
        if (!$p) return;

        $typeMap = [
            'text' => 'Matn', 'photo' => 'Rasm', 'video' => 'Video', 'voice' => 'Ovozli x.',
            'audio' => 'Audio', 'document' => 'Hujjat', 'video_note' => 'Yumaloq v.',
            'sticker' => 'Stiker', 'animation' => 'GIF'
        ];

        $pagedWords = array_slice($words, $p['offset'], $p['perPage'], true);

        $text = "<table bordered striped>";
        $text .= "<tr><th>Kalit so'z</th><th>Holat</th><th>Turi</th><th>Tugmalar</th><th>Javob</th></tr>";
        
        foreach ($pagedWords as $word => $dataArr) {
            $rawType = $dataArr['type'] ?? 'text';
            $type = $typeMap[$rawType] ?? 'Media';
            $match = ($dataArr['match_type'] ?? 'contains') === 'exact' ? 'Aniq' : 'Ixtiyoriy';
            
            $btnCount = 0;
            if (!empty($dataArr['buttons'])) {
                foreach ($dataArr['buttons'] as $r) {
                    $btnCount += count($r);
                }
            }
            $btnText = "{$btnCount} ta";
            
            $text .= "<tr>";
            $text .= "<td><code>$word</code></td>";
            $text .= "<td align='center'>$match</td>";
            $text .= "<td align='center'>$type</td>";
            $text .= "<td align='center'>$btnText</td>";
            
            if ($rawType === 'text') {
                $content = $dataArr['content'] ?? '';
                $clean = $escape ? htmlspecialchars($content) : $content;
                $text .= "<td>" . nl2br($clean, false) . "</td>";
            } else {
                $caption = $dataArr['content'] ?? '';
                $cleanCap = $escape ? htmlspecialchars($caption) : $caption;
                $display = !empty($cleanCap) ? nl2br($cleanCap, false) : ($dataArr['file_name'] ?? 'Fayl');
                $text .= "<td><i>[Media]</i> {$display}</td>";
            }
            $text .= "</tr>";
        }
        $text .= "</table>";

        $keyboard = KeyboardBuilder::make()
            ->pagination($p['page'], $p['totalPages'], $prefix)
            ->rowSingle("{$altLabel} rejimga o'tish", "{$altPrefix}&page={$p['page']}", 5460795800101594035)
            ->backButton('asozlash')
            ->toJson();

        $cid = $this->update->getCallbackMessageChatId();
        $mid = $this->update->getCallbackMessageId();

        $this->api->deleteMessage($cid, $mid);
        $this->api->sendRichMessage($cid, ['html' => $text], ['reply_markup' => $keyboard]);
    }

    public function checkNewWord(string $wordText): void
    {
        $chatId = $this->update->getChatId();
        $existing = $this->storage->getWord($wordText);

        if ($existing !== null) {
            $this->api->sendMessage($chatId,
                "<tg-emoji emoji-id='5447644880824181073'>⚠️</tg-emoji> $wordText <b>qabul qilinmadi!</b>\n\n" .
                "<i>Ushbu so'zdan avval foydalanilgan! Boshqa so'z kiriting:</i>",
                ['parse_mode' => 'html', 'reply_markup' => KeyboardBuilder::make()->backButton('asozlash')->toJson()]
            );
            return;
        }
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
        $existing = $this->storage->getWord($wordText);

        if ($existing !== null) {
            $this->api->sendMessage($chatId,
                "<tg-emoji emoji-id='5447644880824181073'>⚠️</tg-emoji> $wordText <b>qabul qilinmadi!</b>\n\n" .
                "<i>Ushbu so'zdan avval foydalanilgan! Boshqa so'z kiriting:</i>",
                ['parse_mode' => 'html', 'reply_markup' => KeyboardBuilder::make()->backButton('asozlash')->toJson()]
            );
            return;
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

        $this->storage->saveWord($wordKey, $answerData);
        $this->storage->setStep($chatId, "abtn_add&{$wordKey}");

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

        $keyboard = KeyboardBuilder::make()
            ->rowSingle("O'tkazib yuborish", "abtn_skip={$wordKey}", "5416117059207572332")
            ->backButton('asozlash')
            ->toJson();

        $this->api->sendMessage($chatId,
            "<tg-emoji emoji-id='5206607081334906820'>✔️</tg-emoji> <b>Javob qabul qilindi!</b>\n\n" .
            "<b>Kalit so'z:</b> <code>{$wordKey}</code>\n" .
            "<b>Tur:</b> {$typeName} | <b>Moslik:</b> {$matchLabel}" .
            $contentPreview . "\n\n" .
            "<tg-emoji emoji-id='5395444784611480792'>✏️</tg-emoji> <b>Ushbu javobga tugma qo'shasizmi? (ixtiyoriy)</b>\n\n" .
            "<i>Tugma qo'shish uchun quyidagi formatda yozib yuboring:</i>\n" .
            "<code>Kanalimiz - https://t.me/kanal</code>\n" .
            "yoki\n" .
            "<code>Karta raqami - copy:8600123456789012</code>\n\n" .
            "<i>💡 Agar tugma kerak bo'lmasa, quyidagi tugmani bosing:</i>",
            ['parse_mode' => 'html', 'reply_markup' => $keyboard]
        );
    }
    
    public function handleDeleteCallback(string $data): void
    {
        if (!$this->isAdminCallback()) {
            $this->denyCallback();
            return;
        }

        $words = $this->storage->getWords();
        if (empty($words)) {
            $this->showNotFound('asozlash');
            return;
        }

        $keys = array_keys($words);
        $total = count($keys);
        $p = $this->paginate($data, $total, $this->itemsPerPage);
        if (!$p) return;

        $typeMap = [
            'text' => 'Matn', 'photo' => 'Rasm', 'video' => 'Video', 'voice' => 'Ovozli x.', 
            'audio' => 'Audio', 'document' => 'Hujjat', 'video_note' => 'Yumaloq v.', 
            'sticker' => 'Stiker', 'animation' => 'GIF'
        ];

        $keyboard = KeyboardBuilder::make();
        for ($i = $p['offset']; $i < min($p['offset'] + $p['perPage'], $total); $i++) {
            $key = $keys[$i];
            $answer = mb_substr(strip_tags($words[$key]['content']), 0, 20);
            if (empty($answer) && $words[$key]['type'] !== 'text') {
                $answer = !empty($words[$key]['file_name']) ? mb_substr($words[$key]['file_name'], 0, 20) : "Media";
            }
            $type = $typeMap[$words[$key]['type'] ?? 'text'] ?? 'Media';
            $keyboard->rowSingle("{$type}: $key - $answer", "atanla=$key");
        }

        $keyboard->pagination($p['page'], $p['totalPages'], 'delete')
            ->backButton('asozlash');

        $this->editCallback(
            "<tg-emoji emoji-id='5406745015365943482'>⬇️</tg-emoji> <b>O'chirish uchun tanlang!</b>",
            ['parse_mode' => 'html', 'reply_markup' => $keyboard->toJson()]
        );
    }

    public function handleDeleteWordCallback(string $data): void
    {
        if (!$this->isAdminCallback()) {
            $this->denyCallback();
            return;
        }

        $word = explode('=', $data, 2)[1];
        $this->storage->deleteWord($word);
        $this->storage->clearStep($this->update->getCallbackMessageChatId());

        $this->api->answerCallbackQuery($this->update->getCallbackQueryId(), "O'chirildi!", false);
        $this->handleRenameCallback('rename');
    }

    public function handleRenameCallback(string $data): void
    {
        if (!$this->isAdminCallback()) {
            $this->denyCallback();
            return;
        }

        $this->storage->clearStep($this->update->getCallbackMessageChatId());

        $words = $this->storage->getWords();
        if (empty($words)) {
            $this->showNotFound('asozlash');
            return;
        }

        $keys = array_keys($words);
        $total = count($keys);
        $p = $this->paginate($data, $total, $this->itemsPerPage);
        if (!$p) return;

        $typeMap = [
            'text' => 'Matn', 'photo' => 'Rasm', 'video' => 'Video', 'voice' => 'Ovozli x.', 
            'audio' => 'Audio', 'document' => 'Hujjat', 'video_note' => 'Yumaloq v.', 
            'sticker' => 'Stiker', 'animation' => 'GIF'
        ];

        $keyboard = KeyboardBuilder::make();
        for ($i = $p['offset']; $i < min($p['offset'] + $p['perPage'], $total); $i++) {
            $key = $keys[$i];
            $answer = mb_substr(strip_tags($words[$key]['content']), 0, 20);
            if (empty($answer) && $words[$key]['type'] !== 'text') {
                $answer = !empty($words[$key]['file_name']) ? mb_substr($words[$key]['file_name'], 0, 20) : "Media";
            }
            $type = $typeMap[$words[$key]['type'] ?? 'text'] ?? 'Media';
            $keyboard->rowSingle("{$type}: $key - $answer", "rtanla=$key");
        }

        $keyboard->pagination($p['page'], $p['totalPages'], 'rename')
            ->backButton('asozlash');

        $this->editCallback(
            "<tg-emoji emoji-id='5406745015365943482'>⬇️</tg-emoji> <b>Tahrirlash uchun tanlang!</b>",
            ['parse_mode' => 'html', 'reply_markup' => $keyboard->toJson()]
        );
    }

    public function handleRenameWordCallback(string $data): void
    {
        $this->handleWordDetailCallback($data);
    }

    public function handleWordDetailCallback(string $data): void
    {
        if (!$this->isAdminCallback()) {
            $this->denyCallback();
            return;
        }

        $this->storage->clearStep($this->update->getCallbackMessageChatId());

        $word = explode('=', $data, 2)[1];
        $wordData = $this->storage->getWord($word);
        if (!$wordData) {
            $this->showNotFound('rename');
            return;
        }

        $typeMap = [
            'text' => 'Matn', 'photo' => 'Rasm', 'video' => 'Video', 'voice' => 'Ovozli xabar', 
            'audio' => 'Audio', 'document' => 'Hujjat', 'video_note' => 'Yumaloq video', 
            'sticker' => 'Stiker', 'animation' => 'GIF (Animatsiya)'
        ];

        $rawType = $wordData['type'] ?? 'text';
        $typeName = $typeMap[$rawType] ?? 'Media';
        $matchType = $wordData['match_type'] ?? 'contains';
        $matchLabel = $matchType === 'exact' ? 'Aniq moslik' : 'Ixtiyoriy moslik';
        $nextMatch = $matchType === 'exact' ? 'Ixtiyoriy moslikka o\'tkazish' : 'Aniq moslikka o\'tkazish';

        $buttonsCount = 0;
        if (!empty($wordData['buttons'])) {
            foreach ($wordData['buttons'] as $r) {
                $buttonsCount += count($r);
            }
        }
        $buttonsLabel = $buttonsCount > 0 ? "{$buttonsCount} ta tugma" : "Mavjud emas";

        $contentPreview = '';
        if (!empty($wordData['content'])) {
            $contentPreview = htmlspecialchars(mb_substr(strip_tags($wordData['content']), 0, 100));
        } elseif (!empty($wordData['file_name'])) {
            $contentPreview = htmlspecialchars($wordData['file_name']);
        } else {
            $contentPreview = "<i>[Fayl biriktirilgan]</i>";
        }

        $keyboard = KeyboardBuilder::make()
            ->row(
                KeyboardBuilder::btn("Kalit so'z", "edit_key=$word", "5395444784611480792"),
                KeyboardBuilder::btn("Javob matni", "edit_ans=$word", "5443038326535759644")
            )
            ->row(
                KeyboardBuilder::btn("Tugmalar", "abtn_menu=$word", "5460795800101594035"),
                KeyboardBuilder::btn("Moslik turi", "toggle_match=$word", "5231012545799666522")
            )
            ->rowSingle("O'chirish", "del_word=$word", "5445267414562389170")
            ->backButton('rename')
            ->toJson();

        $html = "<tg-emoji emoji-id='5341715473882955310'>⚙️</tg-emoji> <b>Kalit so'z boshqaruvi</b>\n\n" .
                "<b>Kalit so'z:</b> <code>{$word}</code>\n" .
                "<b>Javob turi:</b> {$typeName}\n" .
                "<b>Moslik:</b> {$matchLabel}\n" .
                "<b>Tugmalar:</b> {$buttonsLabel}\n\n" .
                "<b>Hozirgi javob:</b>\n↳ {$contentPreview}\n\n" .
                "<i>Quyidagi tugmalardan birini tanlang:</i>";

        $this->editCallback($html, ['parse_mode' => 'html', 'reply_markup' => $keyboard]);
    }

    public function handleEditKeyCallback(string $data): void
    {
        if (!$this->isAdminCallback()) {
            $this->denyCallback();
            return;
        }

        $word = explode('=', $data, 2)[1];
        $chatId = $this->update->getCallbackMessageChatId();

        $wordData = $this->storage->getWord($word);
        if (!$wordData) {
            $this->showNotFound('rename');
            return;
        }

        $this->storage->setStep($chatId, "editkey&{$word}");

        $this->editCallback(
            "<tg-emoji emoji-id='5395444784611480792'>✏️</tg-emoji> <b>«{$word}» uchun yangi kalit so'zni yuboring:</b>\n\n" .
            "<i>Mijozlar ushbu yangi kalit so'zni yozganlarida bot avto-javob qaytaradi.</i>",
            ['parse_mode' => 'html', 'reply_markup' => KeyboardBuilder::make()->backButton("rtanla=$word")->toJson()]
        );
    }

    public function handleEditAnswerCallback(string $data): void
    {
        if (!$this->isAdminCallback()) {
            $this->denyCallback();
            return;
        }

        $word = explode('=', $data, 2)[1];
        $chatId = $this->update->getCallbackMessageChatId();

        $wordData = $this->storage->getWord($word);
        if (!$wordData) {
            $this->showNotFound('rename');
            return;
        }

        $matchType = $wordData['match_type'] ?? 'contains';
        $this->storage->setStep($chatId, "sozlar&{$matchType}&{$word}");

        $this->editCallback(
            "<tg-emoji emoji-id='5395444784611480792'>✏️</tg-emoji> <b>«{$word}» uchun yangi javobni yuboring:</b>\n\n" .
            $this->placeholderHelp() . "\n\n" .
            "<i>Siz faqat matn emas, balki barcha turdagi fayllarni yuborishingiz mumkin: Rasm, Video, Ovozli xabar, Hujjat, Stiker, GIF yoki Yumaloq video!</i>",
            ['parse_mode' => 'html', 'reply_markup' => KeyboardBuilder::make()->backButton("rtanla=$word")->toJson()]
        );
    }

    public function handleToggleMatchCallback(string $data): void
    {
        if (!$this->isAdminCallback()) {
            $this->denyCallback();
            return;
        }

        $word = explode('=', $data, 2)[1];
        $wordData = $this->storage->getWord($word);
        if (!$wordData) {
            $this->showNotFound('rename');
            return;
        }

        $current = $wordData['match_type'] ?? 'contains';
        $newMatch = $current === 'exact' ? 'contains' : 'exact';
        $wordData['match_type'] = $newMatch;

        $this->storage->saveWord($word, $wordData);

        $newLabel = $newMatch === 'exact' ? 'Aniq moslik' : 'Ixtiyoriy moslik';
        $this->api->answerCallbackQuery($this->update->getCallbackQueryId(), "Moslik: $newLabel", false);

        $this->handleWordDetailCallback($data);
    }

    public function handleButtonMenuCallback(string $data): void
    {
        if (!$this->isAdminCallback()) {
            $this->denyCallback();
            return;
        }

        $this->storage->clearStep($this->update->getCallbackMessageChatId());

        $param = explode('=', $data, 2)[1] ?? '';
        $word = str_contains($param, '&page=') ? explode('&page=', $param, 2)[0] : $param;

        $wordData = $this->storage->getWord($word);
        if (!$wordData) {
            $this->showNotFound('rename');
            return;
        }

        $flatButtons = $this->flattenButtons($wordData['buttons'] ?? []);
        $total = count($flatButtons);

        $keyboard = KeyboardBuilder::make();

        if ($total === 0) {
            $html = "<tg-emoji emoji-id='5460795800101594035'>🔘</tg-emoji> <b>«{$word}» tugmalar bo'limidasiz!</b>\n\n" .
                    "<i>Hozircha ushbu javobda hech qanday tugma mavjud emas.</i>";

            $keyboard->rowSingle("Tugma qo'shish", "abtn_append=$word", "5397916757333654639");
            $keyboard->backButton("rtanla=$word");
        } else {
            $p = $this->paginate($data, $total, 5);
            if (!$p) return;

            $paged = array_slice($flatButtons, $p['offset'], $p['perPage'], true);

            foreach ($paged as $globalIdx => $item) {
                $btnText = $item['btn']['text'] ?? "Tugma #" . ($globalIdx + 1);
                $keyboard->rowSingle($btnText, "abtn_view={$word}&{$globalIdx}");
            }

            $keyboard->rowSingle("Yana qo'shish", "abtn_append=$word", "5397916757333654639")
                ->pagination($p['page'], $p['totalPages'], "abtn_menu={$word}")
                ->backButton("rtanla=$word");

            $html = "<tg-emoji emoji-id='5460795800101594035'>🔘</tg-emoji> <b>«{$word}» tugmalar bo'limidasiz!</b>\n\n" .
                    "<i>Quyidagi tugmalardan birini tanlang:</i>";
        }

        $this->editCallback($html, ['parse_mode' => 'html', 'reply_markup' => $keyboard->toJson()]);
    }

    public function handleViewSingleButtonCallback(string $data): void
    {
        if (!$this->isAdminCallback()) {
            $this->denyCallback();
            return;
        }

        $this->storage->clearStep($this->update->getCallbackMessageChatId());

        $param = str_replace('abtn_view=', '', $data);
        $parts = explode('&', $param, 2);
        $word = $parts[0] ?? '';
        $globalIdx = (int)($parts[1] ?? 0);

        $wordData = $this->storage->getWord($word);
        if (!$wordData) {
            $this->showNotFound('rename');
            return;
        }

        $flatButtons = $this->flattenButtons($wordData['buttons'] ?? []);
        if (!isset($flatButtons[$globalIdx])) {
            $this->handleButtonMenuCallback("abtn_menu=$word");
            return;
        }

        $btn = $flatButtons[$globalIdx]['btn'];
        $bText = htmlspecialchars($btn['text'] ?? '');

        if (isset($btn['url'])) {
            $typeLabel = "Havola (URL)";
            $valLabel = "Havola";
            $valText = htmlspecialchars($btn['url']);
        } else {
            $typeLabel = "Nusxalash (copy_text)";
            $valLabel = "Nusxalanadigan matn";
            $valText = htmlspecialchars($btn['copy_text']['text'] ?? '');
        }

        $keyboard = KeyboardBuilder::make()
            ->row(
                KeyboardBuilder::btn("Tahrirlash", "abtn_editone={$word}&{$globalIdx}", "5395444784611480792"),
                KeyboardBuilder::btn("O'chirish", "abtn_delbtn={$word}&{$globalIdx}", "5445267414562389170")
            )
            ->backButton("abtn_menu=$word")
            ->toJson();

        $html = "<tg-emoji emoji-id='5460795800101594035'>🔘</tg-emoji> <b>Tugma ma'lumotlari:</b>\n\n" .
                "<b>Nomi:</b> {$bText}\n" .
                "<b>Turi:</b> {$typeLabel}\n" .
                "<b>{$valLabel}:</b> <code>{$valText}</code>\n\n" .
                "<i>Kerakli amalni tanlang:</i>";

        $this->editCallback($html, ['parse_mode' => 'html', 'reply_markup' => $keyboard]);
    }

    public function handleEditSingleButtonCallback(string $data): void
    {
        if (!$this->isAdminCallback()) {
            $this->denyCallback();
            return;
        }

        $param = str_replace('abtn_editone=', '', $data);
        $parts = explode('&', $param, 2);
        $word = $parts[0] ?? '';
        $globalIdx = (int)($parts[1] ?? 0);

        $wordData = $this->storage->getWord($word);
        if (!$wordData) {
            $this->showNotFound('rename');
            return;
        }

        $flatButtons = $this->flattenButtons($wordData['buttons'] ?? []);
        if (!isset($flatButtons[$globalIdx])) {
            $this->handleButtonMenuCallback("abtn_menu=$word");
            return;
        }

        $btn = $flatButtons[$globalIdx]['btn'];
        $bText = $btn['text'] ?? '';
        $val = isset($btn['url']) ? $btn['url'] : 'copy:' . ($btn['copy_text']['text'] ?? '');
        $currentStr = "{$bText} - {$val}";

        $chatId = $this->update->getCallbackMessageChatId();
        $this->storage->setStep($chatId, "abtn_editone&{$word}&{$globalIdx}");

        $html = "<tg-emoji emoji-id='5395444784611480792'>✏️</tg-emoji> <b>Tugmani tahrirlash:</b>\n\n" .
                "<i>Hozirgi holat:</i>\n" .
                "<code>{$currentStr}</code>\n\n" .
                "<i>Yuqoridagi matndan nusxa oling va o'zgartirib qayta yuboring:</i>";

        $this->editCallback($html, [
            'parse_mode' => 'html',
            'reply_markup' => KeyboardBuilder::make()->backButton("abtn_view={$word}&{$globalIdx}")->toJson()
        ]);
    }

    public function handleSaveSingleButtonStep(string $step): void
    {
        $parts = explode('&', $step, 3);
        $word = $parts[1] ?? '';
        $globalIdx = (int)($parts[2] ?? 0);
        $chatId = $this->update->getChatId();
        $text = trim($this->update->getText() ?? '');

        if (empty($text) || empty($word)) {
            $this->api->sendMessage($chatId,
                "<tg-emoji emoji-id='5447644880824181073'>⚠️</tg-emoji> <b>Tugma matni bo'sh bo'lishi mumkin emas!</b>",
                ['parse_mode' => 'html', 'reply_markup' => KeyboardBuilder::make()->backButton("abtn_view={$word}&{$globalIdx}")->toJson()]
            );
            return;
        }

        $wordData = $this->storage->getWord($word);
        if (!$wordData) {
            $this->storage->clearStep($chatId);
            return;
        }

        $flatButtons = $this->flattenButtons($wordData['buttons'] ?? []);
        if (!isset($flatButtons[$globalIdx])) {
            $this->storage->clearStep($chatId);
            return;
        }

        $parsedKeyboard = $this->parseButtonsInput($text);
        if (empty($parsedKeyboard) || empty($parsedKeyboard[0][0])) {
            $this->api->sendMessage($chatId,
                "<tg-emoji emoji-id='5447644880824181073'>⚠️</tg-emoji> <b>Format noto'g'ri!</b>\n\n" .
                "<i>Iltimos, ko'rsatilgan formatda yuboring:\n" .
                "<code>Tugma matni - https://...</code>\nyoki\n" .
                "<code>Tugma matni - copy:matn</code></i>",
                ['parse_mode' => 'html', 'reply_markup' => KeyboardBuilder::make()->backButton("abtn_view={$word}&{$globalIdx}")->toJson()]
            );
            return;
        }

        $newBtn = $parsedKeyboard[0][0];
        $r = $flatButtons[$globalIdx]['r'];
        $c = $flatButtons[$globalIdx]['c'];

        $wordData['buttons'][$r][$c] = $newBtn;
        $this->storage->saveWord($word, $wordData);
        $this->storage->clearStep($chatId);

        $keyboard = KeyboardBuilder::make()
            ->rowSingle("Tugmani ko'rish", "abtn_view={$word}&{$globalIdx}", "5460795800101594035")
            ->backButton("abtn_menu=$word")
            ->toJson();

        $this->api->sendMessage($chatId,
            "<tg-emoji emoji-id='5206607081334906820'>✔️</tg-emoji> <b>Tugma muvaffaqiyatli tahrirlandi!</b>",
            ['parse_mode' => 'html', 'reply_markup' => $keyboard]
        );
    }

    public function handleAppendButtonCallback(string $data): void
    {
        if (!$this->isAdminCallback()) {
            $this->denyCallback();
            return;
        }

        $word = explode('=', $data, 2)[1];
        $chatId = $this->update->getCallbackMessageChatId();
        $wordData = $this->storage->getWord($word);
        if (!$wordData) {
            $this->showNotFound('rename');
            return;
        }

        $this->storage->setStep($chatId, "abtn_append&{$word}");

        $html = "<tg-emoji emoji-id='5397916757333654639'>➕</tg-emoji> <b>«{$word}» ga yangi tugma qo'shish:</b>\n\n" .
                "<i>Format bo'yicha yangi tugmani yuboring:</i>\n" .
                "<code>Tugma matni - https://...</code>\n" .
                "yoki\n" .
                "<code>Tugma matni - copy:matn</code>\n\n" .
                "<i>Ushbu yangi tugma mavjud tugmalar ro'yxatiga qo'shiladi.</i>";

        $this->editCallback($html, [
            'parse_mode' => 'html',
            'reply_markup' => KeyboardBuilder::make()->backButton("abtn_menu=$word")->toJson()
        ]);
    }

    public function handleDeleteSingleButtonCallback(string $data): void
    {
        if (!$this->isAdminCallback()) {
            $this->denyCallback();
            return;
        }

        $param = str_replace('abtn_delbtn=', '', $data);
        $parts = explode('&', $param, 2);
        $word = $parts[0] ?? '';
        $globalIdx = (int)($parts[1] ?? 0);

        $wordData = $this->storage->getWord($word);
        if (!$wordData) {
            $this->showNotFound('rename');
            return;
        }

        $flatButtons = $this->flattenButtons($wordData['buttons'] ?? []);
        if (isset($flatButtons[$globalIdx])) {
            $r = $flatButtons[$globalIdx]['r'];
            $c = $flatButtons[$globalIdx]['c'];

            unset($wordData['buttons'][$r][$c]);
            $wordData['buttons'][$r] = array_values($wordData['buttons'][$r]);
            if (empty($wordData['buttons'][$r])) {
                unset($wordData['buttons'][$r]);
            }
            $wordData['buttons'] = array_values($wordData['buttons']);
            if (empty($wordData['buttons'])) {
                unset($wordData['buttons']);
            }

            $this->storage->saveWord($word, $wordData);
            $this->api->answerCallbackQuery($this->update->getCallbackQueryId(), "Tugma o'chirildi!", false);
        }

        $this->handleButtonMenuCallback("abtn_menu=$word");
    }

    private function flattenButtons(array $buttons): array
    {
        $flat = [];
        foreach ($buttons as $r => $row) {
            foreach ($row as $c => $btn) {
                $flat[] = [
                    'r' => $r,
                    'c' => $c,
                    'btn' => $btn
                ];
            }
        }
        return $flat;
    }

    public function handleSkipButtonCallback(string $data): void
    {
        if (!$this->isAdminCallback()) {
            $this->denyCallback();
            return;
        }

        $word = explode('=', $data, 2)[1] ?? '';
        $chatId = $this->update->getCallbackMessageChatId();
        $this->storage->clearStep($chatId);

        $wordData = $this->storage->getWord($word);
        $typeMap = [
            'text' => 'Matn', 'photo' => 'Rasm', 'video' => 'Video', 'voice' => 'Ovozli x.',
            'audio' => 'Audio', 'document' => 'Hujjat', 'video_note' => 'Yumaloq v.',
            'sticker' => 'Stiker', 'animation' => 'GIF'
        ];
        $typeName = $typeMap[$wordData['type'] ?? 'text'] ?? 'Media';
        $matchLabel = ($wordData['match_type'] ?? 'contains') === 'exact' ? 'Aniq' : 'Ixtiyoriy';

        $this->editCallback(
            "<tg-emoji emoji-id='5206607081334906820'>✔️</tg-emoji> <b>Avto javob muvaffaqiyatli saqlandi!</b>\n\n" .
            "<b>Kalit so'z:</b> <code>{$word}</code>\n" .
            "<b>Tur:</b> {$typeName} | <b>Moslik:</b> {$matchLabel}\n" .
            "<b>Tugmalar:</b> <i>Qo'shilmadi</i>",
            ['parse_mode' => 'html', 'reply_markup' => KeyboardBuilder::make()->backButton('asozlash')->toJson()]
        );
    }

    public function handleSaveButtonStep(string $step): void
    {
        $parts = explode('&', $step, 2);
        $action = $parts[0];
        $word = $parts[1] ?? '';
        $chatId = $this->update->getChatId();
        $text = trim($this->update->getText() ?? '');

        if (empty($text) || empty($word)) {
            $this->api->sendMessage($chatId,
                "<tg-emoji emoji-id='5447644880824181073'>⚠️</tg-emoji> <b>Tugma matni bo'sh bo'lishi mumkin emas!</b>",
                ['parse_mode' => 'html', 'reply_markup' => KeyboardBuilder::make()->rowSingle("O'tkazib yuborish", "abtn_skip={$word}", "5416117059207572332")->backButton('asozlash')->toJson()]
            );
            return;
        }

        $wordData = $this->storage->getWord($word);
        if (!$wordData) {
            $this->storage->clearStep($chatId);
            $this->api->sendMessage($chatId,
                "<tg-emoji emoji-id='5260293700088511294'>⛔️</tg-emoji> <b>Kalit so'z topilmadi!</b>",
                ['parse_mode' => 'html', 'reply_markup' => KeyboardBuilder::make()->backButton('asozlash')->toJson()]
            );
            return;
        }

        $parsedKeyboard = $this->parseButtonsInput($text);
        if (empty($parsedKeyboard)) {
            $this->api->sendMessage($chatId,
                "<tg-emoji emoji-id='5447644880824181073'>⚠️</tg-emoji> <b>Tugma formati noto'g'ri!</b>\n\n" .
                "<i>Iltimos, quyidagi formatda yuboring:\n" .
                "<code>Tugma matni - https://...</code>\nyoki\n" .
                "<code>Tugma matni - copy:matn</code></i>\n\n" .
                "<i>Agar tugma qo'shishni xohlamasangiz, o'tkazib yuborish tugmasini bosing:</i>",
                ['parse_mode' => 'html', 'reply_markup' => KeyboardBuilder::make()->rowSingle("O'tkazib yuborish", "abtn_skip={$word}", "5416117059207572332")->backButton('asozlash')->toJson()]
            );
            return;
        }

        if ($action === 'abtn_append' && !empty($wordData['buttons'])) {
            $wordData['buttons'] = array_merge($wordData['buttons'], $parsedKeyboard);
        } else {
            $wordData['buttons'] = $parsedKeyboard;
        }

        $this->storage->saveWord($word, $wordData);
        $this->storage->clearStep($chatId);

        $previewKeyboard = array_merge($wordData['buttons'], [
            [KeyboardBuilder::btn("◀️ Orqaga", "rtanla=$word")]
        ]);

        $this->api->sendMessage($chatId,
            "<tg-emoji emoji-id='5206607081334906820'>✔️</tg-emoji> <b>«{$word}» uchun avto javob va tugmalar muvaffaqiyatli saqlandi!</b>\n\n" .
            "<i>Quyida sozlangan tugmalar namunasi:</i>",
            [
                'parse_mode' => 'html',
                'reply_markup' => json_encode(['inline_keyboard' => $previewKeyboard])
            ]
        );
    }

    public function handleClearButtonsCallback(string $data): void
    {
        if (!$this->isAdminCallback()) {
            $this->denyCallback();
            return;
        }

        $word = explode('=', $data, 2)[1];
        $wordData = $this->storage->getWord($word);
        if (!$wordData) {
            $this->showNotFound('rename');
            return;
        }

        unset($wordData['buttons']);
        $this->storage->saveWord($word, $wordData);

        $this->api->answerCallbackQuery($this->update->getCallbackQueryId(), "Barcha tugmalar tozalandi!", false);
        $this->handleButtonMenuCallback("abtn_menu=$word");
    }

    private function formatButtonsToText(array $buttons): string
    {
        $lines = [];
        foreach ($buttons as $row) {
            $rowParts = [];
            foreach ($row as $btn) {
                $text = $btn['text'] ?? '';
                if (isset($btn['url'])) {
                    $rowParts[] = "{$text} - " . $btn['url'];
                } elseif (isset($btn['copy_text']['text'])) {
                    $rowParts[] = "{$text} - copy:" . $btn['copy_text']['text'];
                }
            }
            if (!empty($rowParts)) {
                $lines[] = implode(' | ', $rowParts);
            }
        }
        return implode("\n", $lines);
    }

    private function parseButtonsInput(string $text): array
    {
        $lines = explode("\n", trim($text));
        $keyboard = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;

            $row = [];
            $rawButtons = explode('|', $line);

            foreach ($rawButtons as $btnStr) {
                $btnStr = trim($btnStr);
                if ($btnStr === '') continue;

                $parts = preg_split('/\s+(?:-|->|=|\+)\s+/u', $btnStr, 2);
                if (count($parts) < 2) continue;

                $btnText = trim($parts[0]);
                $btnVal = trim($parts[1]);
                if ($btnText === '' || $btnVal === '') continue;

                $lowerVal = mb_strtolower($btnVal);
                if (str_starts_with($lowerVal, 'copy:') || str_starts_with($lowerVal, 'copy_text:')) {
                    $copyText = trim(substr($btnVal, strpos($btnVal, ':') + 1));
                    if ($copyText !== '') {
                        $row[] = [
                            'text' => $btnText,
                            'copy_text' => ['text' => $copyText]
                        ];
                    }
                } elseif (preg_match('#^(https?://|tg://|t\.me/)#i', $btnVal)) {
                    $url = $btnVal;
                    if (str_starts_with($lowerVal, 't.me/')) {
                        $url = 'https://' . $url;
                    }
                    $row[] = [
                        'text' => $btnText,
                        'url' => $url
                    ];
                } else {
                    $row[] = [
                        'text' => $btnText,
                        'copy_text' => ['text' => $btnVal]
                    ];
                }
            }

            if (!empty($row)) {
                $keyboard[] = $row;
            }
        }

        return $keyboard;
    }

    public function handleRenameKeywordStep(string $step): void
    {
        $oldWord = explode('&', $step, 2)[1] ?? '';
        $newWord = trim($this->update->getText() ?? '');
        $chatId = $this->update->getChatId();

        if (empty($newWord)) {
            $this->api->sendMessage($chatId,
                "<tg-emoji emoji-id='5447644880824181073'>⚠️</tg-emoji> <b>Yangi kalit so'z bo'sh bo'lishi mumkin emas!</b>",
                ['parse_mode' => 'html', 'reply_markup' => KeyboardBuilder::make()->backButton("rtanla=$oldWord")->toJson()]
            );
            return;
        }

        if (str_contains($newWord, '<') || str_contains($newWord, '>')) {
            $this->api->sendMessage($chatId,
                "<tg-emoji emoji-id='5447644880824181073'>⚠️</tg-emoji> <b>Kalit so'zda HTML teglar bo'lishi mumkin emas!</b>",
                ['parse_mode' => 'html', 'reply_markup' => KeyboardBuilder::make()->backButton("rtanla=$oldWord")->toJson()]
            );
            return;
        }

        if ($newWord !== $oldWord) {
            $existing = $this->storage->getWord($newWord);
            if ($existing !== null) {
                $this->api->sendMessage($chatId,
                    "<tg-emoji emoji-id='5447644880824181073'>⚠️</tg-emoji> <code>{$newWord}</code> <b>so'zi allaqachon mavjud!</b>\n\n<i>Boshqa kalit so'z kiriting:</i>",
                    ['parse_mode' => 'html', 'reply_markup' => KeyboardBuilder::make()->backButton("rtanla=$oldWord")->toJson()]
                );
                return;
            }

            $this->storage->renameWord($oldWord, $newWord);
        }

        $this->storage->clearStep($chatId);

        $this->api->sendMessage($chatId,
            "<tg-emoji emoji-id='5206607081334906820'>✔️</tg-emoji> <b>Kalit so'z muvaffaqiyatli o'zgartirildi!</b>\n\n" .
            "<b>Eski so'z:</b> <code>{$oldWord}</code>\n" .
            "<b>Yangi so'z:</b> <code>{$newWord}</code>",
            ['parse_mode' => 'html', 'reply_markup' => KeyboardBuilder::make()->backButton('rename')->toJson()]
        );
    }

    public function handleIncomingMessage(): void
    {
        if (!$this->storage->isEnabled('auto-answer')) return;

        $text = $this->update->getBusinessText() ?? $this->update->getBusinessCaption() ?? '';
        if (empty($text)) return;

        $responses = $this->storage->findMatchingWords($text);
        if (empty($responses)) return;

        $firstName = htmlspecialchars($this->update->getBusinessFromName() ?? '');
        $lastName = htmlspecialchars($this->update->getBusinessFromLastName() ?? '');
        $fromId = htmlspecialchars((string)($this->update->getBusinessFromId() ?? ''));
        $username = htmlspecialchars($this->update->getBusinessFromUsername() ?? '');
        $hour = date('H:i');
        $date = date('d.m.Y');
        
        $bid = $this->update->getBusinessConnectionId();
        $cid = $this->update->getBusinessChatId();

        $processed = [];
        foreach ($responses as $resp) {
            $content = $resp['content'] ?? '';
            $result = str_replace(
                ['%first%', '%last%', '%id%', '%username%', '%hour%', '%date%'],
                [$firstName, $lastName, $fromId, $username, $hour, $date],
                $content
            );
            $resp['processed_content'] = $result;

            if (!empty($resp['buttons'])) {
                $processedButtons = [];
                foreach ($resp['buttons'] as $row) {
                    $newRow = [];
                    foreach ($row as $btn) {
                        $newBtn = $btn;
                        if (isset($newBtn['url'])) {
                            $newBtn['url'] = str_replace(
                                ['%first%', '%last%', '%id%', '%username%', '%hour%', '%date%'],
                                [$firstName, $lastName, $fromId, $username, $hour, $date],
                                $newBtn['url']
                            );
                        }
                        if (isset($newBtn['copy_text']['text'])) {
                            $newBtn['copy_text']['text'] = str_replace(
                                ['%first%', '%last%', '%id%', '%username%', '%hour%', '%date%'],
                                [$firstName, $lastName, $fromId, $username, $hour, $date],
                                $newBtn['copy_text']['text']
                            );
                        }
                        $newRow[] = $newBtn;
                    }
                    $processedButtons[] = $newRow;
                }
                $resp['processed_buttons'] = $processedButtons;
            }

            $processed[] = $resp;
        }

        $grouped = [];
        $currentTextGroup = [];
        $currentButtons = [];

        foreach ($processed as $item) {
            $type = $item['type'] ?? 'text';
            if ($type === 'text') {
                $currentTextGroup[] = $item['processed_content'];
                if (!empty($item['processed_buttons'])) {
                    foreach ($item['processed_buttons'] as $bRow) {
                        $currentButtons[] = $bRow;
                    }
                }
            } else {
                if (!empty($currentTextGroup)) {
                    $grouped[] = [
                        'type' => 'text',
                        'content' => $this->joinTexts($currentTextGroup),
                        'file_id' => null,
                        'buttons' => $currentButtons
                    ];
                    $currentTextGroup = [];
                    $currentButtons = [];
                }
                $grouped[] = [
                    'type' => $type,
                    'content' => $item['processed_content'],
                    'file_id' => $item['file_id'] ?? null,
                    'buttons' => $item['processed_buttons'] ?? []
                ];
            }
        }

        if (!empty($currentTextGroup)) {
            $grouped[] = [
                'type' => 'text',
                'content' => $this->joinTexts($currentTextGroup),
                'file_id' => null,
                'buttons' => $currentButtons
            ];
        }

        foreach ($grouped as $resp) {
            $result = $resp['content'];
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

            if (!empty($resp['buttons'])) {
                $opts['reply_markup'] = json_encode(['inline_keyboard' => $resp['buttons']]);
            }
            
            if ($type === 'text') {
                $opts['disable_web_page_preview'] = true;
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

    private function joinTexts(array $texts): string
    {
        $out = '';
        foreach ($texts as $t) {
            $t = trim($t);
            if ($t === '') continue;
            if ($out === '') {
                $out = $t;
            } else {
                if (str_ends_with($out, "\n") || str_starts_with($t, "\n")) {
                    $out .= "\n" . ltrim($t, "\n");
                } else {
                    $out .= " " . $t;
                }
            }
        }
        return $out;
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
