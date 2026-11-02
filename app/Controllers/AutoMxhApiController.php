<?php
namespace App\Controllers;

use App\Core\Database;
use App\Services\AutoMxhService;

/**
 * Proxy API Auto MXH — đối tác dùng apikey riêng của user để đấu vào hệ thống.
 *
 * Base: /api/external/automxh
 *
 * Xác thực (theo thứ tự ưu tiên):
 *   header  x-api-key
 *   header  Authorization: Bearer {apikey}
 *   query   ?apikey= / ?api_key= / ?key=
 *   body    apikey / api_key / key
 *
 * Endpoint (giống hệt Auto MXH):
 *   GET  /services?category=&search=
 *   GET  /catalog?search=
 *   GET  /category/{slug}
 *   POST /quote
 *   POST /order  (alias /add)
 *   GET  /status?order=
 *   GET  /orders?limit=&product_ids=
 *   GET  /balance
 *
 * Cũng hỗ trợ endpoint tổng: GET/POST /api/external/automxh?action=services|balance|status|add
 */
class AutoMxhApiController
{
    /**
     * Router điều hướng các endpoint Auto MXH.
     */
    public function handle(string $path = ''): void
    {
        $path   = trim($path, '/');
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        // Lấy apikey từ mọi nguồn
        $apikey = $this->extractApiKey();
        if ($apikey === '') {
            $this->fail('Thiếu API key. Gửi header x-api-key hoặc Authorization: Bearer.', 401);
            return;
        }

        // Tra cứu user gắn với apikey
        $user = Database::fetch(
            'SELECT u.id, u.username, u.balance, u.status, u.role
             FROM ttmmo_api_keys k
             JOIN users u ON u.id = k.user_id
             WHERE k.apikey = ? AND k.is_active = 1
             LIMIT 1',
            [$apikey]
        );
        if (!$user) {
            $this->fail('apikey không hợp lệ', 401);
            return;
        }
        if ((string)$user['status'] !== 'active') {
            $this->fail('Tài khoản đang bị khoá. Vui lòng liên hệ admin.', 403);
            return;
        }

        // Admin được kiểm tra đơn của mọi người để vận hành
        $isAdmin = in_array((string)$user['role'], ['admin', 'operator'], true);

        // Endpoint tổng dùng ?action=
        $action = strtolower(trim((string)($_GET['action'] ?? $_POST['action'] ?? '')));

        if ($path === '') {
            $path = $action;
        }

        // Chuẩn hoá alias
        $path = match ($path) {
            'add'                => 'order',
            'services.php'       => 'services',
            'catalog.php'        => 'catalog',
            'quote.php'          => 'quote',
            'order.php'          => 'order',
            'status.php'         => 'status',
            'orders.php'         => 'orders',
            'balance.php'        => 'balance',
            default              => $path,
        };

        // Tách category/{slug}
        $slug = '';
        if (str_starts_with($path, 'category/')) {
            $slug  = trim(substr($path, 9), '/');
            $path  = 'category';
        }

        $body = $this->jsonBody();

        switch ($path) {
            case 'services':
                $this->services($apikey);
                return;

            case 'catalog':
                $this->catalog($apikey);
                return;

            case 'category':
                if ($slug === '') {
                    $this->fail('Thiếu slug category.', 400);
                    return;
                }
                $this->category($apikey, $slug);
                return;

            case 'quote':
                $this->quote($apikey, $body);
                return;

            case 'order':
                $this->order($apikey, $user, $body);
                return;

            case 'status':
                $this->status($apikey, $user, $isAdmin);
                return;

            case 'orders':
                $this->orders($apikey, $user, $isAdmin);
                return;

            case 'balance':
                $this->balance($apikey, $user);
                return;

            default:
                $this->fail('Endpoint không tồn tại: /' . $path, 404);
        }
    }

    // ================= Endpoint handlers =================

    private function services(string $apikey): void
    {
        $res = $this->proxy($apikey, 'GET', '/services', [], [
            'category' => (string)($_GET['category'] ?? ''),
            'search'   => (string)($_GET['search'] ?? ''),
        ]);

        if ($res['ok']) {
            $this->syncLocal((array)($res['data']['data'] ?? []));
        }
        $this->relay($res);
    }

