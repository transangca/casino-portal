<?php
/**
 * Test logic dịch vụ Tài khoản & Nick với mock API (không cần key thật).
 * Chạy: php tests/test_vuavia_logic.php
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

use App\Core\Crypto;
use App\Core\Database;
use App\Services\VuaviaService;

$pass = 0;
$fail = 0;

function ok(string $label, bool $cond, string $extra = ''): void
{
    global $pass, $fail;
    if ($cond) {
        echo "  [OK]   {$label}\n";
        $pass++;
    } else {
        echo "  [FAIL] {$label}" . ($extra !== '' ? " — {$extra}" : '') . "\n";
        $fail++;
    }
}

// ===== 1. Cấu hình =====
echo "\n== Cấu hình ==\n";

setting_set('vuavia_base_url', 'https://mock.local/api/v2', 'Tài khoản & Nick');
setting_set('vuavia_api_key', Crypto::encrypt('vav_test_0123456789abcdef0123456789abcdef'), 'Tài khoản & Nick');
setting_set('vuavia_service_on', '1', 'Tài khoản & Nick');
setting_set('vuavia_markup', '10', 'Tài khoản & Nick');

ok('baseUrl đọc đúng', VuaviaService::baseUrl() === 'https://mock.local/api/v2', VuaviaService::baseUrl());
ok('apiKey giải mã đúng', VuaviaService::apiKey() === 'vav_test_0123456789abcdef0123456789abcdef');
ok('configured = true', VuaviaService::configured() === true);
ok('enabled = true', VuaviaService::enabled() === true);
ok('markup = 10', VuaviaService::markup() === 10.0);

// API key trong DB phải được mã hoá (không lộ plaintext)
$raw = Database::fetchColumn('SELECT value FROM settings WHERE name = ?', ['vuavia_api_key']);
ok('API key trong DB được mã hoá', $raw !== 'vav_test_0123456789abcdef0123456789abcdef' && str_contains((string)$raw, 'vav_test') === false);

// ===== 2. Request không có key =====
echo "\n== Xử lý lỗi ==\n";
setting_set('vuavia_api_key', '', 'Tài khoản & Nick');
$res = VuaviaService::request('GET', '/wallet/balance');
ok('Không có key → MISSING_API_KEY', !$res['ok'] && $res['errorCode'] === 'MISSING_API_KEY', $res['errorCode']);

// ===== 3. Gọi API thật (mock server) =====
echo "\n== Gọi API (mock) ==\n";
$mockUrl = 'http://127.0.0.1:8921/api/v2';
setting_set('vuavia_base_url', $mockUrl, 'Tài khoản & Nick');
setting_set('vuavia_api_key', Crypto::encrypt('vav_test_0123456789abcdef0123456789abcdef'), 'Tài khoản & Nick');

$bal = VuaviaService::balance();
ok('balance = 150000', $bal['ok'] && (float)$bal['balance'] === 150000.0, json_encode($bal));

$products = VuaviaService::products(['refresh' => true]);
ok('products trả về 3 món', count($products) === 3, (string)count($products));
ok('product[0] đúng tên', ($products[0]['name'] ?? '') === 'Acc Việt Nữ 50-1000 Bạn Bè Trên 18 Tuổi');
ok('product[0] giá 160000', (float)($products[0]['price'] ?? 0) === 160000.0);
ok('product[0] còn 4 hàng', (int)($products[0]['stock_count'] ?? 0) === 4);
ok('product[1] hết hàng', (int)($products[1]['stock_count'] ?? 0) === 0);
ok('product[1] MANUAL', ($products[1]['delivery_type'] ?? '') === 'MANUAL');

// Đồng bộ DB
$sync = VuaviaService::syncProducts();
ok('syncProducts ok', $sync['ok'] === true, json_encode($sync));
ok('DB có 2 sản phẩm', (int)Database::fetchColumn('SELECT COUNT(*) FROM vuavia_products') >= 2);
ok('DB có 2 danh mục', (int)Database::fetchColumn('SELECT COUNT(*) FROM vuavia_categories') >= 2);

$cat = VuaviaService::categories();
ok('categories() trả về danh mục', count($cat) >= 2);

// ===== 4. Đặt hàng =====
echo "\n== Đặt hàng ==\n";

$db = Database::conn();
$st = $db->prepare('INSERT INTO users (username, email, password_hash, role, status, balance, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
$st->execute(['vua_' . time(), 'vua_' . time() . '@local.test', password_hash('Vua@2026', PASSWORD_DEFAULT), 'user', 'active', 10000000]);
$uid = (int)$db->lastInsertId();

$order = VuaviaService::createOrder($uid, '8', 2);
ok('đặt hàng thành công', $order['ok'] === true, json_encode($order));
ok('status = DELIVERED', ($order['status'] ?? '') === 'DELIVERED');
ok('totalAmount = 320000', (float)($order['total_amount'] ?? 0) === 320000.0);
ok('có 2 deliveredItems', count($order['deliveredItems']) === 2);
ok('item đúng định dạng acc|pass', str_contains($order['deliveredItems'][0] ?? '', '|'));

// Lưu đơn + deliveries
$oid = VuaviaService::saveOrder($uid, [
    'product_id'       => 8,
    'product_name'     => 'Acc Việt Nữ',
    'quantity'         => 2,
    'total_amount'     => 320000,
    'unit_price'       => 160000,
    'status'           => 'DELIVERED',
    'partner_order_id' => 101,
]);
VuaviaService::saveDeliveries($oid, $order['deliveredItems']);
ok('lưu đơn thành công', $oid > 0);

$row = VuaviaService::findOrder($uid, $oid);
ok('findOrder trả về đơn', $row !== null);
ok('có 2 deliveries', count($row['deliveries']) === 2);
ok('mật khẩu được giải mã đúng', VuaviaService::revealPassword($row['deliveries'][0]['password_enc']) === 'pass8_1');

// Mật khẩu trong DB phải mã hoá
$enc = Database::fetchColumn('SELECT password_enc FROM vuavia_deliveries WHERE order_id = ? LIMIT 1', [$oid]);
ok('mật khẩu trong DB được mã hoá', $enc !== 'pass8_1' && str_contains((string)$enc, 'pass8_1') === false);

// Đánh dấu đã nhận
ok('markRedeemed ok', VuaviaService::markRedeemed($uid, (int)$row['deliveries'][0]['id']) === true);
$again = VuaviaService::markRedeemed($uid, (int)$row['deliveries'][0]['id']);
ok('markRedeemed lần 2 không đổi', $again === false);

// ===== 5. Lỗi API =====
echo "\n== Lỗi API ==\n";
$err = VuaviaService::createOrder($uid, '9', 1);
ok('hết hàng → OUT_OF_STOCK', !$err['ok'] && $err['errorCode'] === 'OUT_OF_STOCK', json_encode($err));

$err2 = VuaviaService::createOrder($uid, '', 1);
ok('thiếu productId → INVALID_PRODUCT_ID', !$err2['ok'] && $err2['errorCode'] === 'INVALID_PRODUCT_ID');

// ===== 6. Thống kê =====
echo "\n== Thống kê ==\n";
$stats = VuaviaService::stats();
ok('stats có đủ khoá', isset($stats['orders'], $stats['delivered'], $stats['revenue'], $stats['products']));

// Cleanup
Database::query('DELETE FROM users WHERE id = ?', [$uid]);
Database::query('DELETE FROM vuavia_orders');
Database::query('DELETE FROM vuavia_deliveries');
Database::query('DELETE FROM vuavia_products');
Database::query('DELETE FROM vuavia_categories');
Database::query('DELETE FROM settings WHERE name LIKE "vuavia_%"');

echo "\n=== Kết quả: {$pass} pass, {$fail} fail ===\n";
exit($fail > 0 ? 1 : 0);
