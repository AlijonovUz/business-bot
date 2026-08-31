<?php

return [
    'token'      => $_ENV['BOT_TOKEN'] ?? '',
    'admin_id'   => (int)($_ENV['ADMIN_ID'] ?? 0),
    'admin_user' => $_ENV['ADMIN_USER'] ?? '',
    'bot_name'   => $_ENV['BOT_NAME'] ?? '',
    'timezone'   => $_ENV['TIMEZONE'] ?? '',
];