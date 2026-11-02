<?php
/**
 * Chạy migration V14 (nâng cấp dịch vụ Sale & tin nhắn hàng loạt).
 * Chạy: php database/migrate_v14.php
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

$sql = file_get_contents(BASE_PATH . '/database/migrate_v14.sql');
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
echo "Migration V14: $ok câu lệnh OK, $skip bỏ qua\n";

foreach ([
    'sale_account_verifications',
    'sale_imports',
] as $t) {
    $n = (int)Database::fetchColumn(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
        [$t]
    );
    echo "  $t: " . ($n > 0 ? 'OK' : 'THIEU') . "\n";
}

// Thêm cột status cho sale_accounts nếu chưa có
$cols = Database::fetchAll("SHOW COLUMNS FROM `sale_accounts`");
$names = array_column($cols, 'Field');
foreach (['status', 'verified_at', 'last_error'] as $c) {
    echo "  sale_accounts.$c: " . (in_array($c, $names, true) ? 'OK' : 'THIEU') . "\n";
}
