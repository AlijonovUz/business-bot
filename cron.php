<?php

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug.txt');
error_reporting(E_ALL);

if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (str_contains($line, '=')) {
            [$key, $val] = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val, " \t\n\r\0\x0B\"'");
            $_ENV[$key] = $val;
            putenv("{$key}={$val}");
        }
    }
}

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/App/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

$config = require __DIR__ . '/config.php';

date_default_timezone_set($config['timezone'] ?? 'Asia/Tashkent');

$storage = new \App\Services\StorageService($config['db'] ?? [], $config['encryption_key'] ?? '');
$api = new \App\Api\TelegramApi($config['token']);

$pending = $storage->getPendingStories();

foreach ($pending as $index => $story) {
    if (empty($story['scheduled_at'])) continue;

    if ($index > 0) {
        sleep(2);
    }

    $maxAttempts = 3;
    $result = null;
    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        $result = $api->postStoryWithMedia($story);
        if ($result && $result->ok) {
            break;
        }
        if ($attempt < $maxAttempts) {
            sleep(3);
        }
    }

    if ($result && $result->ok) {
        $tgStoryId = $result->result->id ?? null;
        $storage->updateStoryStatus((int)$story['id'], 'posted', $tgStoryId);
        $api->sendMessage($config['admin_id'],
            "<tg-emoji emoji-id='5206607081334906820'>✔️</tg-emoji> <b>Rejalashtirilgan hikoya yuklandi!</b>",
            ['parse_mode' => 'html']
        );
    } else {
        $storage->updateStoryStatus((int)$story['id'], 'failed');
        $desc = htmlspecialchars($result->description ?? 'Telegram API xatoligi yuz berdi');
        $api->sendMessage($config['admin_id'],
            "<tg-emoji emoji-id='5260293700088511294'>⛔️</tg-emoji> <b>Rejalashtirilgan hikoya yuklanmadi!</b>",
            ['parse_mode' => 'html']
        );
    }
}
