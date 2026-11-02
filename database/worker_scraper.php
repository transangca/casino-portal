<?php
/**
 * Worker nền — quét UI/UX website.
 *
 * Chạy: php database/worker_scraper.php <job_id>
 * (controller tự spawn, hoặc cron có thể gọi cho job 'queued')
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

use App\Services\UiScraperService;

$jobId = (int)($argv[1] ?? 0);
if ($jobId <= 0) {
    echo "Usage: php database/worker_scraper.php <job_id>\n";
    exit(1);
}

echo '[' . date('Y-m-d H:i:s') . "] Bắt đầu quét job #{$jobId}\n";

$res = UiScraperService::runJob($jobId);

echo '[' . date('Y-m-d H:i:s') . '] ' . ($res['ok'] ? 'Xong: ' . $res['message'] : 'LỖI: ' . $res['message']) . "\n";
