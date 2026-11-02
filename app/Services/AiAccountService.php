<?php
/**
 * Dịch vụ bán tài khoản AI — tích hợp API đối tác GenzShop.
 *
 * Base URL: https://genzshop.vn/api/partner/v1
 * - GET  /products.php   → danh sách sản phẩm + giá đại lý + tồn kho
 * - GET  /balance.php    → số dư ví của tài khoản gắn với API key
 * - POST /purchase.php   → mua hàng, nhận deliveredAccounts
 *
 * Lưu ý quan trọng:
 *  - Endpoint BẮT BUỘC có đuôi .php, thiếu sẽ trả HTML 404 thay vì JSON.
 *  - Production bắt buộc HTTPS (key nằm trong header).
 *  - Mỗi đơn cần idempotency_key riêng; gửi lại cùng key → replay:true, không trừ tiền lần 2.
 */

namespace App\Services;

use App\Core\Crypto;
use App\Core\Database;

class AiAccountService
{
    public const DEFAULT_BASE_URL = 'https://genzshop.vn/api/partner/v1';

    /** Mã lỗi của API → thông báo tiếng Việt + hướng xử lý */
    public const ERROR_CODES = [
        'INVALID_BODY'          => 'Body gửi lên không phải JSON hợp lệ.',
        'MISSING_API_KEY'       => 'Thiếu header X-API-Key. Vui lòng cấu hình API key.',
        'INVALID_API_KEY'       => 'API key sai hoặc đã bị thu hồi. Liên hệ admin.',
        'INSUFFICIENT_BALANCE'   => 'Số dư ví GenzShop không đủ để mua. Vui lòng nạp thêm.',
        'HTTPS_REQUIRED'        => 'API chỉ chấp nhận kết nối HTTPS. Cần cấu hình lại endpoint.',
        'IP_NOT_ALLOWED'        => 'IP của server không nằm trong allowlist của API key.',
        'PRODUCT_NOT_FOUND'     => 'Không tìm thấy sản phẩm.',
        'METHOD_NOT_ALLOWED'    => 'Sai HTTP method cho endpoint này.',
        'PRODUCT_INACTIVE'      => 'Sản phẩm hiện không mở bán.',
        'OUT_OF_STOCK'          => 'Sản phẩm tạm hết hàng. Vui lòng thử lại sau.',
        'INVALID_PRODUCT_ID'    => 'Mã sản phẩm không hợp lệ.',
        'INVALID_QUANTITY'      => 'Số lượng phải từ 1 đến 1000.',
        'INVALID_IDEMPOTENCY_KEY' => 'idempotency_key sai định dạng (8..128 ký tự [A-Za-z0-9._:-]).',
        'INVALID_CUSTOMER_EMAIL'=> 'Email khách hàng không hợp lệ.',
        'RATE_LIMITED'          => 'Gọi API quá nhanh. Vui lòng thử lại sau.',
        'INTERNAL_ERROR'        => 'Lỗi hệ thống của đối tác. Không bị trừ tiền.',
    ];

    /** Cache sản phẩm trong request */
    private static ?array $productCache = null;

    // =================================================================
    //  Cấu hình
    // =================================================================

    public static function baseUrl(): string
    {
        $url = rtrim(trim((string)setting('genzshop_base_url', '')), '/');
        if ($url === '') {
            $url = (string)config('genzshop.base_url', self::DEFAULT_BASE_URL);
        }
        $url = rtrim(trim($url), '/');

        return $url !== '' ? $url : self::DEFAULT_BASE_URL;
    }

    /**
     * API key đã giải mã (rỗng nếu chưa cấu hình).
     */
    public static function apiKey(): string
    {
        $raw = (string)setting('genzshop_api_key', '');
        if ($raw !== '') {
            $dec = Crypto::decrypt($raw);
            $key = $dec ?? $raw;
            if ($key !== '') {
                return $key;
            }
        }

        return trim((string)config('genzshop.api_key', ''));
    }

    public static function configured(): bool
    {
        return self::apiKey() !== '';
    }

    public static function enabled(): bool
    {
        $v = (string)setting('genzshop_service_on', '');
        if ($v === '') {
            return (bool)config('genzshop.service_on', true);
        }
        return $v === '1';
    }

