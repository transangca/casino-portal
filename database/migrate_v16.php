<?php
/**
 * Chạy migration V16 (dịch vụ mới: check SĐT, copy web, tạo web A-Z, spam SMS).
 * Chạy: php database/migrate_v16.php
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

$sql = file_get_contents(BASE_PATH . '/database/migrate_v16.sql');
$sql = preg_replace('/^\s*--.*$/m', '', $sql);
$sql = preg_replace('/\n{2,}/', "\n", $sql);

$ok = 0;
$skip = 0;
foreach (explode(';', $sql) as $stmt) {
    $stmt = trim($stmt);
    if ($stmt === '') {
        continue;
    }
    try {
        Database::conn()->exec($stmt);
        $ok++;
    } catch (Throwable $e) {
        $skip++;
        echo "  bo qua: " . substr(preg_replace('/\s+/', ' ', $stmt), 0, 90) . " -> " . $e->getMessage() . "\n";
    }
}
echo "Migration V16: $ok câu lệnh OK, $skip bỏ qua\n";

foreach ([
    'service_prices',
    'phone_checks',
    'site_clones',
    'site_auto_builds',
    'sms_campaigns',
    'sms_targets',
    'sms_logs',
    'scraper_jobs',
] as $t) {
    $n = (int)Database::fetchColumn(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
        [$t]
    );
    echo "  $t: " . ($n > 0 ? 'OK' : 'THIEU') . "\n";
}

$prices = (int)Database::fetchColumn('SELECT COUNT(*) FROM service_prices');
echo "  service_prices rows: {$prices}\n";
