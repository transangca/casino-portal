<?php
namespace App\Services;

use App\Core\Crypto;
use App\Core\Database;

/**
 * Dịch vụ bán tài khoản & nick — tích hợp API đối tác Vuavia (v2).
 *
 * Base URL: https://api.vuavia.io/api/v2
 * - GET  /wallet/balance → số dư ví đối tác
 * - GET  /products       → danh sách sản phẩm (q, categoryId, page, size, sort)
 * - POST /orders         → đặt hàng (AUTO giao ngay / MANUAL chờ admin giao)
 * - GET  /orders         → lịch sử đơn hàng
 * - GET  /orders/{id}    → chi tiết đơn + hàng đã giao
 *
    10| * Lưu ý quan trọng:
 *  - Xác thực bằng header `X-Api-Key: vav_xxx` (KHÔNG phải Bearer).
 *  - Response luôn có cấu trúc: { success, message, data, error }.
 *  - Mỗi request chỉ dùng 1 header xác thực → không bị lộ key ra ngoài.
 */
class VuaviaService
{
    public const DEFAULT_BASE_URL = 'https://api.vuavia.io/api/v2';

    /** Mã lỗi của API → thông báo tiếng Việt + hướng xử lý */
    public const ERROR_CODES = [
        'UNAUTHORIZED'        => 'API key không hợp lệ hoặc thiếu. Liên hệ admin cấu hình lại.',
        'INSUFFICIENT_BALANCE'=> 'Số dư ví đối tác không đủ để mua. Vui lòng nạp thêm.',
        'OUT_OF_STOCK'        => 'Sản phẩm tạm hết hàng. Vui lòng thử lại sau.',
        'NOT_FOUND'           => 'Sản phẩm hoặc đơn hàng không tồn tại.',
        'BAD_REQUEST'         => 'Dữ liệu request không hợp lệ (thiếu field, sai kiểu...).',
        'INVALID_PRODUCT_ID'  => 'Mã sản phẩm không hợp lệ.',
        'INVALID_QUANTITY'    => 'Số lượng phải từ 1 trở lên.',
        'NETWORK_ERROR'       => 'Không kết nối được tới API đối tác. Vui lòng thử lại sau.',
        'INVALID_RESPONSE'    => 'API trả về dữ liệu không hợp lệ.',
        'UNKNOWN_ERROR'       => 'Lỗi không xác định từ API đối tác.',
    ];

    /** @var array|null cache danh mục trong request */
    private static ?array $productCache = null;

    // =================================================================
    //  Cấu hình
    // =================================================================

    public static function baseUrl(): string
    {
        $url = rtrim(trim((string)setting('vuavia_base_url', '')), '/');
        if ($url === '') {
            $url = (string)config('vuavia.base_url', self::DEFAULT_BASE_URL);
        }

        return rtrim(trim($url), '/') ?: self::DEFAULT_BASE_URL;
    }

    /**
     * API key đã giải mã (rỗng nếu chưa cấu hình).
     */
    public static function apiKey(): string
    {
        $raw = (string)setting('vuavia_api_key', '');
        if ($raw !== '') {
            $dec = Crypto::decrypt($raw);
            $key = $dec ?? $raw;
            if ($key !== '') {
                return $key;
            }
        }

        return trim((string)config('vuavia.api_key', ''));
    }

    public static function configured(): bool
    {
        return self::apiKey() !== '';
    }

    public static function enabled(): bool
    {
        $v = (string)setting('vuavia_service_on', '');
        if ($v === '') {
            return (bool)config('vuavia.service_on', true);
        }

        return $v === '1';
    }

    /**
     * Lưu API key (mã hóa AES-256-GCM trước khi ghi DB).
     */
    public static function saveApiKey(string $key): void
    {
        $key = trim($key);
        setting_set(
            'vuavia_api_key',
            $key === '' ? '' : Crypto::encrypt($key),
            'Tài khoản & Nick',
            'password'
        );
    }

    /**
     * Biên lợi nhuận (%) — admin cấu hình.
     */
    public static function markup(): float
    {
        $v = setting('vuavia_markup', null);
        if ($v === null || $v === '') {
            return (float)config('vuavia.markup', 0);
        }

        return max(0.0, min(500.0, (float)$v));
    }

    // =================================================================
    //  HTTP
    // =================================================================