    /**
     * Lưu API key (mã hóa AES-256-GCM trước khi ghi DB).
     */
    public static function saveApiKey(string $key): void
    {
        $key = trim($key);
        setting_set('genzshop_api_key', $key === '' ? '' : Crypto::encrypt($key), 'Tài khoản AI', 'password');
    }

    /**
     * Chuẩn hoá tên loại sản phẩm (cursor, chatgpt, claude...).
     */
    public static function typeLabel(string $type): string
    {
        $map = [
            'cursor'   => 'Cursor',
            'chatgpt'  => 'ChatGPT',
            'claude'   => 'Claude',
            'gemini'   => 'Gemini',
            'copilot'  => 'Copilot',
            'openai'   => 'OpenAI',
            'perplexity'=> 'Perplexity',
            'midjourney' => 'Midjourney',
            'sora'     => 'Sora',
            'windsurf' => 'Windsurf',
            'jetbrains'=> 'JetBrains',
        ];
        return $map[strtolower($type)] ?? ucfirst($type);
    }

    // =================================================================
    //  HTTP
    // =================================================================

    /**
     * Gọi API GenzShop.
     *
     * @return array ['ok'=>bool,'data'=>array,'errorCode'=>string,'message'=>string,'status'=>int,'retryAfter'=>int]
     */
    public static function request(string $method, string $endpoint, array $payload = []): array
    {
        if (!self::configured()) {
            return [
                'ok'         => false,
                'data'       => [],
                'errorCode'  => 'MISSING_API_KEY',
                'message'    => 'Chưa cấu hình API key cho dịch vụ tài khoản AI. Admin nhập tại /admin/ai-accounts.',
                'status'     => 401,
                'retryAfter' => 0,
            ];
        }

        // BẮT BUỘC giữ đuôi .php — thiếu sẽ trả HTML 404
        $url = self::baseUrl() . '/' . ltrim($endpoint, '/');
        if (!str_ends_with($url, '.php')) {
            $url .= '.php';
        }

        $headers = [
            'X-API-Key: ' . self::apiKey(),
            'Authorization: Bearer ' . self::apiKey(),
            'Accept: application/json',
        ];

        $opts = ['timeout' => 30, 'retries' => 1, 'allow_error' => true];

        if ($method === 'POST') {
            $headers[] = 'Content-Type: application/json';
            $body = HttpClient::postJson($url, $payload, $headers, $opts);
        } else {
            $body = HttpClient::get($url, $headers, $opts);
        }

        if ($body === null) {
            return [
                'ok'         => false,
                'data'       => [],
                'errorCode'  => 'NETWORK_ERROR',
                'message'    => 'Không kết nối được tới API GenzShop. Vui lòng thử lại sau.',
                'status'     => 0,
                'retryAfter' => 0,
            ];
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return [
                'ok'         => false,
                'data'       => [],
                'errorCode'  => 'INVALID_RESPONSE',
                'message'    => 'API trả về dữ liệu không hợp lệ (có phải endpoint thiếu đuôi .php không?).',
                'status'     => 0,
                'retryAfter' => 0,
            ];
        }

        $ok     = (bool)($decoded['success'] ?? false);
        $code   = (string)($decoded['errorCode'] ?? '');
        $detail = (string)($decoded['detail'] ?? '');

        if ($ok) {
            return [
                'ok'         => true,
                'data'       => $decoded,
                'errorCode'  => '',
                'message'    => $detail,
                'status'     => 200,
                'retryAfter' => 0,
            ];
        }

        return [
            'ok'         => false,
            'data'       => $decoded,
            'errorCode'  => $code !== '' ? $code : 'UNKNOWN_ERROR',
            'message'    => $detail !== '' ? $detail : (self::ERROR_CODES[$code] ?? 'Không rõ nguyên nhân.'),
            'status'     => 0,
            'retryAfter' => 0,
        ];
    }

    // =================================================================
    //  Sản phẩm
    // =================================================================

