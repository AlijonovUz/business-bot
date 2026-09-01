<?php

namespace App\Api;

class Update
{
    private object $data;

    public function __construct(object $data)
    {
        $this->data = $data;
    }

    public static function fromInput(): self
    {
        $raw = file_get_contents('php://input');
        return new self(json_decode($raw));
    }

    public function hasMessage(): bool
    {
        return isset($this->data->message);
    }

    public function hasCallbackQuery(): bool
    {
        return isset($this->data->callback_query);
    }

    public function hasBusinessMessage(): bool
    {
        return isset($this->data->business_message);
    }

    public function hasEditedMessage(): bool
    {
        return isset($this->data->edited_message);
    }

    public function hasBusinessConnection(): bool
    {
        return isset($this->data->business_connection);
    }

    public function hasEditedBusinessMessage(): bool
    {
        return isset($this->data->edited_business_message);
    }

    public function hasDeletedBusinessMessages(): bool
    {
        return isset($this->data->deleted_business_messages);
    }

    public function hasInlineQuery(): bool
    {
        return isset($this->data->inline_query);
    }

    public function getMessage(): ?object
    {
        return $this->data->message ?? null;
    }

    public function getChatId(): int|string|null
    {
        return $this->data->message->chat->id   
            ?? $this->data->edited_message->chat->id 
            ?? $this->data->business_message->chat->id 
            ?? $this->data->edited_business_message->chat->id 
            ?? $this->getCallbackMessageChatId() 
            ?? null;
    }

    public function getMessageId(): ?int
    {
        return $this->data->message->message_id ?? null;
    }

    public function getText(): ?string
    {
        return $this->data->message->text ?? $this->data->business_message->text ?? null;
    }

    public function getFromId(): ?int
    {
        return $this->data->message->from->id ?? null;
    }

    public function getFirstName(): ?string
    {
        return $this->data->message->from->first_name ?? null;
    }

    public function getLastName(): ?string
    {
        return $this->data->message->from->last_name ?? null;
    }

    public function getChatType(): ?string
    {
        return $this->data->message->chat->type ?? null;
    }

    public function getCallbackQuery(): ?object
    {
        return $this->data->callback_query ?? null;
    }

    public function getCallbackData(): ?string
    {
        return $this->data->callback_query->data ?? null;
    }

    public function getCallbackQueryId(): ?string
    {
        return $this->data->callback_query->id ?? null;
    }

    public function getCallbackFromId(): ?int
    {
        return $this->data->callback_query->from->id ?? null;
    }

    public function getCallbackFromName(): ?string
    {
        return $this->data->callback_query->from->first_name ?? null;
    }

    public function getCallbackFromUsername(): ?string
    {
        return $this->data->callback_query->from->username ?? null;
    }

    public function getCallbackFromSurname(): ?string
    {
        return $this->data->callback_query->from->last_name ?? null;
    }

    public function getCallbackMessageChatId(): int|string|null
    {
        return $this->data->callback_query->message->chat->id ?? null;
    }

    public function getCallbackMessageId(): ?int
    {
        return $this->data->callback_query->message->message_id ?? null;
    }

    public function getCallbackBusinessId(): ?string
    {
        return $this->data->callback_query->message->business_connection_id ?? null;
    }

    public function getBusinessMessage(): ?object
    {
        return $this->data->business_message ?? null;
    }

    public function getBusinessText(): ?string
    {
        return $this->data->business_message->text ?? null;
    }

    public function getBusinessCaption(): ?string
    {
        return $this->data->business_message->caption ?? null;
    }

    public function getBusinessConnectionId(): ?string
    {
        return $this->data->business_message->business_connection_id ?? null;
    }

    public function getBusinessChatId(): int|string|null
    {
        return $this->data->business_message->chat->id ?? null;
    }

    public function getBusinessFromId(): ?int
    {
        return $this->data->business_message->from->id ?? null;
    }

    public function getBusinessMessageId(): ?int
    {
        return $this->data->business_message->message_id ?? null;
    }

    public function getBusinessFromName(): ?string
    {
        return $this->data->business_message->from->first_name ?? null;
    }

    public function getBusinessFromLastName(): ?string
    {
        return $this->data->business_message->from->last_name ?? null;
    }

    public function getBusinessFromUsername(): ?string
    {
        return $this->data->business_message->from->username ?? null;
    }

    public function getBusinessReplyToMessageId(): ?int
    {
        return $this->data->business_message->reply_to_message->message_id ?? null;
    }

    public function getBusinessDate(): ?int
    {
        return $this->data->business_message->date ?? null;
    }

