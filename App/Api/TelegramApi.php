<?php

namespace App\Api;

class TelegramApi
{
    private string $token;
    private string $baseUrl;

    public function __construct(string $token)
    {
        $this->token = $token;
        $this->baseUrl = "https://api.telegram.org/bot{$token}/";
    }

    public function call(string $method, array $params = []): ?object
    {
        $url = $this->baseUrl . $method;

        $ch = curl_init();
        
        $hasFile = false;
        foreach ($params as $param) {
            if ($param instanceof \CURLFile) {
                $hasFile = true;
                break;
            }
        }

        if ($hasFile) {
            $options = [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $params,
            ];
        } else {
            $options = [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => json_encode($params),
            ];
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);

        if (curl_error($ch)) {
            error_log('[TelegramApi] cURL xatolik: ' . curl_error($ch));
            curl_close($ch);
            return null;
        }

        curl_close($ch);
        $resObj = json_decode($response);
        if ($resObj && empty($resObj->ok)) {
            error_log("[TelegramApi] API Error: " . $response . " | Method: " . $method . " | Params: " . json_encode($params));
        }
        return $resObj;
    }

    public function getMe(): ?object
    {
        return $this->call('getMe');
    }

    public function getWebhookInfo(): ?object
    {
        return $this->call('getWebhookInfo');
    }

    public function getFile(string $fileId): ?object
    {
        return $this->call('getFile', ['file_id' => $fileId]);
    }

    public function getChat(int|string $chatId): ?object
    {
        return $this->call('getChat', ['chat_id' => $chatId]);
    }

    public function sendMessage(int|string $chatId, string $text, array $extra = []): ?object
    {
        return $this->call('sendMessage', array_merge([
            'chat_id' => $chatId,
            'text' => $text,
        ], $extra));
    }

    public function sendMessageDraft(int|string $chatId, int $draftId, string $text = '', array $extra = []): ?object
    {
        return $this->call('sendMessageDraft', array_merge([
            'chat_id' => $chatId,
            'draft_id' => $draftId,
            'text' => $text,
        ], $extra));
    }

    public function sendPhoto(int|string $chatId, mixed $photo, array $extra = []): ?object
    {
        return $this->call('sendPhoto', array_merge([
            'chat_id' => $chatId,
            'photo' => $photo,
        ], $extra));
    }

    public function sendVideo(int|string $chatId, mixed $video, array $extra = []): ?object
    {
        return $this->call('sendVideo', array_merge([
            'chat_id' => $chatId,
            'video' => $video,
        ], $extra));
    }

    public function sendVoice(int|string $chatId, mixed $voice, array $extra = []): ?object
    {
        return $this->call('sendVoice', array_merge([
            'chat_id' => $chatId,
            'voice' => $voice,
        ], $extra));
    }

    public function sendAudio(int|string $chatId, mixed $audio, array $extra = []): ?object
    {
        return $this->call('sendAudio', array_merge([
            'chat_id' => $chatId,
            'audio' => $audio,
        ], $extra));
    }

    public function sendDocument(int|string $chatId, mixed $document, array $extra = []): ?object
    {
        return $this->call('sendDocument', array_merge([
            'chat_id' => $chatId,
            'document' => $document,
        ], $extra));
    }

    public function sendVideoNote(int|string $chatId, mixed $videoNote, array $extra = []): ?object
    {
        return $this->call('sendVideoNote', array_merge([
            'chat_id' => $chatId,
            'video_note' => $videoNote,
        ], $extra));
    }

    public function sendSticker(int|string $chatId, string $sticker, array $extra = []): ?object
    {
        return $this->call('sendSticker', array_merge([
            'chat_id' => $chatId,
            'sticker' => $sticker,
        ], $extra));
    }

    public function sendAnimation(int|string $chatId, mixed $animation, array $extra = []): ?object
    {
        return $this->call('sendAnimation', array_merge([
            'chat_id' => $chatId,
            'animation' => $animation,
        ], $extra));
    }

    public function sendPoll(int|string $chatId, array $params): ?object
    {
        return $this->call('sendPoll', array_merge(['chat_id' => $chatId], $params));
    }

