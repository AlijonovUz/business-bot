<?php

namespace App\Services;

class KeyboardBuilder
{
    private array $rows = [];

    public function row(array ...$buttons): self
    {
        $this->rows[] = $buttons;
        return $this;
    }


    public function rowSingle(string $text, string $callbackData, ?string $iconEmojiId = null): self
    {
        $this->rows[] = [self::btn($text, $callbackData, $iconEmojiId)];
        return $this;
    }

    public static function btn(string $text, string $callbackData, ?string $iconEmojiId = null): array
    {
        $btn = ['text' => $text, 'callback_data' => $callbackData];
        if ($iconEmojiId !== null) {
            $btn['icon_custom_emoji_id'] = $iconEmojiId;
        }
        return $btn;
    }

    public static function urlBtn(string $text, string $url, ?string $iconEmojiId = null): array
    {
        $btn = ['text' => $text, 'url' => $url];
        if ($iconEmojiId !== null) {
            $btn['icon_custom_emoji_id'] = $iconEmojiId;
        }
        return $btn;
    }

    public function backButton(string $callbackData = 'settings'): self
    {
        return $this->rowSingle("◀️ Orqaga", $callbackData);
    }

    public function toggleButtons(string $current, string $prefix): self
    {
        $onText  = $current === 'on'  ? "Yoqilgan" :"Yoqish";
        $offText = $current === 'off' ? "O'chirilgan" : "O'chirish";

        $this->rows[] = [
            self::btn($onText,  "{$prefix}=on", '5206607081334906820'),
            self::btn($offText, "{$prefix}=off", '5210952531676504517'),
        ];

        return $this;
    }

    public function pagination(int $page, int $totalPages, string $prefix): self
    {
        $this->rows[] = [
            self::btn('⬅️', "{$prefix}&page=" . ($page - 1)),
            self::btn("{$page}/{$totalPages}", 'none'),
            self::btn('➡️', "{$prefix}&page=" . ($page + 1)),
        ];

        return $this;
    }

    public function toJson(): string
    {
        return json_encode(['inline_keyboard' => $this->rows]);
    }

    public function toArray(): array
    {
        return ['inline_keyboard' => $this->rows];
    }

    public static function make(): self
    {
        return new self();
    }
}