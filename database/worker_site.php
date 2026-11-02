<?php
/**
 * Worker nền — copy 1-1 trang web / tạo website tự động.
 *
 * Chạy: php database/worker_site.php clone <id>
 *        php database/worker_site.php auto  <id>
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

use App\Services\SiteAutoBuilderService;
use App\Services\SiteCloneService;

$type = (string)($argv[1] ?? '');
$id   = (int)($argv[2] ?? 0);

if ($id <= 0) {
    echo "Usage: php database/worker_site.php <clone|auto> <id>\n";
    exit(1);
}

echo '[' . date('Y-m-d H:i:s') . "] Bắt đầu xử lý {$type} #{$id}\n";

$res = $type === 'auto'
    ? SiteAutoBuilderService::run($id)
    : SiteCloneService::run($id);

echo '[' . date('Y-m-d H:i:s') . '] ' . ($res['ok'] ? 'Xong: ' . $res['message'] : 'LỖI: ' . $res['message']) . "\n";
