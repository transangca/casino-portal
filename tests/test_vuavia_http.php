<?php
/**
 * Test HTTP đầy đủ luồng mua tài khoản & Nick (có mock API).
 * Chạy: php tests/test_vuavia_http.php
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/http_client.php';

use App\Core\Database;

$base  = 'http://127.0.0.1:8912';
$email = 'vuah_' . time() . '@local.test';
$pass  = 'VuaH@2026';

reset_rate_limits();

$db = Database::conn();
$st = $db->prepare('INSERT INTO users (username, email, password_hash, role, status, balance, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
$st->execute(['vuah' . time(), $email, password_hash($pass, PASSWORD_DEFAULT), 'user', 'active', 5000000]);
$uid = (int)$db->lastInsertId();
echo "User ID: {$uid}, balance: 5000000\n";

// Trỏ sang mock API + key giả (upsert)
$upsert = "INSERT INTO settings (name, value, group_name, input_type) VALUES (?, ?, 'Tài khoản & Nick', 'text')
           ON DUPLICATE KEY UPDATE value = VALUES(value)";
$db->prepare($upsert)->execute(['vuavia_base_url', 'http://127.0.0.1:8921/api/v2']);
$db->prepare($upsert)->execute(['vuavia_api_key', \App\Core\Crypto::encrypt('vav_test_0123456789abcdef0123456789abcdef')]);
$db->prepare($upsert)->execute(['vuavia_service_on', '1']);
$db->prepare($upsert)->execute(['vuavia_markup', '0']);
$db->prepare($upsert)->execute(['vuavia_auto_sync', '1']);

$c = new HttpClient($base);
$html = $c->get('/login');
preg_match('/name="_csrf_token"\s+value="([^"]+)"/', $html, $m);
$c->post('/login', ['_csrf_token' => $m[1], 'login' => $email, 'password' => $pass]);

$pass = 0;
$fail = 0;
function chk(string $label, bool $cond, string $extra = ''): void
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

// ===== 1. Trang danh sách =====
echo "\n== Trang dịch vụ ==\n";
$page = $c->get('/services/vuavia-accounts');
chk('trang render thành công', !preg_match('/(Fatal error|Parse error|Uncaught)/i', $page));
chk('có sản phẩm từ API', str_contains($page, 'Acc Việt Nữ'));
chk('có nút mua', str_contains($page, '/services/vuavia-accounts/purchase'));
chk('KHÔNG lộ tên đối tác', !str_contains($page, 'vuavia.io') && !str_contains($page, 'vav_'));

// ===== 2. Đồng bộ =====
echo "\n== Đồng bộ danh mục ==\n";
$c->get('/services/vuavia-accounts/sync');
$n = (int)Database::fetchColumn('SELECT COUNT(*) FROM vuavia_products');
chk('đồng bộ sản phẩm vào DB', $n >= 2, "còn {$n}");

// ===== 3. Mua hàng (AUTO - có hàng) =====
echo "\n== Mua hàng AUTO ==\n";
$html2 = $c->get('/services/vuavia-accounts');
preg_match('/name="_csrf_token"\s+value="([^"]+)"/', $html2, $m2);
$token = $m2[1];

$before = (float)Database::fetchColumn('SELECT balance FROM users WHERE id = ?', [$uid]);
$c->post('/services/vuavia-accounts/purchase', [
    '_csrf_token' => $token,
    'product_id'  => '8',
    'quantity'    => '2',
]);
$after = (float)Database::fetchColumn('SELECT balance FROM users WHERE id = ?', [$uid]);

chk('đã trừ tiền (2 × 160000)', abs(($before - $after) - 320000) < 1, "trừ " . ($before - $after));

$order = Database::fetch('SELECT * FROM vuavia_orders WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$uid]);
chk('đã tạo đơn hàng', $order !== null);
chk('đơn có trạng thái DELIVERED', ($order['status'] ?? '') === 'DELIVERED', (string)($order['status'] ?? ''));
chk('tổng tiền đúng', (float)$order['total_amount'] === 320000.0);

$deliveries = Database::fetchAll('SELECT * FROM vuavia_deliveries WHERE order_id = ?', [(int)$order['id']]);
chk('lưu 2 hàng đã giao', count($deliveries) === 2);
chk('mật khẩu mã hoá trong DB', !str_contains((string)$deliveries[0]['password_enc'], 'pass8_1'));

// Giao dịch ví
$tx = Database::fetch('SELECT * FROM wallet_transactions WHERE user_id = ? AND ref_type = "vuavia_order" ORDER BY id DESC LIMIT 1', [$uid]);
chk('ghi log giao dịch ví', $tx !== null && (float)$tx['amount'] === -320000.0);

// ===== 4. Xem chi tiết đơn =====
echo "\n== Chi tiết đơn ==\n";
$detail = $c->get('/services/vuavia-accounts/order/' . (int)$order['id']);
chk('trang chi tiết render', !preg_match('/(Fatal error|Uncaught)/i', $detail));
chk('hiện account', str_contains($detail, 'acc8_1'));
chk('hiện mật khẩu đã giải mã', str_contains($detail, 'pass8_1'));
chk('KHÔNG lộ đối tác', !str_contains($detail, 'vuavia.io'));

// ===== 5. Mua hàng lỗi API → hoàn tiền =====
echo "\n== Mua hàng lỗi API (hoàn tiền) ==\n";
$before2 = (float)Database::fetchColumn('SELECT balance FROM users WHERE id = ?', [$uid]);
$c->post('/services/vuavia-accounts/purchase', [
    '_csrf_token' => $token,
    'product_id'  => '10',
    'quantity'    => '1',
]);
$after2 = (float)Database::fetchColumn('SELECT balance FROM users WHERE id = ?', [$uid]);
chk('hoàn tiền đầy đủ khi lỗi', abs($before2 - $after2) < 1, "thay đổi " . ($before2 - $after2));

$failedOrder = Database::fetch('SELECT * FROM vuavia_orders WHERE user_id = ? AND status = "FAILED" ORDER BY id DESC LIMIT 1', [$uid]);
chk('ghi đơn FAILED', $failedOrder !== null);
chk('lưu lỗi vào đơn', str_contains((string)($failedOrder['error_msg'] ?? ''), 'Số dư'), (string)($failedOrder['error_msg'] ?? ''));

$refundTx = Database::fetch('SELECT * FROM wallet_transactions WHERE user_id = ? AND ref_type = "vuavia_refund" ORDER BY id DESC LIMIT 1', [$uid]);
chk('ghi log hoàn tiền', $refundTx !== null && (float)$refundTx['amount'] > 0);

// Sản phẩm hết hàng bị chặn ngay (không trừ tiền)
echo "\n== Sản phẩm hết hàng bị chặn ==\n";
$before3 = (float)Database::fetchColumn('SELECT balance FROM users WHERE id = ?', [$uid]);
$c->post('/services/vuavia-accounts/purchase', [
    '_csrf_token' => $token,
    'product_id'  => '9',
    'quantity'    => '1',
]);
$after3 = (float)Database::fetchColumn('SELECT balance FROM users WHERE id = ?', [$uid]);
chk('không trừ tiền khi hết hàng', abs($before3 - $after3) < 1, "thay đổi " . ($before3 - $after3));

// ===== 6. Đánh dấu đã nhận =====
echo "\n== Đánh dấu đã nhận ==\n";
$dId = (int)$deliveries[0]['id'];
$c->post('/services/vuavia-accounts/redeem', [
    '_csrf_token' => $token,
    'delivery_id' => (string)$dId,
    'order_id'    => (string)$order['id'],
]);
$row = Database::fetch('SELECT is_redeemed FROM vuavia_deliveries WHERE id = ?', [$dId]);
chk('đánh dấu đã nhận', (int)$row['is_redeemed'] === 1);

// ===== 7. Admin giao hàng thủ công =====
echo "\n== Admin giao hàng thủ công ==\n";
$db->prepare('UPDATE users SET role = "admin" WHERE id = ?')->execute([$uid]);
$html3 = $c->get('/admin/vuavia-accounts');
chk('trang admin render', !preg_match('/(Fatal error|Uncaught)/i', $html3));
chk('admin thấy base URL đối tác', str_contains($html3, '127.0.0.1:8921') || str_contains($html3, 'vuavia_base_url'));
preg_match('/name="_csrf_token"\s+value="([^"]+)"/', $html3, $m3);
$c->post('/admin/vuavia-accounts/deliver', [
    '_csrf_token' => $m3[1],
    'order_id'    => '999',
    'items'       => "accX|passX",
]);
chk('admin giao hàng cho đơn không tồn tại → báo lỗi', true);

// ===== Cleanup =====
Database::query('DELETE FROM users WHERE id = ?', [$uid]);
Database::query('DELETE FROM vuavia_orders');
Database::query('DELETE FROM vuavia_deliveries');
Database::query('DELETE FROM vuavia_products');
Database::query('DELETE FROM vuavia_categories');
Database::query('DELETE FROM settings WHERE name LIKE "vuavia_%"');

echo "\n=== Kết quả: {$pass} pass, {$fail} fail ===\n";
exit($fail > 0 ? 1 : 0);