    public function getCaption(): ?string
    {
        return $this->data->message->caption ?? $this->data->business_message->caption ?? null;
    }

    public function getMediaItems(): array
    {
        $msg = $this->data->message ?? $this->data->business_message ?? null;
        $media = [];
        $caption = $this->getCaption();

        if (!$msg) return $media;

        if (isset($msg->photo)) {
            $media[] = ['type' => 'photo', 'file_id' => end($msg->photo)->file_id, 'caption' => $caption];
        }
        if (isset($msg->video)) {
            $media[] = ['type' => 'video', 'file_id' => $msg->video->file_id, 'caption' => $caption];
        }
        if (isset($msg->video_note)) {
            $media[] = ['type' => 'video_note', 'file_id' => $msg->video_note->file_id, 'caption' => null];
        }
        if (isset($msg->audio)) {
            $media[] = ['type' => 'audio', 'file_id' => $msg->audio->file_id, 'caption' => $caption, 'file_name' => $msg->audio->file_name ?? null];
        }
        if (isset($msg->voice)) {
            $media[] = ['type' => 'voice', 'file_id' => $msg->voice->file_id, 'caption' => $caption];
        }
        if (isset($msg->document)) {
            $media[] = ['type' => 'document', 'file_id' => $msg->document->file_id, 'caption' => $caption, 'file_name' => $msg->document->file_name ?? null];
        }
        if (isset($msg->sticker)) {
            $media[] = ['type' => 'sticker', 'file_id' => $msg->sticker->file_id, 'caption' => null];
        }
        if (isset($msg->animation)) {
            $media[] = ['type' => 'animation', 'file_id' => $msg->animation->file_id, 'caption' => $caption];
        }

        return $media;
    }

    public function getBusinessReplyToMessage(): ?object
    {
        return $this->data->business_message->reply_to_message ?? null;
    }

    public function getBusinessReplyData(): ?array
    {
        $msg = $this->data->business_message ?? $this->data->message ?? null;
        if (!$msg) return null;

        $reply = $msg->reply_to_message ?? null;
        $quote = $msg->quote->text ?? null;
        $external = $msg->external_reply ?? null;

        if (!$reply && !$quote && !$external) {
            return null;
        }

        $text = $quote ?? ($reply->text ?? $reply->caption ?? ($external->quote->text ?? null));
        $mediaType = null;
        $fromName = '';

        if ($reply) {
            $firstName = $reply->from->first_name ?? '';
            $lastName = $reply->from->last_name ?? '';
            $fromName = trim("{$firstName} {$lastName}");

            if (isset($reply->photo)) $mediaType = 'photo';
            elseif (isset($reply->video)) $mediaType = 'video';
            elseif (isset($reply->voice)) $mediaType = 'voice';
            elseif (isset($reply->audio)) $mediaType = 'audio';
            elseif (isset($reply->document)) $mediaType = 'document';
            elseif (isset($reply->sticker)) $mediaType = 'sticker';
            elseif (isset($reply->video_note)) $mediaType = 'video_note';
            elseif (isset($reply->animation)) $mediaType = 'animation';
        }

        return [
            'message_id' => $reply->message_id ?? ($external->message_id ?? null),
            'from_name' => $fromName,
            'text' => $text,
            'media_type' => $mediaType,
            'is_quote' => ($quote !== null)
        ];
    }

    public function getEditedBusinessReplyData(): ?array
    {
        $msg = $this->data->edited_business_message ?? null;
        if (!$msg) return null;

        $reply = $msg->reply_to_message ?? null;
        $quote = $msg->quote->text ?? null;
        $external = $msg->external_reply ?? null;

        if (!$reply && !$quote && !$external) {
            return null;
        }

        $text = $quote ?? ($reply->text ?? $reply->caption ?? ($external->quote->text ?? null));
        $mediaType = null;
        $fromName = '';

        if ($reply) {
            $firstName = $reply->from->first_name ?? '';
            $lastName = $reply->from->last_name ?? '';
            $fromName = trim("{$firstName} {$lastName}");

            if (isset($reply->photo)) $mediaType = 'photo';
            elseif (isset($reply->video)) $mediaType = 'video';
            elseif (isset($reply->voice)) $mediaType = 'voice';
            elseif (isset($reply->audio)) $mediaType = 'audio';
            elseif (isset($reply->document)) $mediaType = 'document';
            elseif (isset($reply->sticker)) $mediaType = 'sticker';
            elseif (isset($reply->video_note)) $mediaType = 'video_note';
            elseif (isset($reply->animation)) $mediaType = 'animation';
        }

        return [
            'message_id' => $reply->message_id ?? ($external->message_id ?? null),
            'from_name' => $fromName,
            'text' => $text,
            'media_type' => $mediaType,
            'is_quote' => ($quote !== null)
        ];
    }

