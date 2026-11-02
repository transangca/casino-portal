<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Services\I18n;

/**
 * Đổi nhanh theme & ngôn ngữ (không cần đăng nhập — dùng cookie).
 */
class SettingController
{
    public function setTheme(): void
    {
        $theme = (string)($_GET['theme'] ?? 'system');
        if (!in_array($theme, ['dark', 'light', 'system'], true)) {
            $theme = 'system';
        }

        setcookie('theme', $theme, [
            'expires'  => time() + 31536000,
            'path'     => '/',
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
        $_COOKIE['theme'] = $theme;

        $userId = Auth::id();
        if ($userId) {
            Database::update('users', ['theme' => $theme], 'id = :id', ['id' => $userId]);
        }

        $back = (string)($_GET['back'] ?? '');
        if ($back === '' || !str_starts_with($back, '/')) {
            $back = $_SERVER['HTTP_REFERER'] ?? url('/');
            if (!str_starts_with($back, '/') && !str_starts_with($back, 'http')) {
                $back = url('/');
            }
        }
        redirect($back);
    }

    public function setLanguage(): void
    {
        $code = (string)($_GET['lang'] ?? 'vi');
        if (!in_array($code, I18n::locales(), true)) {
            $code = 'vi';
        }

        setcookie('lang', $code, [
            'expires'  => time() + 31536000,
            'path'     => '/',
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
        $_COOKIE['lang'] = $code;

        $userId = Auth::id();
        if ($userId) {
            Database::update('users', ['language' => $code], 'id = :id', ['id' => $userId]);
        }

        $back = (string)($_GET['back'] ?? '');
        if ($back === '' || !str_starts_with($back, '/')) {
            $back = $_SERVER['HTTP_REFERER'] ?? url('/');
            if (!str_starts_with($back, '/') && !str_starts_with($back, 'http')) {
                $back = url('/');
            }
        }
        redirect($back);
    }
}
