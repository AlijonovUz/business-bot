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
            'reply_data' => $update->getBusinessReplyData(),
        ];

        $this->storage->saveMessage($chatId, $newEntry);
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

        $editHistory = $message['edit_message'] ?? [];
        $editHistory[] = [
            'old_message' => $oldText,
            'new_message' => $newText,
            'old_media' => $oldMedia,
            'new_media' => $newMedia,
            'edit_date' => $editDate,
        ];

        $this->storage->updateMessageInHistory(
            $editedChatId,
            $editedMessageId,
            $textChanged ? $newText : $oldText,
            $mediaChanged ? $newMedia : $oldMedia,
            $editDate,
            $editHistory
        );

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

        $html .= $this->formatReplyRow($message, $editedChatId);

        $html .= "<tr><td><b>Yuborilgan vaqti:</b></td><td>$msgDate</td></tr>";
        $html .= "<tr><td><b>Tahrirlangan vaqti:</b></td><td>$editDate</td></tr>";

        if ($textChanged) {
            $hasMedia = !empty($oldMedia) || !empty($newMedia);
            $textLabel = $hasMedia ? "sarlavha" : "xabar matni";
            $html .= "<tr><td><b>Eski {$textLabel}:</b></td><td>" . ($oldTextSafe ?: "Yo'q") . "</td></tr>";
            $html .= "<tr><td><b>Yangi {$textLabel}:</b></td><td>" . ($newTextSafe ?: "Yo'q") . "</td></tr>";
        }

        if ($mediaChanged) {
            if (!empty($oldMedia) && !empty($newMedia)) {
                $html .= "<tr><td><b>Media:</b></td><td>O'zgartirildi (Eski va yangi media quyida)</td></tr>";
            } elseif (!empty($oldMedia) && empty($newMedia)) {
                $html .= "<tr><td><b>Media:</b></td><td>Olib tashlandi (Eski media quyida)</td></tr>";
            } elseif (empty($oldMedia) && !empty($newMedia)) {
                $html .= "<tr><td><b>Media:</b></td><td>Qo'shildi (Yangi media quyida)</td></tr>";
            }
        }

        $html .= "</table>";

        $this->api->sendRichMessage($this->adminId, ['html' => $html]);

        if ($mediaChanged) {
            if (!empty($oldMedia)) {
                foreach ($oldMedia as $file) {
                    $this->sendMediaFile(
                        $this->adminId,
                        $file,
                        "<tg-emoji emoji-id='5465665476971471368'>🗑</tg-emoji> <b>Eski (tahrirlanishdan oldingi) media</b>"
                    );
                }
            }

            if (!empty($newMedia)) {
                foreach ($newMedia as $file) {
                    $this->sendMediaFile(
                        $this->adminId,
                        $file,
                        "<tg-emoji emoji-id='5397782960512444700'>📌</tg-emoji> <b>Yangi o'rnatilgan media</b>"
                    );
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

        $deletedMessages = $this->storage->findMessagesInHistory($chatId, $messageIds);

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
        $html .= $this->formatReplyRow($msg, $chatId);

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
                $this->sendMediaFile(
                    $this->adminId,
                    $file,
                    "<tg-emoji emoji-id='5397782960512444700'>📌</tg-emoji> <b>O'chirilgan media fayl</b>"
                );
            }
        }
    }

    private function formatReplyRow(array $msg, int|string $chatId): string
    {
        $replyData = $msg['reply_data'] ?? null;
        $replyToId = $msg['reply_to_message_id'] ?? null;

        if (!empty($replyData) && is_array($replyData)) {
            $hasMedia = !empty($replyData['media_type']);
            $isQuote = $replyData['is_quote'] ?? false;
            $replyLabel = $isQuote ? "Iqtibos (Quote)" : ($hasMedia ? "Javoblangan sarlavha" : "Javoblangan matn");

            if (!empty($replyData['text'])) {
                $repliedTextSafe = htmlspecialchars(mb_substr($replyData['text'], 0, 80) . (mb_strlen($replyData['text']) > 80 ? '...' : ''));
            } elseif ($hasMedia) {
                $repliedTextSafe = "Sarlavhasiz media (" . $replyData['media_type'] . ")";
                $replyLabel = "Javoblangan media";
            } else {
                $repliedTextSafe = "Xabar";
            }

            if (!empty($replyData['from_name'])) {
                $author = htmlspecialchars($replyData['from_name']);
                return "<tr><td><b>{$replyLabel}:</b></td><td><i>[{$author}]</i> {$repliedTextSafe}</td></tr>";
            }
            return "<tr><td><b>{$replyLabel}:</b></td><td>{$repliedTextSafe}</td></tr>";
        }

        if (!empty($replyToId)) {
            $repliedMsg = $this->storage->findMessageInHistory($chatId, (int)$replyToId);
            if ($repliedMsg && !empty($repliedMsg['message'])) {
                $repliedData = $repliedMsg['message'];
                $hasReplyMedia = !empty($repliedData['media']);
                $replyLabel = $hasReplyMedia ? "Javoblangan sarlavha" : "Javoblangan matn";

                if (!empty($repliedData['text'])) {
                    $repliedText = $repliedData['text'];
                    $repliedTextSafe = htmlspecialchars(mb_substr($repliedText, 0, 80) . (mb_strlen($repliedText) > 80 ? '...' : ''));
                } elseif ($hasReplyMedia) {
                    $repliedTextSafe = "Sarlavhasiz media";
                    $replyLabel = "Javoblangan media";
                } else {
                    $repliedTextSafe = "Noma'lum xabar";
                    $replyLabel = "Javob";
                }

                $author = trim(($repliedData['first_name'] ?? '') . ' ' . ($repliedData['last_name'] ?? ''));
                if (!empty($author)) {
                    $authorSafe = htmlspecialchars($author);
                    return "<tr><td><b>{$replyLabel}:</b></td><td><i>[{$authorSafe}]</i> {$repliedTextSafe}</td></tr>";
                }
                return "<tr><td><b>{$replyLabel}:</b></td><td>{$repliedTextSafe}</td></tr>";
            }
        }

        return '';
    }

    private function sendMediaFile(int|string $targetChatId, array $file, string $caption): void
    {
        $fid = $file['file_id'] ?? null;
        if (!$fid) return;

        $type = $file['type'] ?? 'document';
        $opts = ['caption' => $caption, 'parse_mode' => 'html'];

        switch ($type) {
            case 'photo': $this->api->sendPhoto($targetChatId, $fid, $opts); break;
            case 'video': $this->api->sendVideo($targetChatId, $fid, $opts); break;
            case 'audio': $this->api->sendAudio($targetChatId, $fid, $opts); break;
            case 'voice': $this->api->sendVoice($targetChatId, $fid, $opts); break;
            case 'document': $this->api->sendDocument($targetChatId, $fid, $opts); break;
            case 'video_note': $this->api->sendVideoNote($targetChatId, $fid); break;
            case 'sticker': $this->api->sendSticker($targetChatId, $fid); break;
            case 'animation': $this->api->sendAnimation($targetChatId, $fid, $opts); break;
        }
    }
}