    /**
     * Danh sách sản phẩm đang mở bán.
     */
    public static function products(bool $refresh = false): array
    {
        if (!$refresh && self::$productCache !== null) {
            return self::$productCache;
        }

        $res = self::request('GET', '/products.php');
        if (!$res['ok']) {
            self::$productCache = [];
            return [];
        }

        $out = [];
        foreach ((array)($res['data']['products'] ?? []) as $p) {
            if (!is_array($p)) {
                continue;
            }
            $out[] = [
                'product_id'   => (string)($p['product_id'] ?? ''),
                'name'         => (string)($p['name'] ?? ''),
                'description'  => (string)($p['description'] ?? ''),
                'type'         => (string)($p['type'] ?? ''),
                'type_label'   => self::typeLabel((string)($p['type'] ?? '')),
                'price'        => (float)($p['walletPricing'] ?? 0),
                'price_text'   => (string)($p['walletPricingText'] ?? ''),
                'available'    => (int)($p['available'] ?? 0),
                'currency'     => (string)($p['walletCurrency'] ?? 'VND'),
                'icon'         => self::typeIcon((string)($p['type'] ?? '')),
            ];
        }

        self::$productCache = $out;
        return $out;
    }

    /**
     * Icon theo loại sản phẩm.
     */
    public static function typeIcon(string $type): string
    {
        $map = [
            'cursor'    => 'fa-solid fa-cursor',
            'chatgpt'   => 'fa-brands fa-openai',
            'claude'    => 'fa-solid fa-robot',
            'gemini'    => 'fa-solid fa-gem',
            'copilot'   => 'fa-brands fa-microsoft',
            'openai'    => 'fa-brands fa-openai',
            'perplexity'=> 'fa-solid fa-compass',
            'midjourney'=> 'fa-solid fa-image',
            'sora'      => 'fa-solid fa-clapperboard',
            'windsurf'  => 'fa-solid fa-wind',
            'jetbrains' => 'fa-solid fa-laptop-code',
        ];
        return $map[strtolower($type)] ?? 'fa-solid fa-box';
    }

    /**
     * Số dư ví GenzShop.
     */
    public static function balance(): array
    {
        $res = self::request('GET', '/balance.php');
        if (!$res['ok']) {
            return [
                'ok'        => false,
                'balance'   => 0.0,
                'currency'  => 'VND',
                'message'   => $res['message'],
                'errorCode' => $res['errorCode'],
            ];
        }

        $d = $res['data'];
        return [
            'ok'         => true,
            'balance'    => (float)($d['balance'] ?? 0),
            'balanceText'=> (string)($d['balanceText'] ?? ''),
            'currency'   => (string)($d['walletCurrency'] ?? 'VND'),
            'message'    => '',
            'errorCode'  => '',
        ];
    }

    // =================================================================
    //  Mua hàng
    // =================================================================

    /**
     * Sinh idempotency_key hợp lệ (8..128 ký tự [A-Za-z0-9._:-]).
     */
    public static function makeIdempotencyKey(int $userId, string $productId, string $nonce = ''): string
    {
        $nonce = $nonce !== '' ? $nonce : bin2hex(random_bytes(8));
        // Chỉ giữ ký tự hợp lệ theo spec: [A-Za-z0-9._:-]
        $pid = preg_replace('/[^A-Za-z0-9._:-]/', '-', $productId);
        $pid = substr((string)$pid, 0, 40);

        return 'vbk-' . $userId . '-' . $pid . '-' . $nonce;
    }

