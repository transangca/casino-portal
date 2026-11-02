<?php
namespace App\Core;

/**
 * CSRF token: sinh 1 token/session, kiểm tra bằng hash_equals.
 */
class CSRF
{
    private const KEY = '_csrf_token';

    public static function token(): string
    {
        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::KEY];
    }

    public static function field(): string
    {
        return sprintf(
            '<input type="hidden" name="_csrf_token" value="%s">',
            htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8')
        );
    }

    public static function check(?string $token = null): bool
    {
        $token ??= $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $expected = $_SESSION[self::KEY] ?? '';
        return $expected !== '' && is_string($token) && hash_equals($expected, $token);
    }

    public static function verifyOrFail(): void
    {
        if (!self::check()) {
            if (is_ajax()) {
                json_error('CSRF token không hợp lệ', 419);
            }
            flash('error', 'Phiên làm việc đã hết hạn. Vui lòng thử lại.');
            redirect($_SERVER['HTTP_REFERER'] ?? url('/'));
        }
    }

    public static function rotate(): void
    {
        $_SESSION[self::KEY] = bin2hex(random_bytes(32));
    }
}
