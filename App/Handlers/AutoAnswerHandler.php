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
        $words = $this->storage->getWords();

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
            $match = ($dataArr['match_type'] ?? 'contains') === 'exact' ? 'Aniq' : 'Ixtiyoriy';
            
            $text .= "<tr>";
            $text .= "<td><code>$word</code></td>";
            $text .= "<td align='center'>$match</td>";
            $text .= "<td align='center'>$type</td>";
            
            if ($rawType === 'text') {
                $content = $dataArr['content'] ?? '';
                $clean = $escape ? htmlspecialchars($content) : $content;
                $text .= "<td>" . mb_substr($clean, 0, 50) . "</td>";
            } else {
                $caption = $dataArr['content'] ?? '';
                $cleanCap = $escape ? htmlspecialchars($caption) : $caption;
                $display = !empty($cleanCap) ? mb_substr($cleanCap, 0, 30) : ($dataArr['file_name'] ?? 'Fayl');
                $text .= "<td><i>[Media]</i> {$display}</td>";
            }
            $text .= "</tr>";
        }
        $text .= "</table>";

        $buttons = [];
        if ($page > 1) {
            $buttons[] = KeyboardBuilder::btn('⬅️', "{$prefix}&page=" . ($page - 1));
        }
        $buttons[] = KeyboardBuilder::btn("$page/$totalPages", 'none');
        if ($page < $totalPages) {
            $buttons[] = KeyboardBuilder::btn('➡️', "{$prefix}&page=" . ($page + 1));
        }

        $keyboard = KeyboardBuilder::make()
            ->row(...$buttons)
            ->rowSingle("{$altLabel} rejimga o'tish", "{$altPrefix}&page={$page}", 5460795800101594035)
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
        $words = $this->storage->getWords();

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
        $this->storage->deleteWord($word);

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
        $words = $this->storage->getWords();

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
        $this->handleWordDetailCallback($data);
    }

    public function handleWordDetailCallback(string $data): void
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

        $contentPreview = '';
        if (!empty($wordData['content'])) {
            $contentPreview = htmlspecialchars(mb_substr(strip_tags($wordData['content']), 0, 100));
        } elseif (!empty($wordData['file_name'])) {
            $contentPreview = htmlspecialchars($wordData['file_name']);
        } else {
            $contentPreview = "<i>[Fayl biriktirilgan]</i>";
        }

        $keyboard = KeyboardBuilder::make()
            ->rowSingle("Kalit so'zni o'zgartirish", "edit_key=$word", 5395444784611480792)
            ->rowSingle("Javob matni/mediasini o'zgartirish", "edit_ans=$word", 5443038326535759644)
            ->rowSingle("Moslik: {$nextMatch}", "toggle_match=$word", 5231012545799666522)
            ->backButton('rename')
            ->toJson();

        $html = "<tg-emoji emoji-id='5341715473882955310'>⚙️</tg-emoji> <b>Kalit so'z boshqaruvi</b>\n\n" .
                "<b>Kalit so'z:</b> <code>{$word}</code>\n" .
                "<b>Javob turi:</b> {$typeName}\n" .
                "<b>Moslik:</b> {$matchLabel}\n\n" .
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
            $processed[] = $resp;
        }

        $grouped = [];
        $currentTextGroup = [];

        foreach ($processed as $item) {
            $type = $item['type'] ?? 'text';
            if ($type === 'text') {
                $currentTextGroup[] = $item['processed_content'];
            } else {
                if (!empty($currentTextGroup)) {
                    $grouped[] = [
                        'type' => 'text',
                        'content' => $this->joinTexts($currentTextGroup),
                        'file_id' => null
                    ];
                    $currentTextGroup = [];
                }
                $grouped[] = [
                    'type' => $type,
                    'content' => $item['processed_content'],
                    'file_id' => $item['file_id'] ?? null
                ];
            }
        }

        if (!empty($currentTextGroup)) {
            $grouped[] = [
                'type' => 'text',
                'content' => $this->joinTexts($currentTextGroup),
                'file_id' => null
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