    /**
     * Mua hàng từ GenzShop.
     *
     * @param int    $userId  Người mua trong hệ thống
     * @param string $productId
     * @param int    $quantity
     * @param string $idempotencyKey
     * @param string $customerEmail
     */
    public static function purchase(
        int $userId,
        string $productId,
        int $quantity = 1,
        string $idempotencyKey = '',
        string $customerEmail = ''
    ): array {
        $productId = trim($productId);
        if ($productId === '') {
            return ['ok' => false, 'message' => 'Vui lòng chọn sản phẩm.', 'errorCode' => 'INVALID_PRODUCT_ID'];
        }

        $quantity = max(1, min(1000, $quantity));
        if ($idempotencyKey === '') {
            $idempotencyKey = self::makeIdempotencyKey($userId, $productId);
        }

        $payload = [
            'product_id'      => $productId,
            'quantity'        => $quantity,
            'idempotency_key' => $idempotencyKey,
        ];
        if ($customerEmail !== '' && filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            $payload['customer_email'] = $customerEmail;
        }

        $res = self::request('POST', '/purchase.php', $payload);

        if (!$res['ok']) {
            return [
                'ok'          => false,
                'message'     => $res['message'],
                'errorCode'   => $res['errorCode'],
                'retryAfter'  => $res['retryAfter'],
                'idempotency' => $idempotencyKey,
            ];
        }

        $d = $res['data'];
        $accounts = [];
        foreach ((array)($d['deliveredAccounts'] ?? []) as $acc) {
            if (!is_array($acc)) {
                continue;
            }
            $accounts[] = [
                'productItemId' => (string)($acc['productItemId'] ?? ''),
                'user'          => (string)($acc['user'] ?? ''),
                'password'      => (string)($acc['password'] ?? ''),
                'verifyEmail'   => (string)($acc['verifyEmail'] ?? ''),
            ];
        }

        return [
            'ok'          => true,
            'message'     => 'Đã giao tài khoản thành công.',
            'errorCode'   => '',
            'replay'      => (bool)($d['replay'] ?? false),
            'orderCode'   => (string)($d['orderCode'] ?? ''),
            'productType' => (string)($d['productType'] ?? ''),
            'quantity'    => (int)($d['quantity'] ?? $quantity),
            'amount'      => (float)($d['amount'] ?? 0),
            'amountText'  => (string)($d['amountText'] ?? ''),
            'balance'     => (float)($d['balance'] ?? 0),
            'balanceText' => (string)($d['balanceText'] ?? ''),
            'accounts'    => $accounts,
            'idempotency' => $idempotencyKey,
        ];
    }

    // =================================================================
    //  Bản sao cục bộ
    // =================================================================

    /**
     * Sản phẩm đã đồng bộ vào DB (dùng khi API không sẵn sàng).
     */
    public static function localProducts(): array
    {
        return Database::fetchAll(
            'SELECT * FROM ai_account_products WHERE is_active = 1 ORDER BY sort_order, name'
        );
    }

    /**
     * Đồng bộ sản phẩm từ API vào DB.
     */
    public static function syncProducts(): array
    {
        $res = self::request('GET', '/products.php');
        if (!$res['ok']) {
            return ['ok' => false, 'message' => $res['message'], 'count' => 0];
        }

        $count = 0;
        foreach ((array)($res['data']['products'] ?? []) as $p) {
            if (!is_array($p) || empty($p['product_id'])) {
                continue;
            }

            $data = [
                'product_id'  => (string)$p['product_id'],
                'name'        => mb_substr((string)($p['name'] ?? ''), 0, 150),
                'description' => mb_substr((string)($p['description'] ?? ''), 0, 500),
                'type'        => (string)($p['type'] ?? ''),
                'type_label'  => self::typeLabel((string)($p['type'] ?? '')),
                'price'       => (float)($p['walletPricing'] ?? 0),
                'available'   => (int)($p['available'] ?? 0),
                'icon'        => self::typeIcon((string)($p['type'] ?? '')),
                'is_active'   => 1,
                'updated_at'  => now(),
            ];

            $exists = Database::fetchColumn(
                'SELECT id FROM ai_account_products WHERE product_id = ?',
                [$data['product_id']]
            );
            if ($exists) {
                Database::update('ai_account_products', $data, 'id = :id', ['id' => (int)$exists]);
            } else {
                $data['sort_order'] = $count;
                Database::insert('ai_account_products', $data);
            }
            $count++;
        }

        self::$productCache = null;

        return ['ok' => true, 'message' => "Đã đồng bộ $count sản phẩm.", 'count' => $count];
    }

    /**
     * Đơn hàng của người dùng.
     */
    public static function myOrders(int $userId, int $limit = 50): array
    {
        return Database::fetchAll(
            'SELECT o.*, p.name AS product_name, p.type_label, p.icon
             FROM ai_account_orders o
             LEFT JOIN ai_account_products p ON p.product_id = o.product_id
             WHERE o.user_id = ? ORDER BY o.id DESC LIMIT ' . (int)$limit,
            [$userId]
        );
    }