    /**
     * Gọi API đối tác.
     *
     * @return array ['ok'=>bool,'data'=>array,'errorCode'=>string,'message'=>string,'status'=>int]
     */
    public static function request(string $method, string $endpoint, array $query = [], array $payload = []): array
    {
        if (!self::configured()) {
            return [
                'ok'        => false,
                'data'      => [],
                'errorCode' => 'MISSING_API_KEY',
                'message'   => 'Chưa cấu hình API key cho dịch vụ tài khoản & nick. Admin nhập tại /admin/vuavia-accounts.',
                'status'    => 401,
            ];
        }

        $url = self::baseUrl() . '/' . ltrim($endpoint, '/');
        if ($query) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        // CHỈ 1 header xác thực — không gửi thêm header khác để tránh lộ key
        $headers = [
            'X-Api-Key: ' . self::apiKey(),
            'Accept: application/json',
        ];

        $opts = ['timeout' => 30, 'retries' => 1, 'allow_error' => true];

        $res = HttpClient::requestFull(
            $method,
            $url,
            $method === 'POST' ? $payload : null,
            $headers,
            $opts
        );

        $body = $res['body'];
        if ($body === null || $body === '') {
            return [
                'ok'        => false,
                'data'      => [],
                'errorCode' => 'NETWORK_ERROR',
                'message'   => 'Không kết nối được tới API đối tác. Vui lòng thử lại sau.',
                'status'    => 0,
            ];
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return [
                'ok'        => false,
                'data'      => [],
                'errorCode' => 'INVALID_RESPONSE',
                'message'   => 'API trả về dữ liệu không hợp lệ.',
                'status'    => $res['status'],
            ];
        }

        $ok = (bool)($decoded['success'] ?? false);

        if ($ok) {
            return [
                'ok'        => true,
                'data'      => (array)($decoded['data'] ?? []),
                'errorCode' => '',
                'message'   => (string)($decoded['message'] ?? 'Thành công'),
                'status'    => $res['status'],
            ];
        }

        $code = (string)($decoded['error']['code'] ?? '');
        if ($code === '') {
            $code = (string)($decoded['errorCode'] ?? '');
        }
        if ($code === '') {
            $code = 'UNKNOWN_ERROR';
        }

        $message = (string)($decoded['message'] ?? '');
        if ($message === '') {
            $message = self::ERROR_CODES[$code] ?? 'Không rõ nguyên nhân.';
        }

        return [
            'ok'        => false,
            'data'      => (array)($decoded['data'] ?? []),
            'errorCode' => $code,
            'message'   => $message,
            'status'    => $res['status'],
        ];
    }

    // =================================================================
    //  Sản phẩm
    // =================================================================

    /**
     * Danh sách sản phẩm (đọc từ cache DB, đồng bộ từ API).
     *
     * @param array $opts ['q','category_id','page','size','sort','refresh']
     */
    public static function products(array $opts = []): array
    {
        $refresh = !empty($opts['refresh']);

        if (!$refresh && self::$productCache !== null) {
            return self::$productCache;
        }

        $q          = trim((string)($opts['q'] ?? ''));
        $categoryId = (int)($opts['category_id'] ?? 0);
        $page       = max(0, (int)($opts['page'] ?? 0));
        $size       = max(1, min(100, (int)($opts['size'] ?? 50)));
        $sort       = (string)($opts['sort'] ?? 'newest');

        $query = ['page' => $page, 'size' => $size, 'sort' => $sort];
        if ($q !== '') {
            $query['q'] = $q;
        }
        if ($categoryId > 0) {
            $query['categoryId'] = $categoryId;
        }

        $res = self::request('GET', '/products', $query);

        $out = [];
        foreach ((array)($res['data']['content'] ?? []) as $p) {
            if (!is_array($p) || !isset($p['id'])) {
                continue;
            }
            $item = self::formatProduct($p);

            // Lọc phía client (đề phòng API bỏ qua tham số q)
            if ($q !== '' && !self::matchName($item['name'], $q)) {
                continue;
            }
            if ($categoryId > 0 && $item['category_id'] !== $categoryId) {
                continue;
            }

            $out[] = $item;
        }

        // Ghi cache DB để khách xem nhanh khi API lỗi
        if ($out && ($q === '' && $categoryId === 0)) {
            self::saveProducts($out);
        }

        self::$productCache = $out;

        return $out;
    }

    /**
     * So khớp tên sản phẩm với từ khoá (không phân biệt hoa thường, dấu).
     */
    private static function matchName(string $name, string $needle): bool
    {
        $norm = static function (string $s): string {
            $s = mb_strtolower(trim($s));
            $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
            if (is_string($t) && $t !== '') {
                $s = $t;
            }
            return (string)preg_replace('/[^a-z0-9]+/', '', $s);
        };

        return str_contains($norm($name), $norm($needle));
    }

