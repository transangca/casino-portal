<?php
/**
 * Cron dịch vụ SMM — đồng bộ bảng giá + cập nhật trạng thái đơn.
 *
 * Chạy: php database/cron_smm.php
 * Đặt crontab: 5 phút/lần: php /path/to/database/cron_smm.php >> storage/logs/cron_smm.log 2>&1
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

use App\Services\SmmService;

echo '[' . date('Y-m-d H:i:s') . '] Cron SMM bắt đầu' . PHP_EOL;

if (!SmmService::enabled()) {
    echo '  Dịch vụ SMM đang tạm dừng — bỏ qua.' . PHP_EOL;
    exit(0);
}

if (!SmmService::configured()) {
    echo '  Chưa cấu hình API key — bỏ qua.' . PHP_EOL;
    exit(0);
}

// 1. Đồng bộ bảng giá (mỗi 30 phút)
if (SmmService::shouldSync()) {
    $res = SmmService::sync();
    echo '  Đồng bộ: ' . ($res['ok'] ? $res['message'] : 'LỖI — ' . $res['message']) . PHP_EOL;
    if ($res['ok']) {
        SmmService::markSynced();
    }
} else {
    echo '  Bảng giá còn mới — bỏ qua đồng bộ.' . PHP_EOL;
}

// 2. Cập nhật trạng thái các đơn đang chạy (batch tối đa 100 mã/lần)
$status = SmmService::refreshStatuses(100);
echo '  Đơn: kiểm tra ' . (int)$status['checked'] . ', cập nhật ' . (int)$status['updated'] . PHP_EOL;

echo '[' . date('Y-m-d H:i:s') . '] Cron SMM xong' . PHP_EOL;