    private function catalog(string $apikey): void
    {
        $res = $this->proxy($apikey, 'GET', '/catalog', [], [
            'search' => (string)($_GET['search'] ?? ''),
        ]);

        $this->relay($res);
    }

    private function category(string $apikey, string $slug): void
    {
        $res = $this->proxy($apikey, 'GET', '/category/' . rawurlencode($slug));
        $this->relay($res);
    }

    private function quote(string $apikey, array $body): void
    {
        $variantId = (int)($body['variant_id'] ?? $body['variant'] ?? $body['service'] ?? 0);
        if ($variantId <= 0) {
            $this->fail('Thiếu variant_id', 400);
            return;
        }

        $payload = ['variant_id' => $variantId];
        $productId = (int)($body['product_id'] ?? 0);
        if ($productId > 0) {
            $payload['product_id'] = $productId;
        }

        $res = $this->proxy($apikey, 'POST', '/quote', $payload);
        $this->relay($res);
    }

    private function order(string $apikey, array $user, array $body): void
    {
        $variantId = (int)($body['service'] ?? $body['variant_id'] ?? $body['variant'] ?? 0);
        $link      = trim((string)($body['link'] ?? ''));
        $productId = (int)($body['product_id'] ?? 0);

        if ($variantId <= 0) {
            $this->fail('Thiếu variant_id', 400);
            return;
        }
        if ($link === '') {
            $this->fail('Thiếu link', 400);
            return;
        }

        $payload = [
            'service'    => $variantId,
            'variant_id' => $variantId,
            'link'       => $link,
        ];
        if ($productId > 0) {
            $payload['product_id'] = $productId;
        }
        foreach (['buyer_info', 'custom_value'] as $f) {
            $v = trim((string)($body[$f] ?? ''));
            if ($v !== '') {
                $payload[$f] = $v;
            }
        }

        $res = $this->proxy($apikey, 'POST', '/order', $payload);
        $this->relay($res);
    }

    private function status(string $apikey, array $user, bool $isAdmin): void
    {
        $orderId = (int)($_GET['order'] ?? $_GET['id'] ?? $_POST['order'] ?? 0);
        if ($orderId <= 0) {
            $this->fail('Thiếu order', 400);
            return;
        }

        // User thường chỉ check được đơn của chính mình
        if (!$isAdmin) {
            $owned = Database::fetchColumn(
                'SELECT id FROM automxh_orders WHERE remote_order = ? AND user_id = ? LIMIT 1',
                [$orderId, (int)$user['id']]
            );
            if (!$owned) {
                $this->fail('Bạn chỉ được kiểm tra trạng thái các đơn Auto MXH của chính mình', 403);
                return;
            }
        }

        $res = $this->proxy($apikey, 'GET', '/status', [], ['order' => $orderId]);
        $this->relay($res);
    }

    private function orders(string $apikey, array $user, bool $isAdmin): void
    {
        $limit = max(1, min(100, (int)($_GET['limit'] ?? 50)));
        $query = ['limit' => $limit];
        $productIds = trim((string)($_GET['product_ids'] ?? ''));
        if ($productIds !== '') {
            $query['product_ids'] = $productIds;
        }

        $res = $this->proxy($apikey, 'GET', '/orders', [], $query);
        $this->relay($res);
    }

    private function balance(string $apikey, array $user): void
    {
        $res = $this->proxy($apikey, 'GET', '/balance');
        $this->relay($res);
    }

    // ================= Helpers =================

    /**
     * Gọi API đối tác bằng apikey của user.
     */
    private function proxy(string $apikey, string $method, string $endpoint, array $payload = [], array $query = []): array
    {
        $url = AutoMxhService::baseUrl() . '/' . ltrim($endpoint, '/');
        if ($query) {
            $clean = array_filter($query, static fn($v) => $v !== '' && $v !== null);
            if ($clean) {
                $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($clean);
            }
        }

        $headers = [
            'x-api-key: ' . $apikey,
            'Authorization: Bearer ' . $apikey,
            'Accept: application/json',
        ];

        $opts = ['timeout' => 30, 'retries' => 1, 'allow_error' => true];

        if ($method === 'POST') {
            $headers[] = 'Content-Type: application/json';
            $body = \App\Services\HttpClient::postJson($url, $payload, $headers, $opts);
        } else {
            $body = \App\Services\HttpClient::get($url, $headers, $opts);
        }

        if ($body === null) {
            return ['ok' => false, 'payload' => ['success' => false, 'message' => 'Không kết nối được tới API Auto MXH.'], 'status' => 502];
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'payload' => ['success' => false, 'message' => 'API trả về dữ liệu không hợp lệ.'], 'status' => 502];
        }