    /**
     * Chuẩn hoá 1 sản phẩm từ API.
     */
    private static function formatProduct(array $p): array
    {        return [
            'product_id'    => (string)($p['id'] ?? ''),
            'name'          => (string)($p['name'] ?? ''),
            'price'         => (float)($p['price'] ?? 0),
            'stock_count'   => (int)($p['stockCount'] ?? 0),
            'delivery_type' => strtoupper((string)($p['deliveryType'] ?? 'AUTO')),
            'category_id'   => (int)($p['categoryId'] ?? 0),
            'category_name' => (string)($p['categoryName'] ?? ''),
        ];
    }

    /**
     * Đồng bộ toàn bộ sản phẩm + danh mục vào DB.
     */
    public static function syncProducts(): array
    {
        $res = self::request('GET', '/products', ['page' => 0, 'size' => 100, 'sort' => 'newest']);

        if (!$res['ok']) {
            return ['ok' => false, 'message' => $res['message'], 'count' => 0];
        }

        $products = [];
        foreach ((array)($res['data']['content'] ?? []) as $p) {
            if (!is_array($p) || !isset($p['id'])) {
                continue;
            }
            $products[] = self::formatProduct($p);
        }

        $count = self::saveProducts($products);
        self::syncCategories($products);

        self::$productCache = null;

        return [
            'ok'      => true,
            'message' => "Đã đồng bộ $count sản phẩm.",
            'count'   => $count,
        ];
    }

    /**
     * Ghi sản phẩm vào DB (upsert).
     */
    private static function saveProducts(array $products): int
    {
        $count = 0;
        $order = 0;

        foreach ($products as $p) {
            if ($p['product_id'] === '') {
                continue;
            }
            $order++;

            $data = [
                'product_id'    => (int)$p['product_id'],
                'name'          => mb_substr($p['name'], 0, 250),
                'price'         => $p['price'],
                'stock_count'   => $p['stock_count'],
                'delivery_type' => $p['delivery_type'],
                'category_id'   => $p['category_id'] ?: null,
                'category_name' => mb_substr($p['category_name'], 0, 145) ?: null,
                'sort_order'    => $order,
                'is_active'     => 1,
            ];

            $exists = Database::fetchColumn(
                'SELECT id FROM vuavia_products WHERE product_id = ?',
                [$data['product_id']]
            );
            if ($exists) {
                Database::update(
                    'vuavia_products',
                    $data,
                    'id = :id',
                    ['id' => (int)$exists]
                );
            } else {
                Database::insert('vuavia_products', $data);
            }
            $count++;
        }

        return $count;
    }

    /**
     * Đồng bộ danh mục.
     */
    private static function syncCategories(array $products): void
    {
        $cats = [];
        foreach ($products as $p) {
            if ($p['category_id'] <= 0) {
                continue;
            }
            $cats[$p['category_id']] = $p['category_name'];
        }

        $order = 0;
        foreach ($cats as $cid => $name) {
            $order++;
            $exists = Database::fetchColumn(
                'SELECT id FROM vuavia_categories WHERE category_id = ?',
                [$cid]
            );
            if ($exists) {
                Database::update(
                    'vuavia_categories',
                    ['name' => mb_substr((string)$name, 0, 145), 'sort_order' => $order],
                    'id = :id',
                    ['id' => (int)$exists]
                );
            } else {
                Database::insert('vuavia_categories', [
                    'category_id' => (int)$cid,
                    'name'        => mb_substr((string)$name, 0, 145),
                    'sort_order'  => $order,
                    'is_active'   => 1,
                ]);
            }
        }
    }

    /**
     * Danh mục sản phẩm (đọc từ DB).
     */
    public static function categories(): array
    {
        return Database::fetchAll(
            'SELECT * FROM vuavia_categories WHERE is_active = 1 ORDER BY sort_order, name'
        );
    }

