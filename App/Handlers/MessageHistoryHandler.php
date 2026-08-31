<?php

namespace App\Handlers;

use App\Services\KeyboardBuilder;

class MessageHistoryHandler extends BaseHandler
{

    public function handleEditedSettingsCallback(string $data): void
    {
        $this->handleToggle(
            'edited-message',
            $data,
            'tahrirlangan',
            fn(string $v) => $this->renderEditedSettings($v)
        );
    }

    public function handleDeletedSettingsCallback(string $data): void
    {
        $this->handleToggle(
            'deleted-message',
            $data,
            'ochirilgan',
            fn(string $v) => $this->renderDeletedSettings($v)
        );
    }

    private function renderEditedSettings(string $value): void
    {
        $res = $value === 'on' ? 'Yoqilgan!' : 'O\'chirilgan!';
        $keyboard = KeyboardBuilder::make()
            ->toggleButtons($value, 'tahrirlangan')
            ->backButton('settings')
            ->toJson();

        $this->editCallback(
            "<tg-emoji emoji-id='5341715473882955310'>⚙️</tg-emoji> <b>Tahrirlangan xabarlar sozlamalaridasiz!</b>\n\n" .
            "<i>— Hozirgi holat: $res</i>\n\n" .
            "<tg-emoji emoji-id='5406745015365943482'>⬇️</tg-emoji> <b>Chatlarda foydalanish:</b>\n\n" .
            "<i>Barcha chatlarda avtomatik tarzda amalga oshiriladi va tahrirlangan xabarlar haqida ma'lumotlar bot orqali sizga yuboriladi!</i>",
            ['parse_mode' => 'html', 'reply_markup' => $keyboard]
        );
    }

    private function renderDeletedSettings(string $value): void
    {
        $res = $value === 'on' ? 'Yoqilgan!' : 'O\'chirilgan!';
        $keyboard = KeyboardBuilder::make()
            ->toggleButtons($value, 'ochirilgan')
            ->backButton('settings')
            ->toJson();

        $this->editCallback(
            "<tg-emoji emoji-id='5341715473882955310'>⚙️</tg-emoji> <b>O'chirilgan xabarlar sozlamalaridasiz!</b>\n\n" .
            "<i>— Hozirgi holat: $res</i>\n\n" .
            "<tg-emoji emoji-id='5406745015365943482'>⬇️</tg-emoji> <b>Chatlarda foydalanish:</b>\n\n" .
            "<i>Barcha chatlarda avtomatik tarzda amalga oshiriladi va o'chirilgan xabarlar haqida ma'lumotlar bot orqali sizga yuboriladi!</i>",
            ['parse_mode' => 'html', 'reply_markup' => $keyboard]
        );
    }

    public function saveIncomingMessage(): void
    {
        $update = $this->update;
        $chatId = $update->getBusinessChatId();
        $history = $this->storage->getHistory($chatId);

        $newEntry = [
            'user_id' => $update->getBusinessFromId(),
            'message_id' => $update->getBusinessMessageId(),
            'reply_to_message_id' => $update->getBusinessReplyToMessageId(),
            'username' => $update->getBusinessFromUsername(),
            'first_name' => $update->getBusinessFromName(),
            'last_name' => $update->getBusinessFromLastName(),
            'text' => $update->getBusinessText() ?? $update->getBusinessCaption(),
            'date' => date('Y-m-d H:i:s', $update->getBusinessDate()),
            'edit_date' => null,
            'edit_message' => [],
            'media' => $update->getMediaItems(),
        ];

        $history[] = $newEntry;

        if (count($history) > 2000) {
            $history = array_slice($history, -2000);
        }

        $this->storage->saveHistory($chatId, $history);
    }

