<?php

return [
    'token'          => $_ENV['BOT_TOKEN'] ?? '',
    'admin_id'       => (int)($_ENV['ADMIN_ID'] ?? 0),
    'admin_user'     => $_ENV['ADMIN_USER'] ?? '',
    'bot_name'       => $_ENV['BOT_NAME'] ?? '',
    'timezone'       => $_ENV['TIMEZONE'] ?? 'Asia/Tashkent',
    'encryption_key' => $_ENV['ENCRYPTION_KEY'] ?? '',
    'db'             => [
        'driver'      => $_ENV['DB_DRIVER'] ?? 'sqlite',
        'sqlite_path' => $_ENV['DB_SQLITE_PATH'] ?? 'storage/database.sqlite',
        'host'        => $_ENV['DB_HOST'] ?? '127.0.0.1',
        'port'        => !empty($_ENV['DB_PORT']) ? (int)$_ENV['DB_PORT'] : (in_array(strtolower($_ENV['DB_DRIVER'] ?? ''), ['pgsql', 'postgres', 'postgresql']) ? 5432 : 3306),
        'database'    => $_ENV['DB_NAME'] ?? '',
        'username'    => $_ENV['DB_USER'] ?? '',
        'password'    => $_ENV['DB_PASS'] ?? '',
    ],
];