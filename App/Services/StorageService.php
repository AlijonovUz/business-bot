<?php

namespace App\Services;

use PDO;

class StorageService
{
    private PDO $pdo;

    private array $defaultSettings = [
        'calculator' => 'off',
        'animation' => 'off',
        'auto-answer' => 'off',
        'currency' => 'off',
        'timed-media' => 'off',
        'timed-command' => '',
        'edited-message' => 'off',
        'deleted-message' => 'off',
        'commands_set' => '0',
    ];

    private string $driver = 'sqlite';
    private ?string $encryptionKeyBinary = null;

    public function __construct(array|string $config = [], string $encryptionKey = '')
    {
        if (is_string($config)) {
            $config = ['driver' => 'sqlite', 'sqlite_path' => $config];
        }

        $rawDriver = strtolower($config['driver'] ?? 'sqlite');
        $this->driver = match($rawDriver) {
            'mysql' => 'mysql',
            'pgsql', 'postgres', 'postgresql' => 'pgsql',
            default => 'sqlite',
        };

        if ($this->driver === 'mysql') {
            $host = $config['host'] ?? '127.0.0.1';
            $port = $config['port'] ?? 3306;
            $dbname = $config['database'] ?? '';
            $user = $config['username'] ?? '';
            $pass = $config['password'] ?? '';

            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
            $this->pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } elseif ($this->driver === 'pgsql') {
            $host = $config['host'] ?? '127.0.0.1';
            $port = $config['port'] ?? 5432;
            $dbname = $config['database'] ?? '';
            $user = $config['username'] ?? '';
            $pass = $config['password'] ?? '';

            $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
            $this->pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } else {
            $dbPath = $config['sqlite_path'] ?? 'storage/database.sqlite';
            $dir = dirname($dbPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $htaccess = $dir . '/.htaccess';
            if (!file_exists($htaccess)) {
                file_put_contents($htaccess, "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n");
            }

            $this->pdo = new PDO("sqlite:" . $dbPath);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            $this->pdo->exec("PRAGMA journal_mode = WAL;");
            $this->pdo->exec("PRAGMA busy_timeout = 5000;");
            $this->pdo->exec("PRAGMA synchronous = NORMAL;");
        }

        $this->createTables();
        $this->initEncryption($encryptionKey);
    }

    private function initEncryption(string $userKey = ''): void
    {
        if (!empty($userKey)) {
            $this->encryptionKeyBinary = hash('sha256', $userKey, true);
            return;
        }

        $stmt = $this->pdo->prepare("SELECT value FROM settings WHERE key = 'app_encryption_key'");
        $stmt->execute();
        $saved = $stmt->fetchColumn();

        if ($saved !== false && !empty($saved)) {
            $this->encryptionKeyBinary = hash('sha256', (string)$saved, true);
        } else {
            $generated = bin2hex(random_bytes(32));
            $this->setSetting('app_encryption_key', $generated);
            $this->encryptionKeyBinary = hash('sha256', $generated, true);
        }
    }

    public function encrypt(?string $data): ?string
    {
        if ($data === null || $data === '' || !$this->encryptionKeyBinary) {
            return $data;
        }

        try {
            $iv = random_bytes(12);
            $tag = '';
            $ciphertext = openssl_encrypt(
                $data,
                'aes-256-gcm',
                $this->encryptionKeyBinary,
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );

            if ($ciphertext === false) {
                return $data;
            }

            return 'enc:v1:' . base64_encode($iv . $tag . $ciphertext);
        } catch (\Throwable) {
            return $data;
        }
    }

    public function decrypt(?string $data): ?string
    {
        if ($data === null || $data === '' || !$this->encryptionKeyBinary) {
            return $data;
        }

        if (!str_starts_with($data, 'enc:v1:')) {
            return $data;
        }

        try {
            $raw = base64_decode(substr($data, 7), true);
            if ($raw === false || strlen($raw) < 28) {
                return $data;
            }

            $iv = substr($raw, 0, 12);
            $tag = substr($raw, 12, 16);
            $ciphertext = substr($raw, 28);

            $decrypted = openssl_decrypt(
                $ciphertext,
                'aes-256-gcm',
                $this->encryptionKeyBinary,
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );

            return $decrypted !== false ? $decrypted : $data;
        } catch (\Throwable) {
            return $data;
        }
    }

    private function createTables(): void
    {
        $this->pdo->exec(DatabaseQueries::getSchema($this->driver));

        try {
            $this->pdo->exec(DatabaseQueries::getMigration($this->driver));
        } catch (\Throwable) {
        }
    }

    public function getStep(int|string|null $chatId): string
    {
        if ($chatId === null || $chatId === '') return '';
        $stmt = $this->pdo->prepare("SELECT step FROM steps WHERE chat_id = :chat_id");
        $stmt->execute([':chat_id' => (string)$chatId]);
        $res = $stmt->fetchColumn();
        return $res !== false ? (string)$res : '';
    }

    public function setStep(int|string|null $chatId, string $value): void
    {
        if ($chatId === null || $chatId === '') return;
        $stmt = $this->pdo->prepare(DatabaseQueries::getStepUpsert($this->driver));
        $stmt->execute([':chat_id' => (string)$chatId, ':step' => $value]);
    }

    public function clearStep(int|string|null $chatId): void
    {
        if ($chatId === null || $chatId === '') return;
        $stmt = $this->pdo->prepare("DELETE FROM steps WHERE chat_id = :chat_id");
        $stmt->execute([':chat_id' => (string)$chatId]);
    }

    public function initSettings(): void
    {
        $stmt = $this->pdo->prepare(DatabaseQueries::getSettingInit($this->driver));
        foreach ($this->defaultSettings as $name => $default) {
            $stmt->execute([':key' => $name, ':value' => $default]);
        }
    }

    public function getSetting(string $name): string
    {
        $stmt = $this->pdo->prepare("SELECT value FROM settings WHERE key = :key");
        $stmt->execute([':key' => $name]);
        $res = $stmt->fetchColumn();
        return $res !== false ? (string)$res : ($this->defaultSettings[$name] ?? '');
    }

    public function setSetting(string $name, string $value): void
    {
        $stmt = $this->pdo->prepare(DatabaseQueries::getSettingUpsert($this->driver));
        $stmt->execute([':key' => $name, ':value' => $value]);
    }

    public function isEnabled(string $name): bool
    {
        return $this->getSetting($name) === 'on';
    }

    public function getWord(string $keyword): ?array
    {
        $stmt = $this->pdo->prepare(DatabaseQueries::getWordSelect());
        $stmt->execute([':keyword' => $keyword]);
        $res = $stmt->fetchColumn();
        return $res !== false ? (json_decode((string)$res, true) ?? null) : null;
    }

    public function saveWord(string $keyword, array $data): void
    {
        $stmt = $this->pdo->prepare(DatabaseQueries::getWordUpsert($this->driver));
        $stmt->execute([
            ':keyword' => $keyword,
            ':data' => json_encode($data, JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function deleteWord(string $keyword): void
    {
        $stmt = $this->pdo->prepare(DatabaseQueries::getWordDelete());
        $stmt->execute([':keyword' => $keyword]);
    }

    public function renameWord(string $oldKeyword, string $newKeyword): void
    {
        $stmt = $this->pdo->prepare(DatabaseQueries::getWordRename());
        $stmt->execute([
            ':old_keyword' => $oldKeyword,
            ':new_keyword' => $newKeyword,
        ]);
    }

    public function findMatchingWords(string $text): array
    {
        $stmt = $this->pdo->query(DatabaseQueries::getWordsSelect());
        $rows = $stmt->fetchAll();
        if (empty($rows)) return [];

        $normalizedText = $this->normalizeString($text);
        if ($normalizedText === '') return [];

        $matches = [];
        foreach ($rows as $row) {
            $keyword = (string)$row['keyword'];
            $data = json_decode($row['data'], true) ?? [];
            $matchType = $data['match_type'] ?? 'contains';
            $normalizedKey = $this->normalizeString($keyword);

            if ($normalizedKey === '') continue;

            if ($matchType === 'exact') {
                if ($normalizedText === $normalizedKey) {
                    $data['_pos'] = 0;
                    $data['_keyword_len'] = mb_strlen($normalizedKey);
                    $matches[] = $data;
                }
            } else {
                $pattern = '/(?:\b|^|\s)' . preg_quote($normalizedKey, '/') . '(?:\b|$|\s)/ui';
                if (preg_match($pattern, $normalizedText, $m, PREG_OFFSET_CAPTURE)) {
                    $pos = $m[0][1] ?? mb_stripos($normalizedText, $normalizedKey);
                    $data['_pos'] = $pos !== false ? (int)$pos : 0;
                    $data['_keyword_len'] = mb_strlen($normalizedKey);
                    $matches[] = $data;
                }
            }
        }

        if (empty($matches)) return [];

        usort($matches, function ($a, $b) {
            if ($a['_pos'] === $b['_pos']) {
                return ($b['_keyword_len'] ?? 0) <=> ($a['_keyword_len'] ?? 0);
            }
            return $a['_pos'] <=> $b['_pos'];
        });

        $unique = [];
        $seen = [];
        foreach ($matches as $m) {
            unset($m['_pos'], $m['_keyword_len']);
            $sig = ($m['type'] ?? '') . '|' . ($m['content'] ?? '') . '|' . ($m['file_id'] ?? '');
            if (!isset($seen[$sig])) {
                $seen[$sig] = true;
                $unique[] = $m;
            }
        }

        return $unique;
    }

    private function normalizeString(string $str): string
    {
        $str = mb_strtolower(trim($str));
        $str = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $str);
        return trim(preg_replace('/\s+/u', ' ', $str));
    }

    public function getWords(): array
    {
        $stmt = $this->pdo->query(DatabaseQueries::getWordsSelect());
        $rows = $stmt->fetchAll();
        $words = [];
        foreach ($rows as $row) {
            $words[$row['keyword']] = json_decode($row['data'], true) ?? [];
        }
        return $words;
    }

    public function saveWords(array $words): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec("DELETE FROM words");
            $stmt = $this->pdo->prepare("INSERT INTO words (keyword, data) VALUES (:keyword, :data)");
            foreach ($words as $keyword => $data) {
                $stmt->execute([
                    ':keyword' => (string)$keyword,
                    ':data' => json_encode($data, JSON_UNESCAPED_UNICODE)
                ]);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function saveMessage(int|string $chatId, array $msg): void
    {
        $stmt = $this->pdo->prepare(DatabaseQueries::getMessageUpsert($this->driver));
        $stmt->execute([
            ':chat_id' => (string)$chatId,
            ':message_id' => (int)$msg['message_id'],
            ':user_id' => isset($msg['user_id']) ? (string)$msg['user_id'] : null,
            ':reply_to_message_id' => isset($msg['reply_to_message_id']) ? (int)$msg['reply_to_message_id'] : null,
            ':username' => $this->encrypt($msg['username'] ?? null),
            ':first_name' => $this->encrypt($msg['first_name'] ?? null),
            ':last_name' => $this->encrypt($msg['last_name'] ?? null),
            ':text' => $this->encrypt($msg['text'] ?? null),
            ':date' => $msg['date'] ?? date('Y-m-d H:i:s'),
            ':edit_date' => $msg['edit_date'] ?? null,
            ':edit_message' => $this->encrypt(json_encode($msg['edit_message'] ?? [], JSON_UNESCAPED_UNICODE)),
            ':media' => $this->encrypt(json_encode($msg['media'] ?? [], JSON_UNESCAPED_UNICODE)),
            ':reply_data' => isset($msg['reply_data']) ? $this->encrypt(json_encode($msg['reply_data'], JSON_UNESCAPED_UNICODE)) : null,
        ]);
    }

    private function decryptMessageRow(array &$row): void
    {
        $row['first_name'] = $this->decrypt($row['first_name'] ?? null);
        $row['last_name'] = $this->decrypt($row['last_name'] ?? null);
        $row['username'] = $this->decrypt($row['username'] ?? null);
        $row['text'] = $this->decrypt($row['text'] ?? null);

        $decryptedEdit = $this->decrypt($row['edit_message'] ?? null);
        $row['edit_message'] = json_decode($decryptedEdit ?? '[]', true) ?? [];

        $decryptedMedia = $this->decrypt($row['media'] ?? null);
        $row['media'] = json_decode($decryptedMedia ?? '[]', true) ?? [];

        if (array_key_exists('reply_data', $row)) {
            $decryptedReply = $this->decrypt($row['reply_data'] ?? null);
            $row['reply_data'] = json_decode($decryptedReply ?? 'null', true);
        }

        $row['message_id'] = (int)$row['message_id'];
        if ($row['reply_to_message_id'] !== null) {
            $row['reply_to_message_id'] = (int)$row['reply_to_message_id'];
        }
        if (is_numeric($row['user_id'])) {
            $row['user_id'] = (int)$row['user_id'];
        }
    }

    public function getHistory(int|string $chatId, int $limit = 2000): array
    {
        $stmt = $this->pdo->prepare("
            SELECT user_id, message_id, reply_to_message_id, username, first_name, last_name, text, date, edit_date, edit_message, media, reply_data
            FROM (
                SELECT * FROM messages
                WHERE chat_id = :chat_id
                ORDER BY id DESC
                LIMIT :limit
            ) AS sub_messages
            ORDER BY id ASC
        ");
        $stmt->bindValue(':chat_id', (string)$chatId);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $this->decryptMessageRow($row);
        }

        return $rows;
    }

    public function findMessageInHistory(int|string $chatId, int $messageId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT user_id, message_id, reply_to_message_id, username, first_name, last_name, text, date, edit_date, edit_message, media, reply_data
            FROM messages
            WHERE chat_id = :chat_id AND message_id = :message_id
            LIMIT 1
        ");
        $stmt->execute([':chat_id' => (string)$chatId, ':message_id' => $messageId]);
        $row = $stmt->fetch();
        if (!$row) return null;

        $this->decryptMessageRow($row);

        return ['index' => 0, 'message' => $row, 'all' => []];
    }

    public function findMessagesInHistory(int|string $chatId, array $messageIds): array
    {
        if (empty($messageIds)) return [];

        $placeholders = implode(',', array_fill(0, count($messageIds), '?'));
        $stmt = $this->pdo->prepare("
            SELECT user_id, message_id, reply_to_message_id, username, first_name, last_name, text, date, edit_date, edit_message, media, reply_data
            FROM messages
            WHERE chat_id = ? AND message_id IN ($placeholders)
        ");

        $params = array_merge([(string)$chatId], array_map('intval', $messageIds));
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $this->decryptMessageRow($row);
        }

        return $rows;
    }

    public function updateMessageInHistory(int|string $chatId, int $messageId, ?string $text, array $media, string $editDate, array $editMessageHistory): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE messages
            SET text = :text,
                media = :media,
                edit_date = :edit_date,
                edit_message = :edit_message
            WHERE chat_id = :chat_id AND message_id = :message_id
        ");

        $stmt->execute([
            ':text' => $this->encrypt($text),
            ':media' => $this->encrypt(json_encode($media, JSON_UNESCAPED_UNICODE)),
            ':edit_date' => $editDate,
            ':edit_message' => $this->encrypt(json_encode($editMessageHistory, JSON_UNESCAPED_UNICODE)),
            ':chat_id' => (string)$chatId,
            ':message_id' => $messageId
        ]);
    }

    public function getAllMessages(): array
    {
        $stmt = $this->pdo->query("
            SELECT user_id, message_id, reply_to_message_id, username, first_name, last_name, text, date, edit_date, edit_message, media
            FROM messages
            ORDER BY date ASC
        ");
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $this->decryptMessageRow($row);
        }

        return $rows;
    }

    public function clearMediaFolder(string $path = 'storage/media'): void
    {
        if (!is_dir($path)) return;

        foreach (glob($path . '/*') as $file) {
            if (is_file($file)) unlink($file);
        }
    }

    public function ensureMediaDir(string $path = 'storage/media'): void
    {
        if (!is_dir($path)) mkdir($path, 0755, true);
    }
}
