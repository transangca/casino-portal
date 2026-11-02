<?php
/**
 * Test API endpoints mới (accounts/products, accounts/order) với API key thật.
 * Chạy: php tests/test_api_accounts_http.php
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/http_client.php';

use App\Core\Database;
use App\Services\ApiKeyService;

$base = 'http://127.0.0.1:8912';

reset_rate_limits();

$db = Database::conn();
$st = $db->prepare('INSERT INTO users (username, email, password_hash, role, status, balance, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
$st->execute(['apiacc_' . time(), 'apiacc_' . time() . '@local.test', password_hash('ApiAcc@2026', PASSWORD_DEFAULT), 'user', 'active', 10000000]);
$uid = (int)$db->lastInsertId();

// Tạo API key cho user
$res = ApiKeyService::create($uid, ['name' => 'Test API']);
$apiKey = (string)($res['key'] ?? '');
echo "API key: " . substr($apiKey, 0, 16) . "...\n";

if ($apiKey === '') {
    echo "[FAIL] Không tạo được API key\n";
    exit(1);
}

// Trỏ API đối tác sang mock + key giả
$upsert = "INSERT INTO settings (name, value, group_name, input_type) VALUES (?, ?, 'Tài khoản & Nick', 'text')
           ON DUPLICATE KEY UPDATE value = VALUES(value)";
$db->prepare($upsert)->execute(['vuavia_base_url', 'http://127.0.0.1:8921/api/v2']);
$db->prepare($upsert)->execute(['vuavia_api_key', \App\Core\Crypto::encrypt('vav_test_0123456789abcdef0123456789abcdef')]);
$db->prepare($upsert)->execute(['vuavia_service_on', '1']);
$db->prepare($upsert)->execute(['vuavia_markup', '10']);

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

function api(string $base, string $key, string $path, ?array $body = null): array
{
    $ch = curl_init($base . $path);
    $headers = ['X-API-Key: ' . $key, 'Accept: application/json'];
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => $headers,
    ];
    if ($body !== null) {
        $opts[CURLOPT_POST]           = true;
        $opts[CURLOPT_POSTFIELDS]     = json_encode($body);
        $headers[]                    = 'Content-Type: application/json';
        $opts[CURLOPT_HTTPHEADER]     = $headers;
    }
    curl_setopt_array($ch, $opts);
    $out = (string)curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, json_decode($out, true) ?: ['raw' => $out]];
}

// ===== 1. Không có key =====
echo "\n== Xác thực ==\n";
[$code, $j] = api($base, 'invalid_key_xxx', '/api/accounts/products');
chk('key sai → 401', $code === 401, "HTTP {$code}");
chk('trả error code', ($j['error']['code'] ?? '') === 'AUTH_INVALID', json_encode($j));

// ===== 2. Danh sách sản phẩm =====
echo "\n== GET /api/accounts/products ==\n";
[$code, $j] = api($base, $apiKey, '/api/accounts/products');
chk('HTTP 200', $code === 200, "HTTP {$code}");
chk('success = true', ($j['success'] ?? false) === true);
chk('có danh sách sản phẩm', is_array($j['data']['products'] ?? null) && count($j['data']['products']) > 0);
$first = $j['data']['products'][0] ?? [];
chk('có price & sell_price', isset($first['price'], $first['sell_price']));
chk('markup 10% → sell_price = price × 1.1', abs((float)$first['sell_price'] - ceil((float)$first['price'] * 1.1)) < 1,
    "price={$first['price']} sell={$first['sell_price']}");

// Lọc theo q
[$code, $j] = api($base, $apiKey, '/api/accounts/products?q=Facebook');
chk('lọc q=Facebook → 1 sản phẩm', count($j['data']['products'] ?? []) === 1, (string)count($j['data']['products'] ?? []));

// ===== 3. Đặt hàng =====
echo "\n== POST /api/accounts/order ==\n";
$before = (float)Database::fetchColumn('SELECT balance FROM users WHERE id = ?', [$uid]);
[$code, $j] = api($base, $apiKey, '/api/accounts/order', ['productId' => 8, 'quantity' => 1]);
chk('HTTP 200', $code === 200, "HTTP {$code}: " . json_encode($j));
chk('success = true', ($j['success'] ?? false) === true, json_encode($j));
chk('có id đơn', !empty($j['data']['id']));
chk('status DELIVERED', ($j['data']['status'] ?? '') === 'DELIVERED');
chk('có deliveredItems', !empty($j['data']['deliveredItems']));
$after = (float)Database::fetchColumn('SELECT balance FROM users WHERE id = ?', [$uid]);
chk('đã trừ tiền (160000 × 1.1)', abs(($before - $after) - 176000) < 1, "trừ " . ($before - $after));

// ===== 4. Lỗi: hết hàng =====
echo "\n== Lỗi ==\n";
[$code, $j] = api($base, $apiKey, '/api/accounts/order', ['productId' => 9, 'quantity' => 1]);
chk('hết hàng → 409', $code === 409, "HTTP {$code}");
chk('error code OUT_OF_STOCK', ($j['error']['code'] ?? '') === 'OUT_OF_STOCK', json_encode($j));

[$code, $j] = api($base, $apiKey, '/api/accounts/order', ['productId' => 999999, 'quantity' => 1]);
chk('sản phẩm không tồn tại → 404', $code === 404, "HTTP {$code}");

[$code, $j] = api($base, $apiKey, '/api/accounts/order', ['quantity' => 1]);
chk('thiếu productId → 422', $code === 422, "HTTP {$code}");

// ===== 5. Lịch sử & chi tiết =====
echo "\n== Lịch sử & chi tiết ==\n";
[$code, $j] = api($base, $apiKey, '/api/accounts/orders');
chk('HTTP 200', $code === 200);
chk('có đơn hàng', count($j['data']['orders'] ?? []) >= 1, (string)count($j['data']['orders'] ?? []));

$orderId = (int)($j['data']['orders'][0]['id'] ?? 0);
[$code, $j] = api($base, $apiKey, '/api/accounts/detail?id=' . $orderId);
chk('HTTP 200', $code === 200);
chk('chi tiết có deliveredItems', !empty($j['data']['deliveredItems']));
chk('mật khẩu được giải mã', ($j['data']['deliveredItems'][0]['password'] ?? '') === 'pass8_1', json_encode($j['data']['deliveredItems'] ?? []));

[$code, $j] = api($base, $apiKey, '/api/accounts/detail?id=999999');
chk('đơn không tồn tại → 404', $code === 404, "HTTP {$code}");

// ===== 6. Không lộ thông tin đối tác qua API =====
echo "\n== Không lộ đối tác qua API ==\n";
$raw = json_encode($j);
[$code, $j2] = api($base, $apiKey, '/api/accounts/products');
$raw2 = json_encode($j2);
chk('API không chứa "vuavia"', !str_contains(strtolower($raw2), 'vuavia'));
chk('API không chứa "vav_"', !str_contains($raw2, 'vav_'));
chk('API không chứa base URL đối tác', !str_contains($raw2, '127.0.0.1:8921'));

// Cleanup
Database::query('DELETE FROM api_clients WHERE user_id = ?', [$uid]);
Database::query('DELETE FROM users WHERE id = ?', [$uid]);
Database::query('DELETE FROM vuavia_orders');
Database::query('DELETE FROM vuavia_deliveries');
Database::query('DELETE FROM vuavia_products');
Database::query('DELETE FROM vuavia_categories');
Database::query('DELETE FROM settings WHERE name LIKE "vuavia_%"');

echo "\n=== Kết quả: {$pass} pass, {$fail} fail ===\n";
exit($fail > 0 ? 1 : 0);