    public function sendMediaGroup(int|string $chatId, array $media, array $extra = []): ?object
    {
        return $this->call('sendMediaGroup', array_merge([
            'chat_id' => $chatId,
            'media' => json_encode($media),
        ], $extra));
    }

    public function sendChatAction(int|string $chatId, string $action, array $extra = []): ?object
    {
        return $this->call('sendChatAction', array_merge([
            'chat_id' => $chatId,
            'action' => $action,
        ], $extra));
    }

    public function editMessageText(int|string $chatId, int $messageId, string $text, array $extra = []): ?object
    {
        return $this->call('editMessageText', array_merge([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
        ], $extra));
    }

    public function editMessageReplyMarkup(int|string $chatId, int $messageId, string $replyMarkup): ?object
    {
        return $this->call('editMessageReplyMarkup', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'reply_markup' => $replyMarkup,
        ]);
    }

    public function answerCallbackQuery(string $queryId, string $text = '', bool $showAlert = false): ?object
    {
        return $this->call('answerCallbackQuery', [
            'callback_query_id' => $queryId,
            'text' => $text,
            'show_alert' => $showAlert,
        ]);
    }

    public function downloadFile(string $fileId, string $ext, string $saveDir = 'data/media'): ?string
    {
        $response = $this->getFile($fileId);

        if (!$response?->ok) {
            return null;
        }

        $remotePath = $response->result->file_path;
        $localPath = rtrim($saveDir, '/') . '/' . time() . $ext;
        $url = "https://api.telegram.org/file/bot{$this->token}/{$remotePath}";

        $fp = fopen($localPath, 'wb');
        if (!$fp) {
            error_log("[TelegramApi] downloadFile: fopen muvaffaqiyatsiz: $localPath");
            return null;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        curl_exec($ch);
        curl_close($ch);
        fclose($fp);

        return file_exists($localPath) ? $localPath : null;
    }

    public function editBusinessMessage(
        string     $businessConnectionId,
        int|string $chatId,
        int        $messageId,
        string     $text,
        array      $extra = []
    ): ?object
    {
        return $this->call('editMessageText', array_merge([
            'business_connection_id' => $businessConnectionId,
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
        ], $extra));
    }

    public function sendBusinessChatAction(
        string     $businessConnectionId,
        int|string $chatId,
        string     $action
    ): ?object
    {
        return $this->call('sendChatAction', [
            'business_connection_id' => $businessConnectionId,
            'chat_id' => $chatId,
            'action' => $action,
        ]);
    }

    public function sendBusinessMessage(
        string     $businessConnectionId,
        int|string $chatId,
        string     $text,
        array      $extra = []
    ): ?object
    {
        return $this->call('sendMessage', array_merge([
            'business_connection_id' => $businessConnectionId,
            'chat_id' => $chatId,
            'text' => $text,
        ], $extra));
    }

    public function sendBusinessMessageDraft(
        string     $businessConnectionId,
        int|string $chatId,
        int        $draftId,
        string     $text = '',
        array      $extra = []
    ): ?object
    {
        return $this->call('sendMessageDraft', array_merge([
            'business_connection_id' => $businessConnectionId,
            'chat_id' => $chatId,
            'draft_id' => $draftId,
            'text' => $text,
        ], $extra));
    }

    public function isBusinessUpdatesAllowed(): bool
    {
        $response = $this->getWebhookInfo();

        if (!$response?->ok) {
            return false;
        }

        $allowed = $response->result->allowed_updates ?? [];

        $required = [
            'business_connection',
            'business_message',
            'edited_business_message',
            'deleted_business_messages',
        ];

        foreach ($required as $type) {
            if (!in_array($type, $allowed)) {
                return false;
            }
        }

        return true;
    }

    public function deleteMessage(int|string $chatId, int $messageId): ?object
    {
        return $this->call('deleteMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ]);
    }

    public function sendRichMessage(int|string $chatId, array $richMessage, array $extra = []): ?object
    {
        return $this->call('sendRichMessage', array_merge([
            'chat_id' => $chatId,
            'rich_message' => $richMessage,
        ], $extra));
    }
}