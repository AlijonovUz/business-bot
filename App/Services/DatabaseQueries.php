<?php

namespace App\Services;

class DatabaseQueries
{
    public static function getSchema(string $driver): string
    {
        return match ($driver) {
            'mysql' => "
                CREATE TABLE IF NOT EXISTS `settings` (
                    `key` VARCHAR(191) PRIMARY KEY,
                    `value` LONGTEXT NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE IF NOT EXISTS `steps` (
                    `chat_id` VARCHAR(191) PRIMARY KEY,
                    `step` TEXT NOT NULL,
                    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE IF NOT EXISTS `words` (
                    `keyword` VARCHAR(191) PRIMARY KEY,
                    `data` LONGTEXT NOT NULL,
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE IF NOT EXISTS `messages` (
                    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
                    `chat_id` VARCHAR(191) NOT NULL,
                    `message_id` BIGINT NOT NULL,
                    `user_id` VARCHAR(191) NULL,
                    `reply_to_message_id` BIGINT NULL,
                    `username` VARCHAR(191) NULL,
                    `first_name` VARCHAR(255) NULL,
                    `last_name` VARCHAR(255) NULL,
                    `text` LONGTEXT NULL,
                    `date` VARCHAR(191) NULL,
                    `edit_date` VARCHAR(191) NULL,
                    `edit_message` LONGTEXT NULL,
                    `media` LONGTEXT NULL,
                    `reply_data` LONGTEXT NULL,
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY `uk_chat_msg` (`chat_id`, `message_id`),
                    INDEX `idx_chat_id` (`chat_id`),
                    INDEX `idx_date` (`date`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE IF NOT EXISTS `stories` (
                    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
                    `story_id` BIGINT NULL,
                    `business_connection_id` VARCHAR(191) NOT NULL,
                    `file_id` TEXT NOT NULL,
                    `file_type` VARCHAR(20) NOT NULL DEFAULT 'photo',
                    `caption` LONGTEXT NULL,
                    `active_period` INT NOT NULL DEFAULT 86400,
                    `post_to_chat_page` TINYINT(1) NOT NULL DEFAULT 0,
                    `protect_content` TINYINT(1) NOT NULL DEFAULT 0,
                    `scheduled_at` BIGINT NULL,
                    `posted_at` BIGINT NULL,
                    `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX `idx_stories_status` (`status`),
                    INDEX `idx_stories_scheduled` (`scheduled_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ",
            'pgsql' => "
                CREATE TABLE IF NOT EXISTS settings (
                    key VARCHAR(191) PRIMARY KEY,
                    value TEXT NOT NULL
                );

                CREATE TABLE IF NOT EXISTS steps (
                    chat_id VARCHAR(191) PRIMARY KEY,
                    step TEXT NOT NULL,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                );

                CREATE TABLE IF NOT EXISTS words (
                    keyword VARCHAR(191) PRIMARY KEY,
                    data TEXT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                );

                CREATE TABLE IF NOT EXISTS messages (
                    id BIGSERIAL PRIMARY KEY,
                    chat_id VARCHAR(191) NOT NULL,
                    message_id BIGINT NOT NULL,
                    user_id VARCHAR(191) NULL,
                    reply_to_message_id BIGINT NULL,
                    username VARCHAR(191) NULL,
                    first_name VARCHAR(255) NULL,
                    last_name VARCHAR(255) NULL,
                    text TEXT NULL,
                    date VARCHAR(191) NULL,
                    edit_date VARCHAR(191) NULL,
                    edit_message TEXT NULL,
                    media TEXT NULL,
                    reply_data TEXT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    CONSTRAINT uk_chat_msg UNIQUE (chat_id, message_id)
                );

                CREATE INDEX IF NOT EXISTS idx_messages_chat_id ON messages(chat_id);
                CREATE INDEX IF NOT EXISTS idx_messages_date ON messages(date);

                CREATE TABLE IF NOT EXISTS stories (
                    id BIGSERIAL PRIMARY KEY,
                    story_id BIGINT NULL,
                    business_connection_id VARCHAR(191) NOT NULL,
                    file_id TEXT NOT NULL,
                    file_type VARCHAR(20) NOT NULL DEFAULT 'photo',
                    caption TEXT NULL,
                    active_period INT NOT NULL DEFAULT 86400,
                    post_to_chat_page SMALLINT NOT NULL DEFAULT 0,
                    protect_content SMALLINT NOT NULL DEFAULT 0,
                    scheduled_at BIGINT NULL,
                    posted_at BIGINT NULL,
                    status VARCHAR(20) NOT NULL DEFAULT 'pending',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                );

                CREATE INDEX IF NOT EXISTS idx_stories_status ON stories(status);
                CREATE INDEX IF NOT EXISTS idx_stories_scheduled ON stories(scheduled_at);
            ",
            default => "
                CREATE TABLE IF NOT EXISTS settings (
                    key TEXT PRIMARY KEY,
                    value TEXT NOT NULL
                );

                CREATE TABLE IF NOT EXISTS steps (
                    chat_id TEXT PRIMARY KEY,
                    step TEXT NOT NULL,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );

                CREATE TABLE IF NOT EXISTS words (
                    keyword TEXT PRIMARY KEY,
                    data TEXT NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );

                CREATE TABLE IF NOT EXISTS messages (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    chat_id TEXT NOT NULL,
                    message_id INTEGER NOT NULL,
                    user_id TEXT,
                    reply_to_message_id INTEGER,
                    username TEXT,
                    first_name TEXT,
                    last_name TEXT,
                    text TEXT,
                    date TEXT,
                    edit_date TEXT,
                    edit_message TEXT,
                    media TEXT,
                    reply_data TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(chat_id, message_id)
                );

                CREATE INDEX IF NOT EXISTS idx_messages_chat_id ON messages(chat_id);
                CREATE INDEX IF NOT EXISTS idx_messages_chat_message ON messages(chat_id, message_id);
                CREATE INDEX IF NOT EXISTS idx_messages_date ON messages(date);

                CREATE TABLE IF NOT EXISTS stories (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    story_id INTEGER NULL,
                    business_connection_id TEXT NOT NULL,
                    file_id TEXT NOT NULL,
                    file_type TEXT NOT NULL DEFAULT 'photo',
                    caption TEXT NULL,
                    active_period INTEGER NOT NULL DEFAULT 86400,
                    post_to_chat_page INTEGER NOT NULL DEFAULT 0,
                    protect_content INTEGER NOT NULL DEFAULT 0,
                    scheduled_at INTEGER NULL,
                    posted_at INTEGER NULL,
                    status TEXT NOT NULL DEFAULT 'pending',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );

                CREATE INDEX IF NOT EXISTS idx_stories_status ON stories(status);
                CREATE INDEX IF NOT EXISTS idx_stories_scheduled ON stories(scheduled_at);
            ",
        };
    }

    public static function getMigration(string $driver): string
    {
        return match ($driver) {
            'mysql' => "ALTER TABLE `messages` ADD COLUMN `reply_data` LONGTEXT NULL;",
            default => "ALTER TABLE messages ADD COLUMN reply_data TEXT NULL;",
        };
    }

    public static function getStepUpsert(string $driver): string
    {
        return match ($driver) {
            'mysql' => "
                INSERT INTO `steps` (`chat_id`, `step`, `updated_at`) VALUES (:chat_id, :step, NOW())
                ON DUPLICATE KEY UPDATE `step` = :step, `updated_at` = NOW()
            ",
            'pgsql' => "
                INSERT INTO steps (chat_id, step, updated_at) VALUES (:chat_id, :step, CURRENT_TIMESTAMP)
                ON CONFLICT (chat_id) DO UPDATE SET step = :step, updated_at = CURRENT_TIMESTAMP
            ",
            default => "
                INSERT INTO steps (chat_id, step, updated_at) VALUES (:chat_id, :step, datetime('now'))
                ON CONFLICT(chat_id) DO UPDATE SET step = :step, updated_at = datetime('now')
            ",
        };
    }

    public static function getSettingInit(string $driver): string
    {
        return match ($driver) {
            'mysql' => "INSERT IGNORE INTO `settings` (`key`, `value`) VALUES (:key, :value)",
            'pgsql' => "INSERT INTO settings (key, value) VALUES (:key, :value) ON CONFLICT (key) DO NOTHING",
            default => "INSERT OR IGNORE INTO settings (key, value) VALUES (:key, :value)",
        };
    }

    public static function getSettingUpsert(string $driver): string
    {
        return match ($driver) {
            'mysql' => "
                INSERT INTO `settings` (`key`, `value`) VALUES (:key, :value)
                ON DUPLICATE KEY UPDATE `value` = :value
            ",
            default => "
                INSERT INTO settings (key, value) VALUES (:key, :value)
                ON CONFLICT(key) DO UPDATE SET value = :value
            ",
        };
    }

    public static function getMessageUpsert(string $driver): string
    {
        return match ($driver) {
            'mysql' => "
                INSERT INTO `messages` (`chat_id`, `message_id`, `user_id`, `reply_to_message_id`, `username`, `first_name`, `last_name`, `text`, `date`, `edit_date`, `edit_message`, `media`, `reply_data`)
                VALUES (:chat_id, :message_id, :user_id, :reply_to_message_id, :username, :first_name, :last_name, :text, :date, :edit_date, :edit_message, :media, :reply_data)
                ON DUPLICATE KEY UPDATE
                    `user_id` = VALUES(`user_id`),
                    `reply_to_message_id` = VALUES(`reply_to_message_id`),
                    `username` = VALUES(`username`),
                    `first_name` = VALUES(`first_name`),
                    `last_name` = VALUES(`last_name`),
                    `text` = VALUES(`text`),
                    `date` = VALUES(`date`),
                    `edit_date` = VALUES(`edit_date`),
                    `edit_message` = VALUES(`edit_message`),
                    `media` = VALUES(`media`),
                    `reply_data` = VALUES(`reply_data`)
            ",
            default => "
                INSERT INTO messages (chat_id, message_id, user_id, reply_to_message_id, username, first_name, last_name, text, date, edit_date, edit_message, media, reply_data)
                VALUES (:chat_id, :message_id, :user_id, :reply_to_message_id, :username, :first_name, :last_name, :text, :date, :edit_date, :edit_message, :media, :reply_data)
                ON CONFLICT(chat_id, message_id) DO UPDATE SET
                    user_id = :user_id,
                    reply_to_message_id = :reply_to_message_id,
                    username = :username,
                    first_name = :first_name,
                    last_name = :last_name,
                    text = :text,
                    date = :date,
                    edit_date = :edit_date,
                    edit_message = :edit_message,
                    media = :media,
                    reply_data = :reply_data
            ",
        };
    }

    public static function getWordUpsert(string $driver): string
    {
        return match ($driver) {
            'mysql' => "
                INSERT INTO `words` (`keyword`, `data`) VALUES (:keyword, :data)
                ON DUPLICATE KEY UPDATE `data` = VALUES(`data`)
            ",
            default => "
                INSERT INTO words (keyword, data) VALUES (:keyword, :data)
                ON CONFLICT (keyword) DO UPDATE SET data = :data
            ",
        };
    }

    public static function getWordDelete(): string
    {
        return "DELETE FROM words WHERE keyword = :keyword";
    }

    public static function getWordRename(): string
    {
        return "UPDATE words SET keyword = :new_keyword WHERE keyword = :old_keyword";
    }

    public static function getWordSelect(): string
    {
        return "SELECT data FROM words WHERE keyword = :keyword LIMIT 1";
    }

    public static function getWordsSelect(): string
    {
        return "SELECT keyword, data FROM words ORDER BY created_at ASC";
    }

    public static function getMatchingWordsQuery(string $driver): string
    {
        return match ($driver) {
            'mysql' => "
                SELECT keyword, data FROM `words`
                WHERE INSTR(LOWER(:text), LOWER(`keyword`)) > 0
                   OR LOWER(`keyword`) = LOWER(:text_trimmed)
                ORDER BY `created_at` ASC
            ",
            'pgsql' => "
                SELECT keyword, data FROM words
                WHERE POSITION(LOWER(keyword) IN LOWER(:text)) > 0
                   OR LOWER(keyword) = LOWER(:text_trimmed)
                ORDER BY created_at ASC
            ",
            default => "
                SELECT keyword, data FROM words
                WHERE INSTR(LOWER(:text), LOWER(keyword)) > 0
                   OR LOWER(keyword) = LOWER(:text_trimmed)
                ORDER BY created_at ASC
            ",
        };
    }

    public static function getStoryInsert(string $driver): string
    {
        return match ($driver) {
            'mysql' => "INSERT INTO `stories` (`business_connection_id`, `file_id`, `file_type`, `caption`, `active_period`, `post_to_chat_page`, `protect_content`, `scheduled_at`, `status`) VALUES (:business_connection_id, :file_id, :file_type, :caption, :active_period, :post_to_chat_page, :protect_content, :scheduled_at, :status)",
            default => "INSERT INTO stories (business_connection_id, file_id, file_type, caption, active_period, post_to_chat_page, protect_content, scheduled_at, status) VALUES (:business_connection_id, :file_id, :file_type, :caption, :active_period, :post_to_chat_page, :protect_content, :scheduled_at, :status)",
        };
    }

    public static function getAllStories(): string
    {
        return "SELECT * FROM stories WHERE status != 'draft' ORDER BY CASE WHEN status = 'pending' THEN 0 ELSE 1 END ASC, CASE WHEN status = 'pending' THEN scheduled_at END ASC, posted_at DESC, id DESC LIMIT 100";
    }

    public static function getStoriesPending(): string
    {
        return "SELECT * FROM stories WHERE status = 'pending' AND (scheduled_at IS NULL OR scheduled_at <= :now) ORDER BY id ASC";
    }

    public static function getStoriesPosted(): string
    {
        return "SELECT * FROM stories WHERE status = 'posted' ORDER BY posted_at DESC LIMIT 50";
    }

    public static function getStoryById(): string
    {
        return "SELECT * FROM stories WHERE id = :id LIMIT 1";
    }

    public static function getStoryUpdateStatus(string $driver): string
    {
        return match ($driver) {
            'mysql' => "UPDATE `stories` SET `status` = :status, `story_id` = :story_id, `posted_at` = :posted_at WHERE `id` = :id",
            default => "UPDATE stories SET status = :status, story_id = :story_id, posted_at = :posted_at WHERE id = :id",
        };
    }

    public static function getStoryDelete(string $driver): string
    {
        return match ($driver) {
            'mysql' => "DELETE FROM `stories` WHERE `id` = :id",
            default => "DELETE FROM stories WHERE id = :id",
        };
    }

    public static function getStoryUpdateCaption(string $driver): string
    {
        return match ($driver) {
            'mysql' => "UPDATE `stories` SET `caption` = :caption WHERE `id` = :id",
            default => "UPDATE stories SET caption = :caption WHERE id = :id",
        };
    }

    public static function getActiveBusinessConnection(): string
    {
        return "SELECT key FROM settings WHERE key LIKE 'bc_%' AND value = '1' LIMIT 1";
    }
}