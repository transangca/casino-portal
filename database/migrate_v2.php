<?php
/**
 * Chạy migration v2 (schema mới) — an toàn, chạy lại nhiều lần được.
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/app/Support/helpers.php';
spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) return;
    $p = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($p)) require_once $p;
});

use App\Core\Database;

$sql = file_get_contents(BASE_PATH . '/database/migrate_v2.sql');

// Tách các câu lệnh, bỏ qua comment (chấp nhận cả \r\n và \n)
$sql = preg_replace('/^\s*--.*$/m', '', $sql);
$sql = str_replace("\r\n", "\n", $sql);
$statements = array_filter(array_map('trim', explode(";\n", $sql . "\n")));

$ok = 0; $fail = 0;
foreach ($statements as $st) {
    $st = trim($st);
    if ($st === '' || !preg_match('/^(CREATE|ALTER|INSERT|UPDATE|DELETE|SET)/i', $st)) {
        continue;
    }
    try {
        Database::query($st);
        $ok++;
    } catch (\Throwable $e) {
        $fail++;
        echo "FAIL: " . substr(preg_replace('/\s+/', ' ', $st), 0, 110) . "\n   -> " . $e->getMessage() . "\n";
    }
}

echo "\nOK: $ok | FAIL: $fail\n";

// Kiểm tra các cột mới
echo "\n--- providers columns ---\n";
foreach (Database::fetchAll('SHOW COLUMNS FROM providers') as $c) {
    echo '  ' . $c['Field'] . "\n";
}
echo "--- game_categories ---\n";
foreach (Database::fetchAll('SELECT code,name FROM game_categories') as $c) {
    echo "  {$c['code']} = {$c['name']}\n";
}