    /**
     * Chi tiết 1 đơn (kèm tài khoản đã giao).
     */
    public static function findOrder(int $userId, int $id): ?array
    {
        $order = Database::fetch(
            'SELECT o.*, p.name AS product_name, p.type_label, p.icon
             FROM ai_account_orders o
             LEFT JOIN ai_account_products p ON p.product_id = o.product_id
             WHERE o.id = ? AND o.user_id = ?',
            [$id, $userId]
        );
        if (!$order) {
            return null;
        }

        $order['accounts'] = Database::fetchAll(
            'SELECT * FROM ai_account_deliveries WHERE order_id = ? ORDER BY id',
            [$id]
        );

        return $order;
    }

    /**
     * Tạo/cập nhật đơn (upsert theo idempotency_key để tránh lỗi trùng key).
     */
    public static function createOrder(int $userId, array $data): int
    {
        $idem = (string)($data['idempotency_key'] ?? '');

        $row = [
            'user_id'         => $userId,
            'product_id'      => (string)($data['product_id'] ?? ''),
            'product_name'    => (string)($data['product_name'] ?? ''),
            'type'            => (string)($data['type'] ?? ''),
            'quantity'        => max(1, (int)($data['quantity'] ?? 1)),
            'amount'          => (float)($data['amount'] ?? 0),
            'order_code'      => (string)($data['order_code'] ?? ''),
            'idempotency_key' => $idem !== '' ? $idem : null,
            'status'          => (string)($data['status'] ?? 'pending'),
            'replay'          => !empty($data['replay']) ? 1 : 0,
            'message'         => mb_substr((string)($data['message'] ?? ''), 0, 255),
        ];

        if ($idem !== '') {
            $exists = Database::fetchColumn(
                'SELECT id FROM ai_account_orders WHERE idempotency_key = ?',
                [$idem]
            );
            if ($exists) {
                Database::update('ai_account_orders', $row, 'id = :id', ['id' => (int)$exists]);
                return (int)$exists;
            }
        }

        return Database::insert('ai_account_orders', $row);
    }

    /**
     * Lưu tài khoản được giao (mật khẩu mã hoá).
     * Gọi lại với cùng order_id sẽ thay thế danh sách cũ (tránh trùng khi replay).
     */
    public static function saveDeliveries(int $orderId, array $accounts): void
    {
        Database::delete('ai_account_deliveries', 'order_id = ?', [$orderId]);

        foreach ($accounts as $acc) {
            if (!is_array($acc)) {
                continue;
            }
            Database::insert('ai_account_deliveries', [
                'order_id'        => $orderId,
                'product_item_id' => (string)($acc['productItemId'] ?? ''),
                'username'        => (string)($acc['user'] ?? ''),
                'password_enc'    => !empty($acc['password']) ? Crypto::encrypt((string)$acc['password']) : null,
                'verify_email'    => (string)($acc['verifyEmail'] ?? ''),
                'is_redeemed'     => 0,
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
     * Đánh dấu tài khoản đã được khách nhận.
     */
    public static function markRedeemed(int $userId, int $deliveryId): bool
    {
        $n = Database::query(
            'UPDATE ai_account_deliveries d
             JOIN ai_account_orders o ON o.id = d.order_id
             SET d.is_redeemed = 1
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
            'SELECT o.*, u.username FROM ai_account_orders o
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
            'orders'     => (int)Database::fetchColumn('SELECT COUNT(*) FROM ai_account_orders'),
            'delivered'  => (int)Database::fetchColumn('SELECT COUNT(*) FROM ai_account_deliveries'),
            'revenue'    => (float)Database::fetchColumn('SELECT COALESCE(SUM(amount),0) FROM ai_account_orders WHERE status = "success"'),
            'products'   => (int)Database::fetchColumn('SELECT COUNT(*) FROM ai_account_products WHERE is_active = 1'),
            'out_stock'  => (int)Database::fetchColumn('SELECT COUNT(*) FROM ai_account_products WHERE is_active = 1 AND available <= 0'),
        ];
    }

    /**
     * Tự động đồng bộ sản phẩm (cache 5 phút).
     */
    public static function shouldSync(): bool
    {
        $last = (int)self::cacheGet('genzshop_sync');
        return (time() - $last) > 300;
    }

    public static function markSynced(): void
    {
        self::cacheSet('genzshop_sync', (string)time(), 3600);
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
