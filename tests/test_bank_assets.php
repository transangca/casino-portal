<?php
/**
 * Kiểm tra logo ngân hàng + tra cứu VietQR.
 * Chạy: php tests/test_bank_assets.php
 */

require_once __DIR__ . '/bootstrap.php';

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

$codes = [
    'MB', 'VCB', 'TCB', 'ICB', 'BIDV', 'ACB', 'AGRI', 'VIB', 'TPB', 'SEAB',
    'SCB', 'PVB', 'ABB', 'LPB', 'VBB', 'VCC', 'SHB', 'MSB', 'OCB', 'BVB',
    'VRB', 'STB', 'IVB', 'NCB', 'HDB', 'EIB', 'BSP', 'GPB', 'CIMB', 'CITI',
    'HSBC', 'DBS', 'UOB', 'KBank', 'CAKE', 'MOMO', 'ZALO', 'VNPT', 'VIETTEL',
    'BAC', 'VAB', 'VBSP', 'TIMO', 'TNEX', 'PB', 'UB', 'M', 'G', 'P', 'D',
];

echo "== 1. Logo ngân hàng theo mã ==\n";
$missing = [];
foreach ($codes as $c) {
    $html = bank_logo($c);
    if ($html === '') {
        $missing[] = $c;
        continue;
    }
    $pass++;
}
check('tất cả mã ngân hàng phổ biến có logo', empty($missing), implode(', ', $missing));

echo "\n== 2. Tên hiển thị ==\n";
check('MB → MB Bank', bank_display_name('MB') === 'MB Bank', bank_display_name('MB'));
check('VCB → Vietcombank', bank_display_name('VCB') === 'Vietcombank', bank_display_name('VCB'));
check('mã lạ → trả về chính nó', bank_display_name('ZZZZ') === 'ZZZZ', bank_display_name('ZZZZ'));

echo "\n== 3. Bỏ hậu tố loại tài khoản ==\n";
check('MB-PRO → mb', bank_code_slug('MB-PRO') === 'mb', (string)bank_code_slug('MB-PRO'));
check('VCB-PRO → vcb', bank_code_slug('VCB-PRO') === 'vcb', (string)bank_code_slug('VCB-PRO'));

echo "\n== 4. File thực tế tồn tại ==\n";
$badFiles = [];
foreach ($codes as $c) {
    $slug = bank_code_slug($c);
    if ($slug === null) {
        continue;
    }
    $p1 = BASE_PATH . '/public/assets/img/brands/bank/' . $slug . '.png';
    $p2 = BASE_PATH . '/public/assets/img/brands/bank/' . $slug . '.svg';
    if (!is_file($p1) && !is_file($p2)) {
        $badFiles[] = $c . '→' . $slug;
    }
}
check('mọi logo ngân hàng có file thật', empty($badFiles), implode(', ', $badFiles));

echo "\n== 5. Brand manifest đầy đủ ==\n";
$manifest = brand_manifest();
$noFile = [];
foreach ($manifest as $slug => $meta) {
    $rel = 'img/brands/' . $meta['group'] . '/' . $meta['file'];
    if (!is_file(BASE_PATH . '/public/assets/' . $rel . '.svg') && !is_file(BASE_PATH . '/public/assets/' . $rel . '.png')) {
        // thử fallback providers
        $found = false;
        foreach (['svg', 'png', 'jpg', 'jpeg', 'webp'] as $ext) {
            if (is_file(BASE_PATH . '/public/assets/img/providers/' . $meta['file'] . '.' . $ext)) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            $noFile[] = $slug . '→' . $meta['file'];
        }
    }
}
check('mọi brand có file logo (kể cả fallback)', empty($noFile), implode(', ', $noFile));

echo "\n=== Kết quả: {$pass} pass, {$fail} fail ===\n";
exit($fail > 0 ? 1 : 0);