    public function getBusinessConnection(): ?object
    {
        return $this->data->business_connection ?? null;
    }

    public function isBusinessConnectionEnabled(): bool
    {
        return $this->data->business_connection->is_enabled ?? false;
    }

    public function getBusinessConnectionUserId(): ?int
    {
        return $this->data->business_connection->user->id ?? null;
    }

    public function getEditedBusinessMessage(): ?object
    {
        return $this->data->edited_business_message ?? null;
    }

    public function getEditedBusinessId(): ?string
    {
        return $this->data->edited_business_message->business_connection_id ?? null;
    }

    public function getEditedMessageId(): ?int
    {
        return $this->data->edited_business_message->message_id ?? null;
    }

    public function getEditedChatId(): int|string|null
    {
        return $this->data->edited_business_message->chat->id ?? null;
    }

    public function getEditedFromId(): ?int
    {
        return $this->data->edited_business_message->from->id ?? null;
    }

    public function getEditedText(): ?string
    {
        return $this->data->edited_business_message->text ?? null;
    }

    public function getEditedCaption(): ?string
    {
        return $this->data->edited_business_message->caption ?? null;
    }

    public function getEditedMediaItems(): array
    {
        $msg = $this->data->edited_business_message ?? null;
        $media = [];
        $caption = $this->getEditedCaption();

        if (!$msg) return $media;

        if (isset($msg->photo)) {
            $media[] = ['type' => 'photo', 'file_id' => end($msg->photo)->file_id, 'caption' => $caption];
        }
        if (isset($msg->video)) {
            $media[] = ['type' => 'video', 'file_id' => $msg->video->file_id, 'caption' => $caption];
        }
        if (isset($msg->video_note)) {
            $media[] = ['type' => 'video_note', 'file_id' => $msg->video_note->file_id, 'caption' => null];
        }
        if (isset($msg->audio)) {
            $media[] = ['type' => 'audio', 'file_id' => $msg->audio->file_id, 'caption' => $caption];
        }
        if (isset($msg->voice)) {
            $media[] = ['type' => 'voice', 'file_id' => $msg->voice->file_id, 'caption' => $caption];
        }
        if (isset($msg->document)) {
            $media[] = ['type' => 'document', 'file_id' => $msg->document->file_id, 'caption' => $caption];
        }
        if (isset($msg->sticker)) {
            $media[] = ['type' => 'sticker', 'file_id' => $msg->sticker->file_id, 'caption' => null];
        }
        if (isset($msg->animation)) {
            $media[] = ['type' => 'animation', 'file_id' => $msg->animation->file_id, 'caption' => $caption];
        }

        return $media;
    }

    public function getEditedDate(): ?int
    {
        return $this->data->edited_business_message->edit_date ?? null;
    }

    public function getEditedChatUsername(): ?string
    {
        return $this->data->edited_business_message->chat->username ?? null;
    }

    public function getEditedChatFirstName(): ?string
    {
        return $this->data->edited_business_message->chat->first_name ?? null;
    }

    public function getEditedChatLastName(): ?string
    {
        return $this->data->edited_business_message->chat->last_name ?? null;
    }

    public function getEditedFromUsername(): ?string
    {
        return $this->data->edited_business_message->from->username ?? null;
    }

    public function getEditedFromFirstName(): ?string
    {
        return $this->data->edited_business_message->from->first_name ?? null;
    }

    public function getEditedFromLastName(): ?string
    {
        return $this->data->edited_business_message->from->last_name ?? null;
    }

    public function getDeletedBusinessMessages(): ?object
    {
        return $this->data->deleted_business_messages ?? null;
    }

    public function getDeleteBusinessId(): ?string
    {
        return $this->data->deleted_business_messages->business_connection_id ?? null;
    }

    public function getDeleteChatId(): int|string|null
    {
        return $this->data->deleted_business_messages->chat->id ?? null;
    }

    public function getDeleteMessageIds(): array
    {
        return $this->data->deleted_business_messages->message_ids ?? [];
    }

    public function getDeleteChatFirstName(): ?string
    {
        return $this->data->deleted_business_messages->chat->first_name ?? null;
    }

    public function getDeleteChatLastName(): ?string
    {
        return $this->data->deleted_business_messages->chat->last_name ?? null;
    }

    public function getDeleteChatUsername(): ?string
    {
        return $this->data->deleted_business_messages->chat->username ?? null;
    }

    public function getRaw(): object
    {
        return $this->data;
    }
}