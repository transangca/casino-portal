<?php
/**
 * Chạy migration V6 (Bank ảo - HeyBank API v2).
 * Chạy: php database/migrate_v6.php
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

$sql = file_get_contents(BASE_PATH . '/database/migrate_v6.sql');
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
echo "Migration V6: $ok câu lệnh OK, $skip bỏ qua\n";

// Kiểm tra bảng đã tạo
foreach (['virtual_bank_accounts', 'virtual_bank_transactions', 'virtual_bank_orders'] as $t) {
    $n = (int)Database::fetchColumn(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
        [$t]
    );
    echo "  $t: " . ($n > 0 ? 'OK' : 'THIẾU') . "\n";
}