    /**
     * Sản phẩm lưu trong DB (fallback khi API lỗi).
     */
    public static function localProducts(array $opts = []): array
    {
        $where  = ['is_active = 1'];
        $params = [];

        $q = trim((string)($opts['q'] ?? ''));
        if ($q !== '') {
            $where[] = 'name LIKE :q';
            $params['q'] = '%' . $q . '%';
        }

        $categoryId = (int)($opts['category_id'] ?? 0);
        if ($categoryId > 0) {
            $where[] = 'category_id = :cid';
            $params['cid'] = $categoryId;
        }

        $rows = Database::fetchAll(
            'SELECT * FROM vuavia_products WHERE ' . implode(' AND ', $where)
            . ' ORDER BY sort_order, id LIMIT 200',
            $params
        );

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'product_id'    => (string)$r['product_id'],
                'name'          => (string)$r['name'],
                'price'         => (float)$r['price'],
                'stock_count'   => (int)$r['stock_count'],
                'delivery_type' => (string)$r['delivery_type'],
                'category_id'   => (int)($r['category_id'] ?? 0),
                'category_name' => (string)($r['category_name'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * Tìm 1 sản phẩm theo ID.
     */
    public static function findProduct(string $productId): ?array
    {
        $productId = trim($productId);
        if ($productId === '') {
            return null;
        }

        foreach (self::products() as $p) {
            if ($p['product_id'] === $productId) {
                return $p;
            }
        }

        foreach (self::localProducts() as $p) {
            if ($p['product_id'] === $productId) {
                return $p;
            }
        }

        return null;
    }

    // =================================================================
    //  Ví & đặt hàng
    // =================================================================

    /**
     * Số dư ví đối tác.
     */
    public static function balance(): array
    {
        $res = self::request('GET', '/wallet/balance');

        if (!$res['ok']) {
            return [
                'ok'        => false,
                'balance'   => 0.0,
                'message'   => $res['message'],
                'errorCode' => $res['errorCode'],
            ];
        }

        return [
            'ok'      => true,
            'balance' => (float)($res['data']['balance'] ?? 0),
            'message' => '',
            'errorCode' => '',
        ];
    }

    /**
     * Đặt hàng.
     *
     * @return array ['ok','message','errorCode','order' => [..], 'deliveredItems' => [...]]
     */
    public static function createOrder(int $userId, string $productId, int $quantity = 1): array
    {
        $productId = trim($productId);
        if ($productId === '') {
            return ['ok' => false, 'message' => 'Vui lòng chọn sản phẩm.', 'errorCode' => 'INVALID_PRODUCT_ID'];
        }

        $quantity = max(1, min(1000, $quantity));

        $res = self::request('POST', '/orders', [], [
            'productId' => (int)$productId,
            'quantity'  => $quantity,
        ]);

        if (!$res['ok']) {
            return [
                'ok'        => false,
                'message'   => $res['message'],
                'errorCode' => $res['errorCode'],
            ];
        }

        $d = $res['data'];

        $items = [];
        foreach ((array)($d['deliveredItems'] ?? []) as $item) {
            $item = trim((string)$item);
            if ($item === '') {
                continue;
            }
            $items[] = $item;
        }

        return [
            'ok'             => true,
            'message'        => (string)($res['message'] ?: 'Đặt hàng thành công'),
            'errorCode'      => '',
            'partner_order_id' => (int)($d['id'] ?? 0),
            'status'         => (string)($d['status'] ?? 'PENDING_DELIVERY'),
            'total_amount'   => (float)($d['totalAmount'] ?? 0),
            'quantity'       => (int)($d['quantity'] ?? $quantity),
            'deliveredItems' => $items,
            'created_at'     => (string)($d['createdAt'] ?? now()),
        ];
    }

    // =================================================================
    //  Đơn hàng (bản sao cục bộ)
    // =================================================================

    /**
     * Lưu đơn hàng vào DB.
     */
    public static function saveOrder(int $userId, array $data): int
    {
        return Database::insert('vuavia_orders', [
            'user_id'           => $userId,
            'product_id'        => (int)($data['product_id'] ?? 0),
            'product_name'      => mb_substr((string)($data['product_name'] ?? ''), 0, 250) ?: null,
            'quantity'          => max(1, (int)($data['quantity'] ?? 1)),
            'total_amount'      => (float)($data['total_amount'] ?? 0),
            'unit_price'        => (float)($data['unit_price'] ?? 0),
            'status'            => (string)($data['status'] ?? 'PENDING'),
            'partner_order_id'  => (int)($data['partner_order_id'] ?? 0) ?: null,
            'delivery_note'     => mb_substr((string)($data['delivery_note'] ?? ''), 0, 490) ?: null,
            'error_msg'         => mb_substr((string)($data['error_msg'] ?? ''), 0, 490) ?: null,
        ]);
    }

    /**
     * Lưu hàng đã giao (mật khẩu mã hoá).
     */
    public static function saveDeliveries(int $orderId, array $items): void
    {
        foreach ($items as $item) {
            $item = (string)$item;
            if ($item === '') {
                continue;
            }

            // Format API: "account|password" → tách account & password
            $account  = $item;
            $password = '';
            $pos      = strpos($item, '|');
            if ($pos !== false) {
                $account  = substr($item, 0, $pos);
                $password = substr($item, $pos + 1);
            }

            Database::insert('vuavia_deliveries', [
                'order_id'     => $orderId,
                'account'      => mb_substr($account, 0, 250) ?: null,
                'password_enc' => $password !== '' ? Crypto::encrypt($password) : null,
            ]);
        }
    }

    /**
     * Giải mã mật khẩu đã giao.
     */
    public static function revealPassword(?string $encrypted): ?string
    {
        if ($encrypted === null || $encrypted === '') {
            return null;
        }

        return Crypto::decrypt($encrypted);
    }

    /**
     * Đơn hàng của người dùng.
     */
    public static function myOrders(int $userId, int $limit = 50): array
    {
        return Database::fetchAll(
            'SELECT * FROM vuavia_orders WHERE user_id = ? ORDER BY id DESC LIMIT ' . (int)$limit,
            [$userId]
        );
    }

    /**
     * Chi tiết 1 đơn (kèm hàng đã giao).
     */
    public static function findOrder(int $userId, int $id): ?array
    {
        $order = Database::fetch(
            'SELECT * FROM vuavia_orders WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );
        if (!$order) {
            return null;
        }

        $order['deliveries'] = Database::fetchAll(
            'SELECT * FROM vuavia_deliveries WHERE order_id = ? ORDER BY id',
            [$id]
        );

        return $order;
    }

    /**
     * Đánh dấu đã nhận hàng.
     */
    public static function markRedeemed(int $userId, int $deliveryId): bool
    {
        $n = Database::query(
            'UPDATE vuavia_deliveries d
             JOIN vuavia_orders o ON o.id = d.order_id
             SET d.is_redeemed = 1, d.redeemed_at = NOW()
             WHERE d.id = ? AND o.user_id = ?',
            [$deliveryId, $userId]
        )->rowCount();

        return $n > 0;
    }

    /**
     * Tất cả đơn (admin).
     */
    public static function allOrders(int $limit = 100): array
    {
        return Database::fetchAll(
            'SELECT o.*, u.username FROM vuavia_orders o
             LEFT JOIN users u ON u.id = o.user_id
             ORDER BY o.id DESC LIMIT ' . (int)$limit
        );
    }

    /**
     * Thống kê (admin).
     */
    public static function stats(): array
    {
        return [
            'orders'    => (int)Database::fetchColumn('SELECT COUNT(*) FROM vuavia_orders'),
            'delivered' => (int)Database::fetchColumn('SELECT COUNT(*) FROM vuavia_deliveries'),
            'revenue'   => (float)Database::fetchColumn('SELECT COALESCE(SUM(total_amount),0) FROM vuavia_orders'),
            'products'  => (int)Database::fetchColumn('SELECT COUNT(*) FROM vuavia_products WHERE is_active = 1'),
            'pending'   => (int)Database::fetchColumn('SELECT COUNT(*) FROM vuavia_orders WHERE status = "PENDING_DELIVERY"'),
        ];
    }

    /**
     * Tự động đồng bộ sản phẩm (cache 5 phút).
     */
    public static function shouldSync(): bool
    {
        $last = (int)self::cacheGet('vuavia_sync');
        return (time() - $last) > 300;
    }

    public static function markSynced(): void
    {
        self::cacheSet('vuavia_sync', (string)time(), 3600);
    }

    private static function cacheGet(string $key): ?string
    {
        $row = Database::fetch(
            'SELECT cache_value FROM cache_store WHERE cache_key = ? AND (expires_at IS NULL OR expires_at > NOW())',
            [$key]
        );

        return $row ? (string)$row['cache_value'] : null;
    }

    private static function cacheSet(string $key, string $value, int $ttl = 3600): void
    {
        $expires = date('Y-m-d H:i:s', time() + $ttl);
        $exists  = Database::fetchColumn('SELECT cache_key FROM cache_store WHERE cache_key = ?', [$key]);
        if ($exists) {
            Database::update('cache_store', [
                'cache_value' => $value,
                'expires_at'  => $expires,
            ], 'cache_key = :k', ['k' => $key]);
        } else {
            Database::insert('cache_store', [
                'cache_key'   => $key,
                'cache_value' => $value,
                'expires_at'  => $expires,
            ]);
        }
    }
}
