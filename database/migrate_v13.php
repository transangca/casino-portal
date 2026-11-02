<?php
/**
 * Chạy migration V13 (dịch vụ VPS StoreVPS / Virtualizor - XVPS).
 * Chạy: php database/migrate_v13.php
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

$sql = file_get_contents(BASE_PATH . '/database/migrate_v13.sql');
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
echo "Migration V13: $ok câu lệnh OK, $skip bỏ qua\n";

foreach ([
    'vps_vz_products',
    'vps_vz_operating_systems',
    'vps_vz_locations',
    'vps_vz_orders',
    'vps_vz_instances',
    'vps_vz_actions',
] as $t) {
    $n = (int)Database::fetchColumn(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
        [$t]
    );
    echo "  $t: " . ($n > 0 ? 'OK' : 'THIEU') . "\n";
}
