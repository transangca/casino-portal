<?php
/**
 * Bootstrap dùng chung cho các script test.
 */

define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $path = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

require_once BASE_PATH . '/app/Support/helpers.php';

date_default_timezone_set((string)config('app.timezone', 'Asia/Ho_Chi_Minh'));
mb_internal_encoding('UTF-8');

/**
 * Xóa bộ đếm rate limit.
 *
 * Các test HTTP gọi nhiều route liên tiếp → dính 429 của SecurityGuard.
 * Gọi hàm này ở đầu mỗi test HTTP để chạy độc lập.
 */
if (!function_exists('reset_rate_limits')) {
    function reset_rate_limits(): void
    {
        try {
            App\Core\Database::query('DELETE FROM rate_limits');
        } catch (\Throwable $e) {
            // Bảng chưa tồn tại — bỏ qua
        }
    }
}