    public function handleEditedMessage(): void
    {
        if (!$this->storage->isEnabled('edited-message')) return;

        $editedChatId = $this->update->getEditedChatId();
        $editedMessageId = $this->update->getEditedMessageId();
        $editedFromId = $this->update->getEditedFromId();
        
        $newText = $this->update->getEditedText() ?? $this->update->getEditedCaption();
        $newMedia = $this->update->getEditedMediaItems();
        $editDate = date('Y-m-d H:i:s', $this->update->getEditedDate());

        $found = $this->storage->findMessageInHistory($editedChatId, $editedMessageId);
        if (!$found) return;

        $index = $found['index'];
        $history = $found['all'];
        $message = $found['message'];

        $oldText = $message['text'] ?? null;
        $oldMedia = $message['media'] ?? [];
        $msgDate = $message['date'] ?? 'Aniqlanmadi!';

        $textChanged = ($newText !== $oldText);
        
        $mediaChanged = false;
        if (count($newMedia) !== count($oldMedia)) {
            $mediaChanged = true;
        } else {
            foreach ($newMedia as $i => $nm) {
                if ($nm['file_id'] !== ($oldMedia[$i]['file_id'] ?? '') || $nm['type'] !== ($oldMedia[$i]['type'] ?? '')) {
                    $mediaChanged = true;
                    break;
                }
            }
        }

        if (!$textChanged && !$mediaChanged) return;

        $history[$index]['edit_message'][] = [
            'old_message' => $oldText,
            'new_message' => $newText,
            'old_media' => $oldMedia,
            'new_media' => $newMedia,
            'edit_date' => $editDate,
        ];
        
        if ($textChanged) $history[$index]['text'] = $newText;
        if ($mediaChanged) $history[$index]['media'] = $newMedia;
        $history[$index]['edit_date'] = $editDate;

        $this->storage->saveHistory($editedChatId, $history);

        if ($editedFromId === $this->adminId) return;

        $username = $this->update->getEditedChatUsername();
        $firstName = $this->update->getEditedChatFirstName();
        $lastName = $this->update->getEditedChatLastName();
        $link = $username
            ? "<a href='https://t.me/{$username}'>{$firstName} {$lastName}</a>"
            : "<a href='tg://user?id={$editedChatId}'>{$firstName} {$lastName}</a>";

        $fromUsername = $this->update->getEditedFromUsername();
        $fromFirstName = $this->update->getEditedFromFirstName();
        $fromLastName = $this->update->getEditedFromLastName();
        $senderLink = $fromUsername
            ? "<a href='https://t.me/{$fromUsername}'>{$fromFirstName} {$fromLastName}</a>"
            : ($editedFromId ? "<a href='tg://user?id={$editedFromId}'>{$fromFirstName} {$fromLastName}</a>" : "<i>Aniqlanmadi</i>");

        $oldTextSafe = htmlspecialchars($oldText ?? '');
        $newTextSafe = htmlspecialchars($newText ?? '');

        $html = "<table bordered striped>";
        $html .= "<tr><td><b>Holat:</b></td><td>Tahrirlandi</td></tr>";
        $html .= "<tr><td><b>Suhbat:</b></td><td>$link</td></tr>";
        $html .= "<tr><td><b>Yuboruvchi:</b></td><td>$senderLink</td></tr>";

        $replyToId = $found['message']['reply_to_message_id'] ?? null;
        if ($replyToId) {
            $repliedMsg = $this->storage->findMessageInHistory($editedChatId, $replyToId);
            if ($repliedMsg) {
                $hasReplyMedia = !empty($repliedMsg['message']['media']);
                $replyLabel = $hasReplyMedia ? "Javoblangan sarlavha" : "Javoblangan matn";
                
                if (!empty($repliedMsg['message']['text'])) {
                    $repliedText = $repliedMsg['message']['text'];
                    $repliedTextSafe = htmlspecialchars(mb_substr($repliedText, 0, 50) . (mb_strlen($repliedText) > 50 ? '...' : ''));
                } elseif ($hasReplyMedia) {
                    $repliedTextSafe = "Sarlavhasiz media";
                    $replyLabel = "Javoblangan media";
                } else {
                    $repliedTextSafe = "Noma'lum xabar";
                    $replyLabel = "Javob";
                }
                $html .= "<tr><td><b>{$replyLabel}:</b></td><td>{$repliedTextSafe}</td></tr>";
            }
        }

        $html .= "<tr><td><b>Yuborilgan vaqti:</b></td><td>$msgDate</td></tr>";
        $html .= "<tr><td><b>Tahrirlangan vaqti:</b></td><td>$editDate</td></tr>";

        if ($textChanged) {
            $hasMedia = !empty($oldMedia) || !empty($newMedia);
            $textLabel = $hasMedia ? "sarlavha" : "xabar matni";
            $html .= "<tr><td><b>Eski {$textLabel}:</b></td><td>" . ($oldTextSafe ?: "Yo'q") . "</td></tr>";
            $html .= "<tr><td><b>Yangi {$textLabel}:</b></td><td>" . ($newTextSafe ?: "Yo'q") . "</td></tr>";
        }

        if ($mediaChanged) {
            $html .= "<tr><td><b>Media:</b></td><td>O'zgartirilgan (Quyida yangi media)</td></tr>";
        }

        $html .= "</table>";

        $this->api->sendRichMessage($this->adminId, ['html' => $html]);

        if ($mediaChanged || (!empty($newMedia) && empty($oldMedia))) {
            $notifyMedia = !empty($newMedia) ? $newMedia : $oldMedia;
            if (!empty($notifyMedia)) {
                $file = $notifyMedia[0];
                $fid = $file['file_id'];
                $opts = ['caption' => "<tg-emoji emoji-id='5397782960512444700'>📌</tg-emoji> <b>Yangi o'rnatilgan media</b>", 'parse_mode' => 'html'];

                switch ($file['type']) {
                    case 'photo': $this->api->sendPhoto($this->adminId, $fid, $opts); break;
                    case 'video': $this->api->sendVideo($this->adminId, $fid, $opts); break;
                    case 'audio': $this->api->sendAudio($this->adminId, $fid, $opts); break;
                    case 'voice': $this->api->sendVoice($this->adminId, $fid, $opts); break;
                    case 'document': $this->api->sendDocument($this->adminId, $fid, $opts); break;
                }
            }
        }
    }

