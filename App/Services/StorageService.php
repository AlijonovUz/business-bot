<?php

namespace App\Services;

class StorageService
{

    public function getStep(int|string $chatId): string
    {
        $path = "storage/step/{$chatId}.txt";
        return file_exists($path) ? trim(file_get_contents($path)) : '';
    }

    public function setStep(int|string $chatId, string $value): void
    {
        if (!is_dir('storage/step')) mkdir('storage/step', 0777, true);
        file_put_contents("storage/step/{$chatId}.txt", $value, LOCK_EX);
    }

    public function clearStep(int|string $chatId): void
    {
        $path = "storage/step/{$chatId}.txt";
        if (file_exists($path)) unlink($path);
    }

    private array $defaultSettings = [
        'calculator' => 'off',
        'animation' => 'off',
        'auto-answer' => 'off',
        'currency' => 'off',
        'timed-media' => 'off',
        'timed-command' => '',
        'edited-message' => 'off',
        'deleted-message' => 'off',
    ];

    public function initSettings(): void
    {
        if (!is_dir('storage/data')) mkdir('storage/data', 0777, true);

        foreach ($this->defaultSettings as $name => $default) {
            $path = "storage/data/{$name}.txt";
            if (!file_exists($path)) {
                file_put_contents($path, $default, LOCK_EX);
            }
        }
    }

    public function getSetting(string $name): string
    {
        $path = "storage/data/{$name}.txt";
        return file_exists($path) ? trim(file_get_contents($path)) : '';
    }

    public function setSetting(string $name, string $value): void
    {
        file_put_contents("storage/data/{$name}.txt", $value, LOCK_EX);
    }

    public function isEnabled(string $name): bool
    {
        return $this->getSetting($name) === 'on';
    }

    public function readJson(string $path): array
    {
        if (!file_exists($path)) return [];
        $content = file_get_contents($path);
        return json_decode($content, true) ?? [];
    }

    public function writeJson(string $path, array $data): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    public function getWords(): array
    {
        return $this->readJson('storage/data/words.json');
    }

    public function saveWords(array $words): void
    {
        $this->writeJson('storage/data/words.json', $words);
    }

    public function getHistory(int|string $chatId): array
    {
        return $this->readJson("storage/data/history/{$chatId}.json");
    }

    public function saveHistory(int|string $chatId, array $messages): void
    {
        $this->writeJson("storage/data/history/{$chatId}.json", $messages);
    }

    public function findMessageInHistory(int|string $chatId, int $messageId): ?array
    {
        $history = $this->getHistory($chatId);

        foreach ($history as $index => $message) {
            if (($message['message_id'] ?? null) == $messageId) {
                return ['index' => $index, 'message' => $message, 'all' => $history];
            }
        }

        return null;
    }

    public function clearMediaFolder(string $path = 'storage/data/media'): void
    {
        if (!is_dir($path)) return;

        foreach (glob($path . '/*') as $file) {
            if (is_file($file)) unlink($file);
        }
    }

    public function ensureMediaDir(string $path = 'storage/data/media'): void
    {
        if (!is_dir($path)) mkdir($path, 0777, true);
    }
}
