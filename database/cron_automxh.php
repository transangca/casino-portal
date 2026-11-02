<?php
/**
 * Cron Auto MXH — đồng bộ danh mục & làm mới trạng thái đơn.
 *
 * Dùng cho Windows Task Scheduler hoặc cron Linux:
 *   php database/cron_automxh.php
 *
 * Gợi ý cron (mỗi 5 phút):
 *   *\/5 * * * * php /path/to/database/cron_automxh.php >> /var/log/automxh.log 2>&1
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

use App\Services\AutoMxhService;

// Không cho chạy song song
$lockFile = BASE_PATH . '/storage/automxh_cron.lock';
if (!is_dir(dirname($lockFile))) {
    @mkdir(dirname($lockFile), 0775, true);
}
$lock = @fopen($lockFile, 'c');
if ($lock && !flock($lock, LOCK_EX | LOCK_NB)) {
    echo "[" . date('Y-m-d H:i:s') . "] Đang có tiến trình khác chạy — bỏ qua.\n";
    exit(0);
}

try {
    if (!AutoMxhService::configured()) {
        echo "[" . date('Y-m-d H:i:s') . "] Chưa cấu hình API key Auto MXH — bỏ qua.\n";
        exit(0);
    }

    // 1. Đồng bộ danh mục
    $sync = AutoMxhService::sync();
    if ($sync['ok']) {
        AutoMxhService::markSynced();
        echo "[" . date('Y-m-d H:i:s') . "] " . $sync['message'] . "\n";
    } else {
        echo "[" . date('Y-m-d H:i:s') . "] Đồng bộ danh mục lỗi: " . $sync['message'] . "\n";
    }

    // 2. Làm mới trạng thái đơn
    $refresh = AutoMxhService::refreshStatuses(50);
    echo "[" . date('Y-m-d H:i:s') . "] Kiểm tra {$refresh['checked']} đơn, cập nhật {$refresh['updated']} đơn.\n";
} catch (Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] LỖI: " . $e->getMessage() . "\n";
    exit(1);
} finally {
    if ($lock) {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}
