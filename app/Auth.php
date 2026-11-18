<?php
declare(strict_types=1);

namespace App;

class Auth
{
    private const COOKIE_NAME = 'remember';
    private const COOKIE_DAYS = 30;

    public static function user(): ?array
    {
        if (empty($_SESSION['user_id'])) return null;

        $stmt = Database::conn()->prepare(
            'SELECT * FROM users WHERE id = ? AND deleted_at IS NULL AND banned_at IS NULL'
        );
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Восстанавливает сессию из куки "remember", если сессии нет.
     * ДОЛЖЕН вызываться в bootstrap.php сразу после session_start() —
     * до любого вывода HTML.
     */
    public static function restoreFromCookieIfNeeded(): void
    {
        if (!empty($_SESSION['user_id'])) return;
        if (empty($_COOKIE[self::COOKIE_NAME])) return;

        self::restoreFromCookie();
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function login(int $userId, bool $remember = false): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;

        // Генерируем свежий CSRF, чтобы не тащить старый
        $_SESSION['csrf'] = bin2hex(random_bytes(32));

        if ($remember) {
            self::issueRememberToken($userId);
        } else {
            self::clearRememberCookie();
        }
    }

    public static function logout(): void
    {
        // Удаляем все токены этого пользователя
        if (!empty($_SESSION['user_id'])) {
            $stmt = Database::conn()->prepare('DELETE FROM user_tokens WHERE user_id = ?');
            $stmt->execute([$_SESSION['user_id']]);
        }

        self::clearRememberCookie();

        $_SESSION = [];
        session_destroy();
    }

    /* ─── Remember-me ─────────────────────────────────── */

    private static function issueRememberToken(int $userId): void
    {
        $selector  = bin2hex(random_bytes(8));   // 16 hex
        $validator = bin2hex(random_bytes(32));  // 64 hex

        $expires = date('Y-m-d H:i:s', time() + self::COOKIE_DAYS * 86400);

        Database::conn()->prepare(
            'INSERT INTO user_tokens (user_id, selector, validator_hash, expires_at)
             VALUES (?, ?, ?, ?)'
        )->execute([$userId, $selector, hash('sha256', $validator), $expires]);

        self::setRememberCookie($selector, $validator, $expires);
    }

    private static function restoreFromCookie(): bool
    {
        if (empty($_COOKIE[self::COOKIE_NAME])) return false;

        $raw = (string)$_COOKIE[self::COOKIE_NAME];
        if (!str_contains($raw, ':')) {
            self::clearRememberCookie();
            return false;
        }

        [$selector, $validator] = explode(':', $raw, 2);

        if (!preg_match('/^[a-f0-9]{16}$/', $selector)
            || !preg_match('/^[a-f0-9]{64}$/', $validator)) {
            self::clearRememberCookie();
            return false;
        }

        $db = Database::conn();
        $stmt = $db->prepare(
            'SELECT id, user_id, validator_hash, expires_at
             FROM user_tokens WHERE selector = ? LIMIT 1'
        );
        $stmt->execute([$selector]);
        $row = $stmt->fetch();

        if (!$row) {
            self::clearRememberCookie();
            return false;
        }

        // Истёк — удаляем и отказываем
        if (strtotime($row['expires_at']) < time()) {
            $db->prepare('DELETE FROM user_tokens WHERE id = ?')->execute([$row['id']]);
            self::clearRememberCookie();
            return false;
        }

        if (!hash_equals($row['validator_hash'], hash('sha256', $validator))) {
            // Кто-то подделал куку — на всякий случай убиваем все токены пользователя
            $db->prepare('DELETE FROM user_tokens WHERE user_id = ?')->execute([$row['user_id']]);
            self::clearRememberCookie();
            return false;
        }

        // Успех. Ротируем токен и стартуем сессию.
        // Проверим, что пользователь не удалён
        $stmt = $db->prepare('SELECT 1 FROM users WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$row['user_id']]);
        if (!$stmt->fetch()) {
            $db->prepare('DELETE FROM user_tokens WHERE user_id = ?')->execute([$row['user_id']]);
            self::clearRememberCookie();
            return false;
        }

        // Успех. Ротируем токен и стартуем сессию.
        $db->prepare('DELETE FROM user_tokens WHERE id = ?')->execute([$row['id']]);

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$row['user_id'];
        $_SESSION['csrf']    = bin2hex(random_bytes(32));

        self::issueRememberToken((int)$row['user_id']);

        return true;
    }

    private static function setRememberCookie(string $selector, string $validator, string $expires): void
    {
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        setcookie(self::COOKIE_NAME, $selector . ':' . $validator, [
            'expires'  => strtotime($expires),
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private static function clearRememberCookie(): void
    {
        if (!empty($_COOKIE[self::COOKIE_NAME])) {
            setcookie(self::COOKIE_NAME, '', [
                'expires'  => time() - 3600,
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            unset($_COOKIE[self::COOKIE_NAME]);
        }
    }
	
	public static function isAdmin(): bool
    {
        $u = self::user();
        return $u && in_array($u['role'], ['admin', 'owner'], true);
    }

    public static function isOwner(): bool
    {
        $u = self::user();
        return $u && $u['role'] === 'owner';
    }
}