        return [
            'ok'      => (bool)($decoded['success'] ?? false),
            'payload' => $decoded,
            'status'  => (bool)($decoded['success'] ?? false) ? 200 : 400,
        ];
    }

    /**
     * Trả nguyên payload của đối tác về cho bên gọi.
     */
    private function relay(array $res): void
    {
        http_response_code($res['status']);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($res['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Lưu nhanh danh mục vào DB khi có đối tác gọi /services.
     */
    private function syncLocal(array $variants): void
    {
        if (!$variants) {
            return;
        }
        try {
            foreach ($variants as $v) {
                if (!is_array($v)) {
                    continue;
                }
                $vid = (int)($v['variant_id'] ?? $v['id'] ?? 0);
                if ($vid <= 0) {
                    continue;
                }
                $cat = (array)($v['category'] ?? []);
                $row = [
                    'variant_id'    => $vid,
                    'product_id'    => (int)($v['product_id'] ?? 0),
                    'category_id'   => (int)($cat['id'] ?? 0),
                    'category_slug' => (string)($cat['slug'] ?? ''),
                    'product_name'  => (string)($v['product_name'] ?? ''),
                    'name'          => (string)($v['name'] ?? ''),
                    'type'          => (string)($v['type'] ?? ''),
                    'badge'         => (string)($v['badge'] ?? ''),
                    'quantity'      => max(1, (int)($v['quantity'] ?? 1)),
                    'price'         => (float)($v['price'] ?? 0),
                    'vat_amount'    => (float)($v['vat_amount'] ?? 0),
                    'total_to_pay'  => (float)($v['total_to_pay'] ?? 0),
                    'currency'      => (string)($v['currency'] ?? 'VND'),
                    'list_price'    => isset($v['list_price']) ? (float)$v['list_price'] : null,
                    'agent_discount_percent' => (float)($v['agent_discount_percent'] ?? 0),
                    'allow_avatar'  => !empty($v['allow_avatar']) ? 1 : 0,
                    'allow_files'   => !empty($v['allow_files']) ? 1 : 0,
                    'description'   => (string)($v['description'] ?? ''),
                    'is_active'     => 1,
                    'updated_at'    => now(),
                ];
                $exists = Database::fetchColumn('SELECT id FROM automxh_variants WHERE variant_id = ?', [$vid]);
                if ($exists) {
                    Database::update('automxh_variants', $row, 'id = :id', ['id' => (int)$exists]);
                } else {
                    Database::insert('automxh_variants', $row);
                }
            }
        } catch (\Throwable $e) {
            // Không chặn luồng chính vì lỗi lưu danh mục
        }
    }

    /**
     * Trích apikey từ header / query / body.
     */
    private function extractApiKey(): string
    {
        // Header
        $key = trim((string)($_SERVER['HTTP_X_API_KEY'] ?? ''));
        if ($key !== '') {
            return $key;
        }

        $auth = trim((string)($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
        if ($auth !== '' && stripos($auth, 'bearer ') === 0) {
            return trim(substr($auth, 7));
        }

        // Query
        foreach (['apikey', 'api_key', 'key'] as $f) {
            $v = trim((string)($_GET[$f] ?? ''));
            if ($v !== '') {
                return $v;
            }
        }

        // Body
        $body = $this->jsonBody();
        foreach (['apikey', 'api_key', 'key'] as $f) {
            $v = trim((string)($body[$f] ?? ''));
            if ($v !== '') {
                return $v;
            }
        }
        foreach (['apikey', 'api_key', 'key'] as $f) {
            $v = trim((string)($_POST[$f] ?? ''));
            if ($v !== '') {
                return $v;
            }
        }

        return '';
    }

    /**
     * Đọc JSON body.
     */
    private function jsonBody(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $raw = file_get_contents('php://input');
        if (!is_string($raw) || $raw === '') {
            return $cache = [];
        }

        $decoded = json_decode($raw, true);
        return $cache = is_array($decoded) ? $decoded : [];
    }

    /**
     * Trả lỗi chuẩn JSON.
     */
    private function fail(string $message, int $code = 400): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
