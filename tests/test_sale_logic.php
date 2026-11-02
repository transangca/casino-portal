<?php
/**
 * Test logic dịch vụ Sale: xác minh 6 số + import file .txt/.csv.
 * Chạy: php tests/test_sale_logic.php
 */

require_once __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\SaleService;

$pass = 0;
$fail = 0;

function check(string $name, bool $ok, string $msg = ''): void
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
        echo "  [OK]   {$name}\n";
    } else {
        $fail++;
        echo "  [FAIL] {$name}" . ($msg !== '' ? " -> {$msg}" : '') . "\n";
    }
}

// Tạo user test
$db = Database::conn();
$email = 'sale_test_' . time() . '@local.test';
$cols = array_column($db->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_ASSOC), 'Field');
$ins = 'INSERT INTO users (username, email, password_hash, role, status, balance, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())';
$st = $db->prepare($ins);
$st->execute(['saletest' . time(), $email, 'x', 'user', 'active', 0]);
$userId = (int)$db->lastInsertId();
echo "User test ID: {$userId}\n\n";

// ---------- 1. Tạo tài khoản ----------
echo "== 1. Tạo tài khoản ==\n";
$res = SaleService::createAccount($userId, [
    'platform' => 'telegram',
    'account'  => '@test_acc',
    'secret'   => 'secret123',
]);
check('tạo tài khoản thành công', !empty($res['ok']), json_encode($res));
$accountId = (int)($res['id'] ?? 0);
check('có account id', $accountId > 0);

// Kiểm tra status mặc định = pending
$row = Database::fetch('SELECT status FROM sale_accounts WHERE id = ?', [$accountId]);
check('status mặc định = pending', (string)$row['status'] === 'pending', (string)$row['status']);

// ---------- 2. Xác minh ----------
echo "\n== 2. Xác minh 6 số ==\n";
$v = SaleService::createVerification($userId, $accountId);
check('tạo mã xác minh', !empty($v['ok']), json_encode($v));
check('mã có 6 chữ số', isset($v['code']) && preg_match('/^\d{6}$/', (string)$v['code']));

// Sai mã
$bad = SaleService::verifyAccount($userId, $accountId, '000000');
$expectWrong = ((string)$v['code'] !== '000000');
check('mã sai bị từ chối', $expectWrong ? empty($bad['ok']) : true, json_encode($bad));

// Đúng mã
$good = SaleService::verifyAccount($userId, $accountId, (string)$v['code']);
check('mã đúng xác minh thành công', !empty($good['ok']), json_encode($good));
$row = Database::fetch('SELECT status FROM sale_accounts WHERE id = ?', [$accountId]);
check('status = verified', (string)$row['status'] === 'verified', (string)$row['status']);

// Tài khoản chưa xác minh không chạy được chiến dịch
$acc2 = SaleService::createAccount($userId, [
    'platform' => 'facebook',
    'account'  => 'fb_test_acc',
    'secret'   => 'pw',
]);
$acc2Id = (int)($acc2['id'] ?? 0);
$camp = SaleService::createCampaign($userId, [
    'message'     => 'test',
    'target_list' => "0912345678\n0987654321",
    'account_id'  => $acc2Id,
]);
check('tạo chiến dịch thành công', !empty($camp['ok']), json_encode($camp));
$start = SaleService::startCampaign($userId, (int)$camp['id']);
check('chưa xác minh thì không chạy được', empty($start['ok']), json_encode($start));

// Reset
SaleService::resetVerification($userId, $accountId);
$row = Database::fetch('SELECT status FROM sale_accounts WHERE id = ?', [$accountId]);
check('reset về pending', (string)$row['status'] === 'pending', (string)$row['status']);

// ---------- 3. Import file ----------
echo "\n== 3. Import file ==\n";
$tmp = sys_get_temp_dir() . '/sale_import_test.csv';
file_put_contents($tmp, "acc1,pass1\nacc2,pass2\nacc3,pass3\nacc1,dup\n,missingacct\n");
$parsed = SaleService::parseImportFile($tmp, 'test.csv');
check('parse file csv thành công', !empty($parsed['ok']), json_encode($parsed));
check('3 dòng hợp lệ', count($parsed['valid']) === 3, (string)count($parsed['valid'] ?? []));
check('2 dòng không hợp lệ (trùng + thiếu)', count($parsed['invalid']) === 2, (string)count($parsed['invalid'] ?? []));

$imp = SaleService::importAccounts($userId, 'zalo', $parsed['valid'], 'test.csv');
check('import thành công', !empty($imp['ok']), json_encode($imp));
check('import 3 tài khoản', (int)($imp['added'] ?? 0) === 3, json_encode($imp));

// Import lại -> phải skip trùng
$imp2 = SaleService::importAccounts($userId, 'zalo', $parsed['valid'], 'test.csv');
check('import lại bỏ qua trùng', (int)($imp2['skipped'] ?? 0) === 3, json_encode($imp2));

// File .txt phân tách bằng |
$tmp2 = sys_get_temp_dir() . '/sale_import_test.txt';
file_put_contents($tmp2, "t1|p1\nt2|p2|x\n");
$p2 = SaleService::parseImportFile($tmp2, 'test.txt');
check('parse .txt thành công', !empty($p2['ok']));
check('.txt 2 dòng hợp lệ', count($p2['valid'] ?? []) === 2, (string)count($p2['valid'] ?? []));

// File sai định dạng
$p3 = SaleService::parseImportFile($tmp2, 'test.exe');
check('từ chối file không hỗ trợ', empty($p3['ok']));

// Lịch sử import
$imports = SaleService::imports($userId, 5);
check('có lịch sử import', count($imports) >= 2, (string)count($imports));

// ---------- 4. Brand logos ----------
echo "\n== 4. Logo thương hiệu ==\n";
foreach (['telegram', 'facebook', 'zalo'] as $p) {
    $slug = SaleService::PLATFORM_BRANDS[$p] ?? $p;
    $url = brand_logo_url($slug);
    check("logo {$p} tồn tại", $url !== '', $slug);
}

// ---------- Cleanup ----------
Database::query('DELETE FROM sale_account_verifications WHERE user_id = ?', [$userId]);
Database::query('DELETE FROM sale_imports WHERE user_id = ?', [$userId]);
Database::query('DELETE FROM sale_campaigns WHERE user_id = ?', [$userId]);
Database::query('DELETE FROM sale_accounts WHERE user_id = ?', [$userId]);
Database::query('DELETE FROM users WHERE id = ?', [$userId]);
@unlink($tmp);
@unlink($tmp2);

echo "\n=== Kết quả: {$pass} pass, {$fail} fail ===\n";
exit($fail > 0 ? 1 : 0);
