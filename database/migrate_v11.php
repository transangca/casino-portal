<?php
/**
 * Chạy migration V11 (dịch vụ SMM - trungtammmo.vn).
 * Chạy: php database/migrate_v11.php
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

$sql = file_get_contents(BASE_PATH . '/database/migrate_v11.sql');
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
        echo "  bo qua: " . substr(preg_replace('/\s+/', ' ', $stmt), 0, 80) . " -> " . $e->getMessage() . "\n";
    }
}
echo "Migration V11: $ok câu lệnh OK, $skip bỏ qua\n";

// ---------- Thêm cột giảm giá đại lý nếu bảng key đã tồn tại từ trước ----------
$hasDisc = (int)Database::fetchColumn(
    "SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ttmmo_api_keys' AND COLUMN_NAME = 'agent_discount_percent'"
);
if ($hasDisc === 0) {
    try {
        Database::conn()->exec(
            'ALTER TABLE `ttmmo_api_keys` ADD COLUMN `agent_discount_percent` DECIMAL(6,2) NOT NULL DEFAULT 0 AFTER `is_active`'
        );
        echo "  + ALTER ttmmo_api_keys agent_discount_percent\n";
    } catch (Throwable $e) {
        echo "  ! ALTER ttmmo_api_keys agent_discount_percent: " . $e->getMessage() . "\n";
    }
}

foreach (['ttmmo_api_keys', 'smm_categories', 'smm_services', 'smm_orders'] as $t) {
    $n = (int)Database::fetchColumn(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
        [$t]
    );
    echo "  $t: " . ($n > 0 ? 'OK' : 'THIEU') . "\n";
}
