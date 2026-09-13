<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';

class AddtomarDbSessionHandler implements SessionHandlerInterface
{
    public function open(string $path, string $name): bool
    {
        try {
            caps_db();
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        try {
            $stmt = caps_db()->prepare('SELECT data FROM php_sessions WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $data = $stmt->fetchColumn();

            return is_string($data) ? $data : '';
        } catch (Throwable) {
            return '';
        }
    }

    public function write(string $id, string $data): bool
    {
        try {
            $stmt = caps_db()->prepare(
                'INSERT INTO php_sessions (id, data, updated_at) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE data = VALUES(data), updated_at = VALUES(updated_at)'
            );

            return $stmt->execute([$id, $data, time()]);
        } catch (Throwable) {
            return false;
        }
    }

    public function destroy(string $id): bool
    {
        try {
            $stmt = caps_db()->prepare('DELETE FROM php_sessions WHERE id = ?');

            return $stmt->execute([$id]);
        } catch (Throwable) {
            return false;
        }
    }

    public function gc(int $max_lifetime): int|false
    {
        try {
            $stmt = caps_db()->prepare('DELETE FROM php_sessions WHERE updated_at < ?');
            $stmt->execute([time() - $max_lifetime]);

            return $stmt->rowCount();
        } catch (Throwable) {
            return 0;
        }
    }
}

function addtomar_boot_session(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    $lifetime = 60 * 60 * 24 * 14;
    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path' => '/',
        'secure' => addtomar_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.gc_maxlifetime', (string) $lifetime);

    try {
        caps_db();
        session_set_save_handler(new AddtomarDbSessionHandler(), true);
    } catch (Throwable) {
        // Fall back to default file sessions when MySQL is unavailable.
    }

    session_start();
}
