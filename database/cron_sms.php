<?php
/**
 * Cron — gửi tin nhắn SMS hàng loạt + chạy job quét UI/UX đang chờ.
 *
 * Chạy: php database/cron_sms.php
 * Crontab: mỗi 2 phút chạy php /path/to/database/cron_sms.php >> storage/logs/cron_sms.log 2>&1
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

use App\Core\Database;
use App\Services\SmsService;
use App\Services\UiScraperService;

echo '[' . date('Y-m-d H:i:s') . '] Cron bắt đầu' . PHP_EOL;

// ---------------------------------------------------------------
//  1. Gửi tin nhắn SMS
// ---------------------------------------------------------------
$batch = (int)($argv[1] ?? 50);

$sum = SmsService::processAll($batch);
echo '  SMS: ' . (int)$sum['campaigns'] . ' chiến dịch, gửi ' . (int)$sum['sent']
    . ', lỗi ' . (int)$sum['failed'] . PHP_EOL;

// ---------------------------------------------------------------
//  2. Job quét UI/UX đang chờ (queued) — chạy tiếp nếu worker bị gián đoạn
// ---------------------------------------------------------------
$queued = Database::fetchAll(
    'SELECT id FROM scraper_jobs WHERE status = "queued" ORDER BY id LIMIT 3'
);
foreach ($queued as $j) {
    echo '  Quét UI/UX job #' . (int)$j['id'] . '...';
    $res = UiScraperService::runJob((int)$j['id']);
    echo ($res['ok'] ? ' xong' : ' lỗi: ' . $res['message']) . PHP_EOL;
}

echo '[' . date('Y-m-d H:i:s') . '] Cron xong' . PHP_EOL;
