<?php
/**
 * Cron Auto Bank — quét tự động tất cả tài khoản ngân hàng.
 *
 * Dùng cho Windows Task Scheduler hoặc cron Linux:
 *   php database/cron_autobank.php
 *
 * Gợi ý cron (mỗi 1-5 phút):
 *   * * * * * php /path/to/database/cron_autobank.php >> /var/log/autobank.log 2>&1
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
use App\Services\AutoBankService;

// Không cho chạy song song (tránh đối soát trùng)
$lockFile = BASE_PATH . '/storage/autobank_cron.lock';
if (!is_dir(dirname($lockFile))) {
    @mkdir(dirname($lockFile), 0775, true);
}
$lock = @fopen($lockFile, 'c');
if ($lock && !flock($lock, LOCK_EX | LOCK_NB)) {
    echo "[" . date('Y-m-d H:i:s') . "] Đang có tiến trình khác chạy — bỏ qua.\n";
    exit(0);
}

try {
    $accounts = AutoBankService::allAccounts();
    $active   = array_values(array_filter($accounts, fn($a) => (int)$a['is_active'] === 1));

    if (!$active) {
        echo "[" . date('Y-m-d H:i:s') . "] Không có tài khoản nào đang bật.\n";
        exit(0);
    }

    $total = $ok = $failed = $new = $approved = 0;
    $errors = [];

    foreach ($active as $acc) {
        $total++;
        $res = AutoBankService::syncAccount((int)$acc['id'], true);

        if ($res['ok']) {
            $ok++;
            $new      += (int)($res['new'] ?? 0);
            $approved += (int)($res['matched'] ?? 0);
            echo "[" . date('Y-m-d H:i:s') . "] OK  " . $acc['label']
                . " — moi=" . $res['new']
                . " duyet=" . ($res['matched'] ?? 0) . "\n";
        } else {
            $failed++;
            $errors[] = $acc['label'] . ': ' . $res['message'];
            echo "[" . date('Y-m-d H:i:s') . "] ERR " . $acc['label']
                . " — " . $res['message'] . "\n";
        }
    }

    echo "[" . date('Y-m-d H:i:s') . "] Hoan tat: {$ok}/{$total} tai khoan, "
        . "{$new} giao dich moi, {$approved} phieu nap tien duyet.\n";
} catch (Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] LOI: " . $e->getMessage() . "\n";
    exit(1);
} finally {
    if ($lock) {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}
