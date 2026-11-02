<?php
define('BASE_PATH', __DIR__);
require BASE_PATH . '/app/Core/Database.php';
require BASE_PATH . '/app/Support/helpers.php';

use App\Core\Database;

foreach (['ads_banners', 'settings', 'design_fonts'] as $t) {
    try {
        $rows = Database::fetchAll("SELECT * FROM `$t` LIMIT 20");
        echo "=== $t (" . count($rows) . ") ===\n";
        foreach ($rows as $r) {
            $s = json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            echo substr($s, 0, 400) . "\n";
        }
    } catch (Throwable $e) {
        echo "$t: " . $e->getMessage() . "\n";
    }
}
