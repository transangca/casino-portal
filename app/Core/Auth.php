<?php
namespace App\Core;

use App\Core\Database;

/**
 * Quản lý phiên đăng nhập + định danh người dùng.
 */
class Auth
{
    private static ?array $user = null;
    private static bool $resolved = false;

    public static function attempt(string $login, string $password): bool
    {
        $user = Database::fetch(
            'SELECT * FROM users WHERE (username = ? OR email = ?) AND status = "active" LIMIT 1',
            [$login, $login]
        );

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        self::$user = $user;
        self::$resolved = true;

        Database::update('users', ['last_login_at' => now()], 'id = :id', ['id' => $user['id']]);
        return true;
    }

    public static function loginById(int $id): bool
    {
        $user = Database::fetch('SELECT * FROM users WHERE id = ? LIMIT 1', [$id]);
        if (!$user) {
            return false;
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        self::$user = $user;
        self::$resolved = true;
        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        self::$user = null;
        self::$resolved = true;
    }

    public static function user(): ?array
    {
        if (self::$resolved) {
            return self::$user;
        }
        self::$resolved = true;

        $id = $_SESSION['user_id'] ?? null;
        if (!$id) {
            return self::$user = null;
        }

        $user = Database::fetch('SELECT * FROM users WHERE id = ? LIMIT 1', [(int)$id]);
        if (!$user || $user['status'] !== 'active') {
            return self::$user = null;
        }
        return self::$user = $user;
    }

    public static function id(): ?int
    {
        $u = self::user();
        return $u ? (int)$u['id'] : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function guest(): bool
    {
        return !self::check();
    }

    public static function isAdmin(): bool
    {
        $u = self::user();
        return $u && in_array($u['role'], ['admin', 'operator'], true);
    }

    public static function isOperator(): bool
    {
        $u = self::user();
        return $u && $u['role'] === 'operator';
    }

    /** Lấy balance mới nhất từ DB (không dùng cache trong session) */
    public static function balance(): float
    {
        $v = Database::fetchColumn('SELECT balance FROM users WHERE id = ?', [self::id()]);
        return (float)$v;
    }

    public static function refresh(): void
    {
        self::$resolved = false;
        self::user();
    }
}