    public function handleDeletedMessages(): void
    {
        if (!$this->storage->isEnabled('deleted-message')) return;

        $chatId = $this->update->getDeleteChatId();
        $messageIds = $this->update->getDeleteMessageIds();
        $username = $this->update->getDeleteChatUsername();
        $firstName = $this->update->getDeleteChatFirstName();
        $lastName = $this->update->getDeleteChatLastName();

        $chatLink = $username
            ? "<a href='https://t.me/{$username}'>{$firstName} {$lastName}</a>"
            : "<a href='tg://user?id={$chatId}'>{$firstName} {$lastName}</a>";

        $history = $this->storage->getHistory($chatId);
        $deletedMessages = [];

        foreach ($messageIds as $msgId) {
            foreach ($history as $msg) {
                if (($msg['message_id'] ?? null) == $msgId) {
                    $deletedMessages[] = $msg;
                    break;
                }
            }
        }

        if (empty($deletedMessages)) return;

        foreach ($deletedMessages as $msg) {
            $this->sendDeletedMessageToAdmin($msg, $chatLink);
        }
    }

    private function sendDeletedMessageToAdmin(array $msg, string $chatLink): void
    {
        $userFirst = $msg['first_name'] ?? null;
        $userLast = $msg['last_name'] ?? null;
        $userId = $msg['user_id'] ?? null;
        $username = $msg['username'] ?? null;
        $textSafe = htmlspecialchars($msg['text'] ?? '');
        $date = $msg['date'] ?? 'Aniqlanmadi!';
        $editDate = $msg['edit_date'] ?? 'Tahrirlanmagan!';
        $media = $msg['media'] ?? [];

        $senderLink = $username
            ? "<a href='https://t.me/{$username}'>{$userFirst} {$userLast}</a>"
            : ($userId ? "<a href='tg://user?id={$userId}'>{$userFirst} {$userLast}</a>" : 'Aniqlanmadi');

        $html = "<table bordered striped>";
        $html .= "<tr><td><b>Holat:</b></td><td>O'chirildi</td></tr>";
        $html .= "<tr><td><b>Suhbat:</b></td><td>$chatLink</td></tr>";
        $html .= "<tr><td><b>Yuboruvchi:</b></td><td>$senderLink</td></tr>";

        $chatId = $this->update->getDeleteChatId();
        $replyToId = $msg['reply_to_message_id'] ?? null;
        if ($replyToId) {
            $repliedMsg = $this->storage->findMessageInHistory($chatId, $replyToId);
            if ($repliedMsg) {
                $hasReplyMedia = !empty($repliedMsg['message']['media']);
                $replyLabel = $hasReplyMedia ? "Javoblangan sarlavha" : "Javoblangan matn";
                
                if (!empty($repliedMsg['message']['text'])) {
                    $repliedText = $repliedMsg['message']['text'];
                    $repliedTextSafe = htmlspecialchars(mb_substr($repliedText, 0, 50) . (mb_strlen($repliedText) > 50 ? '...' : ''));
                } elseif ($hasReplyMedia) {
                    $repliedTextSafe = "Sarlavhasiz media";
                    $replyLabel = "Javoblangan media";
                } else {
                    $repliedTextSafe = "Noma'lum xabar";
                    $replyLabel = "Javob";
                }
                $html .= "<tr><td><b>{$replyLabel}:</b></td><td>{$repliedTextSafe}</td></tr>";
            }
        }

        $html .= "<tr><td><b>Yuborilgan vaqti:</b></td><td>$date</td></tr>";
        if ($editDate !== 'Tahrirlanmagan!') {
            $html .= "<tr><td><b>Tahrirlangan vaqti:</b></td><td>$editDate</td></tr>";
        }
        
        $textLabel = !empty($media) ? "sarlavha" : "matn";
        
        if ($textSafe !== '') {
            $html .= "<tr><td><b>O'chirilgan {$textLabel}:</b></td><td>$textSafe</td></tr>";
        } else {
            $html .= "<tr><td><b>O'chirilgan {$textLabel}:</b></td><td>" . ucfirst($textLabel) . " mavjud emas</td></tr>";
        }

        if (!empty($media)) {
            $html .= "<tr><td><b>Media:</b></td><td>O'chirilgan (Quyida media)</td></tr>";
        }

        $html .= "</table>";

        $this->api->sendRichMessage($this->adminId, ['html' => $html]);

        if (!empty($media)) {
            foreach ($media as $file) {
                $fid = $file['file_id'];
                $opts = ['caption' => "<tg-emoji emoji-id='5397782960512444700'>📌</tg-emoji> <b>O'chirilgan media fayl</b>", 'parse_mode' => 'html'];

                switch ($file['type']) {
                    case 'photo': $this->api->sendPhoto($this->adminId, $fid, $opts); break;
                    case 'video': $this->api->sendVideo($this->adminId, $fid, $opts); break;
                    case 'audio': $this->api->sendAudio($this->adminId, $fid, $opts); break;
                    case 'voice': $this->api->sendVoice($this->adminId, $fid, $opts); break;
                    case 'document': $this->api->sendDocument($this->adminId, $fid, $opts); break;
                    case 'video_note': $this->api->sendVideoNote($this->adminId, $fid); break;
                    case 'sticker': $this->api->sendSticker($this->adminId, $fid); break;
                }
            }
        }
    }
}