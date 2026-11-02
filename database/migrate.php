<?php
/**
 * Migration runner: chạy schema.sql (idempotent) + các ALTER bổ sung.
 * Chạy: php database/migrate.php
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

echo "Chạy schema...\n";
$sql = file_get_contents(BASE_PATH . '/database/schema.sql');

// Bỏ toàn bộ comment dòng trước khi tách câu lệnh
$sql = preg_replace('/^\s*--.*$/m', '', $sql);
$sql = preg_replace('/\n{2,}/', "\n", $sql);

// Tách câu lệnh theo dấu ";"
$statements = explode(';', $sql);
$ok = 0;
$skip = 0;
foreach ($statements as $stmt) {
    $stmt = trim($stmt);
    if ($stmt === '') {
        continue;
    }
    try {
        Database::conn()->exec($stmt);
        $ok++;
    } catch (Throwable $e) {
        $skip++;
        echo "  bo qua: " . substr(preg_replace('/\s+/', ' ', $stmt), 0, 70) . " -> " . $e->getMessage() . "\n";
    }
}
echo "  $ok câu lệnh OK, $skip bỏ qua\n";

// ---------- ALTER bổ sung cho các bảng đã tồn tại ----------
$alters = [
    'games' => [
        'ADD COLUMN `game_config` JSON DEFAULT NULL AFTER `sort_order`',
    ],
    'users' => [
        'ADD COLUMN `role_id` INT UNSIGNED DEFAULT NULL AFTER `role`',
        'ADD COLUMN `avatar` VARCHAR(255) DEFAULT NULL AFTER `full_name`',
        'ADD COLUMN `phone` VARCHAR(30) DEFAULT NULL AFTER `email`',
        'ADD COLUMN `telegram` VARCHAR(60) DEFAULT NULL AFTER `phone`',
        'ADD COLUMN `api_token` VARCHAR(255) DEFAULT NULL AFTER `telegram`',
    ],
    'settings' => [
        'MODIFY COLUMN `value` TEXT DEFAULT NULL',
    ],
];

foreach ($alters as $table => $list) {
    foreach ($list as $alter) {
        $col = preg_match('/COLUMN `(\w+)`/', $alter, $m) ? $m[1] : '';
        if ($col !== '') {
            $exists = Database::fetchColumn(
                "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
                [$table, $col]
            );
            if ($exists) {
                continue;
            }
        }
        try {
            Database::conn()->exec("ALTER TABLE `$table` $alter");
            echo "  + ALTER $table $col\n";
        } catch (Throwable $e) {
            echo "  ! ALTER $table $col: " . $e->getMessage() . "\n";
        }
    }
}

echo "\nMigration hoàn tất.\n";
