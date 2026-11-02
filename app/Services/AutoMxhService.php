<?php
/**
 * Auto MXH — dịch vụ mở khóa tài khoản, tích hợp API đối tác TrungTamMMO.
 *
 * Base URL: https://trungtammmo.vn/api/external/automxh
 *
 * Endpoint (tất cả đều dùng apikey riêng của user):
 *   GET  /services?category=&search=   → toàn bộ variant đang active + giá
 *   GET  /catalog?search=              → cây category / product
 *   GET  /category/{slug}              → product kèm đủ variant con
 *   POST /quote                        → tính giá (không tạo đơn)
 *   POST /order                        → tạo đơn thật + trừ ví chính
 *   GET  /status?order=                → trạng thái & tiến độ 1 đơn
 *   GET  /orders?limit=&product_ids=   → lịch sử đơn
 *   GET  /balance                      → số dư ví chính
 *
 * Xác thực: header `x-api-key` (đồng thời gửi kèm Authorization: Bearer).
 *
 * Lưu ý nghiệp vụ:
 *  - Giá = variant.price, KHÔNG cộng VAT (vat_amount/vat_percent = 0).
 *  - Không nhận quantity tự do — quantity là thuộc tính của gói.
 *  - Tài khoản đại lý được giảm %: services/category/quote trả price đã giảm
 *    kèm list_price + agent_discount_percent.
 *  - Gói chưa xử lý tự động được vẫn tạo đơn ở trạng thái pending cho admin xử lý.
 */

namespace App\Services;

use App\Core\Crypto;
use App\Core\Database;

class AutoMxhService
{
    public const DEFAULT_BASE_URL = 'https://trungtammmo.vn/api/external/automxh';

    /** Trạng thái đơn chuẩn hoá */
    public const STATUS_PENDING    = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED  = 'completed';
    public const STATUS_FAILED     = 'failed';

    /** Thứ tự ưu tiên khi quy đổi trạng thái đối tác → trạng thái nội bộ */
    private const STATUS_MAP = [
        'completed'  => self::STATUS_COMPLETED,
        'complete'   => self::STATUS_COMPLETED,
        'success'    => self::STATUS_COMPLETED,
        'done'       => self::STATUS_COMPLETED,
        'finished'   => self::STATUS_COMPLETED,
        'processing' => self::STATUS_PROCESSING,
        'running'    => self::STATUS_PROCESSING,
        'in_progress'=> self::STATUS_PROCESSING,
        'pending'    => self::STATUS_PENDING,
        'queued'     => self::STATUS_PENDING,
        'waiting'    => self::STATUS_PENDING,
        'failed'     => self::STATUS_FAILED,
        'error'      => self::STATUS_FAILED,
        'cancelled'  => self::STATUS_FAILED,
        'canceled'   => self::STATUS_FAILED,
    ];

    /** Cache trong request cho danh mục */
    private static ?array $servicesCache = null;

    // =================================================================
    //  Cấu hình
    // =================================================================

    public static function baseUrl(): string
    {
        $url = rtrim(trim((string)setting('automxh_base_url', '')), '/');
        if ($url === '') {
            $url = (string)config('automxh.base_url', self::DEFAULT_BASE_URL);
        }
        $url = rtrim(trim($url), '/');

        return $url !== '' ? $url : self::DEFAULT_BASE_URL;
    }

    /**
     * API key đã giải mã.
     * Ưu tiên key đã lưu (mã hoá), sau đó tới key cấu hình chung trong .env.
     */
    public static function apiKey(): string
    {
        $raw = (string)setting('automxh_api_key', '');
        if ($raw !== '') {
            $dec = Crypto::decrypt($raw);
            $key = $dec ?? $raw;
            if ($key !== '') {
                return trim($key);
            }
        }

        return trim((string)config('automxh.api_key', ''));
    }

    public static function saveApiKey(string $key): void
    {
        $key = trim($key);
        setting_set('automxh_api_key', $key === '' ? '' : Crypto::encrypt($key), 'Auto MXH', 'password');
    }

    public static function configured(): bool
    {
        return self::apiKey() !== '';
    }

    public static function enabled(): bool
    {
        $v = (string)setting('automxh_service_on', '');
        if ($v === '') {
            return (bool)config('automxh.service_on', true);
        }
        return $v === '1';
    }

    /** Biên lợi nhuận (%) áp cho khách */
    public static function markup(): float
    {
        return (float)setting('automxh_markup', '0');
    }

    /**
     * Tính giá bán cho khách từ giá đối tác.
     */
    public static function sellPrice(float $cost): float
    {
        $markup = self::markup();
        return $markup > 0 ? (float)ceil($cost * (1 + $markup / 100)) : $cost;
    }

    // =================================================================
    //  HTTP
    // =================================================================

    /**
     * Gọi API Auto MXH.
     *
     * @return array ['ok'=>bool,'data'=>array,'message'=>string,'status'=>int]
     */
    public static function request(string $method, string $endpoint, array $payload = [], array $query = []): array
    {
        $apiKey = self::apiKey();
        if ($apiKey === '') {
            return [
                'ok'      => false,
                'data'    => [],
                'message' => 'Chưa cấu hình API key cho dịch vụ Auto MXH. Admin nhập tại /admin/automxh.',
                'status'  => 401,
            ];
        }

        $url = self::baseUrl() . '/' . ltrim($endpoint, '/');
        if ($query) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        $headers = [
            'x-api-key: ' . $apiKey,
            'Authorization: Bearer ' . $apiKey,
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
                'ok'      => false,
                'data'    => [],
                'message' => 'Không kết nối được tới API Auto MXH. Vui lòng thử lại sau.',
                'status'  => 0,
            ];
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return [
                'ok'      => false,
                'data'    => [],
                'message' => 'API Auto MXH trả về dữ liệu không hợp lệ.',
                'status'  => 0,
            ];
        }

        $success = (bool)($decoded['success'] ?? false);
        $message = (string)($decoded['message'] ?? '');

        return [
            'ok'      => $success,
            'data'    => $decoded,
            'message' => $message !== '' ? $message : ($success ? 'OK' : 'Không rõ nguyên nhân.'),
            'status'  => $success ? 200 : 401,
        ];
    }

    // =================================================================
    //  Endpoint wrappers
    // =================================================================

    /**
     * Danh sách toàn bộ variant đang active.
     */
    public static function services(string $category = '', string $search = ''): array
    {
        $query = [];
        if ($category !== '') {
            $query['category'] = $category;
        }
        if ($search !== '') {
            $query['search'] = $search;
        }

        $res = self::request('GET', '/services', [], $query);
        if (!$res['ok']) {
            return [];
        }

        $out = [];
        foreach ((array)($res['data']['data'] ?? []) as $v) {
            if (!is_array($v)) {
                continue;
            }
            $out[] = self::normalizeVariant($v);
        }

        return $out;
    }

    /**
     * Cây category / product.
     */
    public static function catalog(string $search = ''): array
    {
        $query = $search !== '' ? ['search' => $search] : [];

        $res = self::request('GET', '/catalog', [], $query);
        if (!$res['ok']) {
            return [];
        }

        $out = [];
        foreach ((array)($res['data']['data'] ?? []) as $cat) {
            if (!is_array($cat)) {
                continue;
            }
            $c = (array)($cat['category'] ?? []);
            $products = [];
            foreach ((array)($cat['products'] ?? []) as $p) {
                if (!is_array($p)) {
                    continue;
                }
                $products[] = [
                    'product_id'    => (int)($p['product_id'] ?? $p['id'] ?? 0),
                    'name'          => (string)($p['name'] ?? ''),
                    'min_price'     => (float)($p['min_price'] ?? 0),
                    'variant_count' => (int)($p['variant_count'] ?? 0),
                ];
            }
            $out[] = [
                'category_id' => (int)($c['id'] ?? 0),
                'name'        => (string)($c['name'] ?? ''),
                'slug'        => (string)($c['slug'] ?? ''),
                'icon'        => (string)($c['icon'] ?? ''),
                'gif'         => (string)($c['gif'] ?? ''),
                'status'      => (string)($c['status'] ?? 'active'),
                'products'    => $products,
            ];
        }

        return $out;
    }

    /**
     * Product kèm đủ variant con trong một category.
     */
    public static function category(string $slug): array
    {
        $res = self::request('GET', '/category/' . rawurlencode($slug));
        if (!$res['ok']) {
            return [];
        }

        $out = [];
        foreach ((array)($res['data']['data'] ?? []) as $p) {
            if (!is_array($p)) {
                continue;
            }
            $variants = [];
            foreach ((array)($p['variants'] ?? []) as $v) {
                if (is_array($v)) {
                    $variants[] = self::normalizeVariant($v);
                }
            }
            $out[] = [
                'product_id'    => (int)($p['product_id'] ?? $p['id'] ?? 0),
                'name'          => (string)($p['name'] ?? ''),
                'variants'      => $variants,
            ];
        }

        return $out;
    }

    /**
     * Tính giá (không tạo đơn).
     */
    public static function quote(int $variantId, int $productId = 0): array
    {
        $payload = ['variant_id' => $variantId];
        if ($productId > 0) {
            $payload['product_id'] = $productId;
        }

        $res = self::request('POST', '/quote', $payload);
        if (!$res['ok']) {
            return ['ok' => false, 'message' => $res['message'], 'data' => []];
        }

        return [
            'ok'      => true,
            'message' => '',
            'service' => self::normalizeVariant((array)($res['data']['service'] ?? [])),
            'checkout'=> (array)($res['data']['checkout'] ?? []),
            'discount'=> (array)($res['data']['agent_discount'] ?? []),
            'data'    => $res['data'],
        ];
    }

    /**
     * Tạo đơn thật + trừ ví chính của user gắn với apikey.
     */
    public static function createOrder(int $variantId, string $link, int $productId = 0, string $buyerInfo = '', string $customValue = ''): array
    {
        $payload = [
            'service'   => $variantId,
            'variant_id'=> $variantId,
            'link'      => $link,
        ];
        if ($productId > 0) {
            $payload['product_id'] = $productId;
        }
        if ($buyerInfo !== '') {
            $payload['buyer_info'] = $buyerInfo;
        }
        if ($customValue !== '') {
            $payload['custom_value'] = $customValue;
        }

        $res = self::request('POST', '/order', $payload);
        if (!$res['ok']) {
            return ['ok' => false, 'message' => $res['message'], 'data' => []];
        }

        $d = (array)($res['data']['data'] ?? []);
        $orderId = (int)($res['data']['order'] ?? $d['order'] ?? $d['id'] ?? 0);

        return [
            'ok'      => true,
            'message' => (string)($res['data']['message'] ?? 'Đơn Auto MXH đã được tạo.'),
            'order_id'=> $orderId,
            'charge'  => (float)($res['data']['charge'] ?? $d['total_to_pay'] ?? 0),
            'balance' => (float)($res['data']['balance'] ?? $d['balance_after'] ?? 0),
            'status'  => self::mapStatus((string)($d['status'] ?? self::STATUS_PENDING)),
            'data'    => $d,
            'raw'     => $res['data'],
        ];
    }

    /**
     * Trạng thái & tiến độ 1 đơn.
     */
    public static function status(int $orderId): array
    {
        $res = self::request('GET', '/status', [], ['order' => $orderId]);
        if (!$res['ok']) {
            return ['ok' => false, 'message' => $res['message'], 'data' => []];
        }

        $d = (array)($res['data']['data'] ?? []);
        $progress = (array)($d['progress'] ?? []);

        return [
            'ok'      => true,
            'message' => '',
            'status'  => self::mapStatus((string)($d['status'] ?? '')),
            'progress_status'  => (string)($progress['status'] ?? ''),
            'progress_remains' => (string)($progress['remains'] ?? ''),
            'data'    => $d,
        ];
    }

    /**
     * Lịch sử đơn gần đây.
     */
    public static function orders(int $limit = 50, string $productIds = ''): array
    {
        $query = ['limit' => max(1, min(100, $limit))];
        if ($productIds !== '') {
            $query['product_ids'] = $productIds;
        }

        $res = self::request('GET', '/orders', [], $query);
        if (!$res['ok']) {
            return [];
        }

        $out = [];
        foreach ((array)($res['data']['data'] ?? []) as $o) {
            if (!is_array($o)) {
                continue;
            }
            $out[] = [
                'id'           => (int)($o['id'] ?? 0),
                'product_id'   => (int)($o['product_id'] ?? 0),
                'variant_id'   => (int)($o['variant_id'] ?? 0),
                'price'        => (float)($o['price'] ?? 0),
                'order_status' => self::mapStatus((string)($o['order_status'] ?? '')),
                'raw_status'   => (string)($o['order_status'] ?? ''),
                'product_name' => (string)($o['product_name'] ?? ''),
                'variant_name' => (string)($o['variant_name'] ?? ''),
                'created_at'   => (string)($o['created_at'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * Số dư ví chính.
     */
    public static function balance(): array
    {
        $res = self::request('GET', '/balance');
        if (!$res['ok']) {
            return [
                'ok'       => false,
                'balance'  => 0.0,
                'currency' => 'VND',
                'message'  => $res['message'],
                'user'     => [],
            ];
        }

        $d = (array)($res['data']['data'] ?? []);
        return [
            'ok'       => true,
            'balance'  => (float)($res['data']['balance'] ?? $d['balance'] ?? 0),
            'currency' => (string)($res['data']['currency'] ?? 'VND'),
            'user'     => [
                'user_id'     => (int)($d['user_id'] ?? 0),
                'username'    => (string)($d['username'] ?? ''),
                'api_status'  => (string)($d['api_status'] ?? ''),
            ],
            'message'  => '',
        ];
    }

    // =================================================================
    //  Chuẩn hoá
    // =================================================================

    /**
     * Chuẩn hoá 1 variant về cấu trúc thống nhất.
     */
    private static function normalizeVariant(array $v): array
    {
        $cat = (array)($v['category'] ?? []);
        $price = (float)($v['price'] ?? 0);
        $listPrice = $v['list_price'] ?? null;

        return [
            'variant_id'   => (int)($v['variant_id'] ?? $v['id'] ?? 0),
            'product_id'   => (int)($v['product_id'] ?? 0),
            'category_id'  => (int)($cat['id'] ?? 0),
            'category_name'=> (string)($cat['name'] ?? ''),
            'category_slug'=> (string)($cat['slug'] ?? ''),
            'product_name' => (string)($v['product_name'] ?? ''),
            'name'         => (string)($v['name'] ?? ''),
            'type'         => (string)($v['type'] ?? ''),
            'badge'        => (string)($v['badge'] ?? ''),
            'quantity'     => max(1, (int)($v['quantity'] ?? 1)),
            'price'        => $price,
            'vat_amount'   => (float)($v['vat_amount'] ?? 0),
            'total_to_pay' => (float)($v['total_to_pay'] ?? $price),
            'currency'     => (string)($v['currency'] ?? 'VND'),
            'list_price'   => $listPrice === null ? null : (float)$listPrice,
            'agent_discount_percent' => (float)($v['agent_discount_percent'] ?? 0),
            'allow_avatar' => (bool)($v['allow_avatar'] ?? false),
            'allow_files'  => (bool)($v['allow_files'] ?? false),
            'description'  => (string)($v['description'] ?? ''),
        ];
    }

    /**
     * Quy đổi trạng thái đối tác → trạng thái nội bộ.
     */
    public static function mapStatus(string $raw): string
    {
        $key = strtolower(trim($raw));
        return self::STATUS_MAP[$key] ?? ($key !== '' ? $key : self::STATUS_PENDING);
    }

    /**
     * Nhãn hiển thị cho trạng thái.
     */
    public static function statusLabel(string $status): string
    {
        return [
            self::STATUS_PENDING    => 'Đang chờ',
            self::STATUS_PROCESSING => 'Đang xử lý',
            self::STATUS_COMPLETED  => 'Hoàn thành',
            self::STATUS_FAILED     => 'Thất bại',
        ][$status] ?? ucfirst($status);
    }

    public static function statusBadge(string $status): string
    {
        return [
            self::STATUS_PENDING    => 'warning',
            self::STATUS_PROCESSING => 'info',
            self::STATUS_COMPLETED  => 'success',
            self::STATUS_FAILED     => 'danger',
        ][$status] ?? 'secondary';
    }

    // =================================================================
    //  Đồng bộ danh mục
    // =================================================================

    /**
     * Đồng bộ category / product / variant từ API vào DB.
     */
    public static function sync(): array
    {
        if (!self::configured()) {
            return ['ok' => false, 'message' => 'Chưa cấu hình API key Auto MXH.', 'categories' => 0, 'products' => 0, 'variants' => 0];
        }

        $catalog = self::catalog();
        if (!$catalog) {
            // Thử services để lấy dữ liệu khi catalog lỗi
            $svcs = self::services();
            if (!$svcs) {
                return ['ok' => false, 'message' => 'Không tải được danh mục từ API Auto MXH.', 'categories' => 0, 'products' => 0, 'variants' => 0];
            }
            $catalog = self::buildCatalogFromVariants($svcs);
        }

        $nCat = 0;
        $nProd = 0;
        $nVar = 0;

        foreach ($catalog as $cat) {
            $catId = (int)$cat['category_id'];
            if ($catId <= 0) {
                continue;
            }

            $exists = Database::fetchColumn('SELECT id FROM automxh_categories WHERE category_id = ?', [$catId]);
            $row = [
                'category_id' => $catId,
                'slug'        => (string)$cat['slug'],
                'name'        => (string)$cat['name'],
                'icon'        => (string)($cat['icon'] ?? ''),
                'gif'         => (string)($cat['gif'] ?? ''),
                'status'      => (string)($cat['status'] ?? 'active'),
                'sort_order'  => $nCat,
                'updated_at'  => now(),
            ];
            if ($exists) {
                Database::update('automxh_categories', $row, 'id = :id', ['id' => (int)$exists]);
            } else {
                Database::insert('automxh_categories', $row);
            }
            $nCat++;

            foreach ((array)$cat['products'] as $prod) {
                $prodId = (int)$prod['product_id'];
                if ($prodId <= 0) {
                    continue;
                }
                $prow = [
                    'product_id'    => $prodId,
                    'category_id'   => $catId,
                    'category_slug' => (string)$cat['slug'],
                    'name'          => (string)$prod['name'],
                    'min_price'     => (float)($prod['min_price'] ?? 0),
                    'variant_count' => (int)($prod['variant_count'] ?? 0),
                    'is_active'     => 1,
                    'sort_order'    => $nProd,
                    'updated_at'    => now(),
                ];
                $pexists = Database::fetchColumn('SELECT id FROM automxh_products WHERE product_id = ?', [$prodId]);
                if ($pexists) {
                    Database::update('automxh_products', $prow, 'id = :id', ['id' => (int)$pexists]);
                } else {
                    Database::insert('automxh_products', $prow);
                }
                $nProd++;
            }
        }

        // Variant lấy từ endpoint services (đầy đủ giá)
        $variants = self::services();
        foreach ($variants as $v) {
            $vid = (int)$v['variant_id'];
            if ($vid <= 0) {
                continue;
            }
            $vrow = [
                'variant_id'    => $vid,
                'product_id'    => (int)$v['product_id'],
                'category_id'   => (int)$v['category_id'],
                'category_slug' => (string)$v['category_slug'],
                'product_name'  => (string)$v['product_name'],
                'name'          => (string)$v['name'],
                'type'          => (string)$v['type'],
                'badge'         => (string)$v['badge'],
                'quantity'      => (int)$v['quantity'],
                'price'         => (float)$v['price'],
                'vat_amount'    => (float)$v['vat_amount'],
                'total_to_pay'  => (float)$v['total_to_pay'],
                'currency'      => (string)$v['currency'],
                'list_price'    => $v['list_price'],
                'agent_discount_percent' => (float)$v['agent_discount_percent'],
                'allow_avatar'  => $v['allow_avatar'] ? 1 : 0,
                'allow_files'   => $v['allow_files'] ? 1 : 0,
                'description'   => (string)$v['description'],
                'is_active'     => 1,
                'sort_order'    => $nVar,
                'updated_at'    => now(),
            ];
            $vexists = Database::fetchColumn('SELECT id FROM automxh_variants WHERE variant_id = ?', [$vid]);
            if ($vexists) {
                Database::update('automxh_variants', $vrow, 'id = :id', ['id' => (int)$vexists]);
            } else {
                Database::insert('automxh_variants', $vrow);
            }
            $nVar++;
        }

        self::$servicesCache = null;

        return [
            'ok'        => true,
            'message'   => "Đã đồng bộ $nCat nhóm, $nProd sản phẩm, $nVar gói.",
            'categories'=> $nCat,
            'products'  => $nProd,
            'variants'  => $nVar,
        ];
    }

    /**
     * Dựng cây catalog từ danh sách variant (fallback khi endpoint catalog lỗi).
     */
    private static function buildCatalogFromVariants(array $variants): array
    {
        $cats = [];
        foreach ($variants as $v) {
            $cid = (int)$v['category_id'];
            if ($cid <= 0) {
                continue;
            }
            if (!isset($cats[$cid])) {
                $cats[$cid] = [
                    'category_id' => $cid,
                    'name'        => (string)$v['category_name'],
                    'slug'        => (string)$v['category_slug'],
                    'icon'        => '',
                    'gif'         => '',
                    'status'      => 'active',
                    'products'    => [],
                ];
            }
            $pid = (int)$v['product_id'];
            if ($pid > 0 && !isset($cats[$cid]['products'][$pid])) {
                $cats[$cid]['products'][$pid] = [
                    'product_id'    => $pid,
                    'name'          => (string)$v['product_name'],
                    'min_price'     => (float)$v['price'],
                    'variant_count' => 0,
                ];
            }
            if ($pid > 0) {
                $cats[$cid]['products'][$pid]['variant_count']++;
            }
        }

        $out = [];
        foreach ($cats as $c) {
            $c['products'] = array_values($c['products']);
            $out[] = $c;
        }

        return $out;
    }

    // =================================================================
    //  Truy vấn DB
    // =================================================================

    public static function localCategories(): array
    {
        return Database::fetchAll(
            'SELECT * FROM automxh_categories ORDER BY sort_order, name'
        );
    }

    public static function localProducts(?int $categoryId = null): array
    {
        if ($categoryId !== null) {
            return Database::fetchAll(
                'SELECT * FROM automxh_products WHERE category_id = ? ORDER BY sort_order, name',
                [$categoryId]
            );
        }
        return Database::fetchAll('SELECT * FROM automxh_products ORDER BY sort_order, name');
    }

    public static function localVariants(?int $categoryId = null, ?int $productId = null, string $search = ''): array
    {
        $sql = 'SELECT * FROM automxh_variants WHERE is_active = 1';
        $params = [];
        if ($categoryId !== null) {
            $sql .= ' AND category_id = ?';
            $params[] = $categoryId;
        }
        if ($productId !== null) {
            $sql .= ' AND product_id = ?';
            $params[] = $productId;
        }
        if ($search !== '') {
            $sql .= ' AND (name LIKE ? OR product_name LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
        }
        $sql .= ' ORDER BY sort_order, name';

        return $params ? Database::fetchAll($sql, $params) : Database::fetchAll($sql);
    }

    public static function findVariant(int $variantId): ?array
    {
        return Database::fetch(
            'SELECT * FROM automxh_variants WHERE variant_id = ?',
            [$variantId]
        );
    }

    /**
     * Danh mục đầy đủ cho trang khách: category → product → variant.
     */
    public static function catalogTree(string $search = ''): array
    {
        $categories = self::localCategories();
        $out = [];
        foreach ($categories as $cat) {
            $products = [];
            foreach (self::localProducts((int)$cat['category_id']) as $prod) {
                $variants = self::localVariants((int)$cat['category_id'], (int)$prod['product_id'], $search);
                if ($search !== '' && !$variants) {
                    continue;
                }
                $products[] = [
                    'product_id' => (int)$prod['product_id'],
                    'name'       => (string)$prod['name'],
                    'variants'   => $variants,
                ];
            }
            if ($search !== '' && !$products) {
                continue;
            }
            $out[] = [
                'category_id' => (int)$cat['category_id'],
                'name'        => (string)$cat['name'],
                'slug'        => (string)$cat['slug'],
                'icon'        => (string)($cat['icon'] ?? ''),
                'gif'         => (string)($cat['gif'] ?? ''),
                'products'    => $products,
            ];
        }

        return $out;
    }

    // =================================================================
    //  Đơn hàng
    // =================================================================

    /**
     * Tạo bản ghi đơn trong DB.
     */
    public static function createOrderRow(int $userId, array $data): int
    {
        $idem = (string)($data['idempotency_key'] ?? '');

        $row = [
            'user_id'          => $userId,
            'remote_order'     => isset($data['remote_order']) && (int)$data['remote_order'] > 0 ? (int)$data['remote_order'] : null,
            'variant_id'       => (int)($data['variant_id'] ?? 0),
            'product_id'       => (int)($data['product_id'] ?? 0),
            'category_slug'    => (string)($data['category_slug'] ?? ''),
            'variant_name'     => (string)($data['variant_name'] ?? ''),
            'product_name'     => (string)($data['product_name'] ?? ''),
            'link'             => (string)($data['link'] ?? ''),
            'buyer_info'       => (string)($data['buyer_info'] ?? ''),
            'custom_value'     => (string)($data['custom_value'] ?? ''),
            'quantity'         => max(1, (int)($data['quantity'] ?? 1)),
            'price'            => (float)($data['price'] ?? 0),
            'amount'           => (float)($data['amount'] ?? 0),
            'charge'           => (float)($data['charge'] ?? 0),
            'status'           => (string)($data['status'] ?? self::STATUS_PENDING),
            'progress_status'  => (string)($data['progress_status'] ?? ''),
            'progress_remains' => (string)($data['progress_remains'] ?? ''),
            'balance_after'    => $data['balance_after'] ?? null,
            'idempotency_key'  => $idem !== '' ? $idem : null,
            'message'          => mb_substr((string)($data['message'] ?? ''), 0, 500),
            'raw'              => isset($data['raw']) ? json_encode($data['raw'], JSON_UNESCAPED_UNICODE) : null,
            'updated_at'       => now(),
        ];

        if ($idem !== '') {
            $exists = Database::fetchColumn('SELECT id FROM automxh_orders WHERE idempotency_key = ?', [$idem]);
            if ($exists) {
                // Giữ nguyên trạng thái/tiến độ của đơn cũ khi khách bấm lại (replay).
                if (!isset($data['status'])) {
                    unset($row['status'], $row['progress_status'], $row['progress_remains']);
                }
                Database::update('automxh_orders', $row, 'id = :id', ['id' => (int)$exists]);
                return (int)$exists;
            }
        }

        return Database::insert('automxh_orders', $row);
    }

    /**
     * Đơn của user.
     */
    public static function myOrders(int $userId, int $limit = 50): array
    {
        return Database::fetchAll(
            'SELECT * FROM automxh_orders WHERE user_id = ? ORDER BY id DESC LIMIT ' . (int)$limit,
            [$userId]
        );
    }

    public static function findOrder(int $userId, int $id): ?array
    {
        return Database::fetch(
            'SELECT * FROM automxh_orders WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );
    }

    /**
     * Tất cả đơn (admin).
     */
    public static function allOrders(int $limit = 200): array
    {
        return Database::fetchAll(
            'SELECT o.*, u.username FROM automxh_orders o
             LEFT JOIN users u ON u.id = o.user_id
             ORDER BY o.id DESC LIMIT ' . (int)$limit
        );
    }

    /**
     * Cập nhật trạng thái đơn sau khi poll.
     */
    public static function updateOrderStatus(int $orderId, string $status, string $progressStatus = '', string $progressRemains = ''): void
    {
        $row = [
            'status'           => $status,
            'progress_status'  => $progressStatus,
            'progress_remains' => $progressRemains,
            'updated_at'       => now(),
        ];
        Database::update('automxh_orders', $row, 'id = :id', ['id' => $orderId]);
    }

    /**
     * Đơn đang chờ xử lý (admin cần làm tay).
     */
    public static function pendingOrders(int $limit = 100): array
    {
        return Database::fetchAll(
            'SELECT o.*, u.username FROM automxh_orders o
             LEFT JOIN users u ON u.id = o.user_id
             WHERE o.status IN ("pending","processing")
             ORDER BY o.id DESC LIMIT ' . (int)$limit
        );
    }

    public static function stats(): array
    {
        return [
            'orders'     => (int)Database::fetchColumn('SELECT COUNT(*) FROM automxh_orders'),
            'pending'    => (int)Database::fetchColumn('SELECT COUNT(*) FROM automxh_orders WHERE status = "pending"'),
            'processing' => (int)Database::fetchColumn('SELECT COUNT(*) FROM automxh_orders WHERE status = "processing"'),
            'completed'  => (int)Database::fetchColumn('SELECT COUNT(*) FROM automxh_orders WHERE status = "completed"'),
            'failed'     => (int)Database::fetchColumn('SELECT COUNT(*) FROM automxh_orders WHERE status = "failed"'),
            'revenue'    => (float)Database::fetchColumn('SELECT COALESCE(SUM(amount),0) FROM automxh_orders WHERE status IN ("processing","completed")'),
            'variants'   => (int)Database::fetchColumn('SELECT COUNT(*) FROM automxh_variants WHERE is_active = 1'),
            'categories' => (int)Database::fetchColumn('SELECT COUNT(*) FROM automxh_categories'),
        ];
    }

    // =================================================================
    //  API key của user
    // =================================================================

    /**
     * Lưu apikey cho user (dùng chung hệ key `ttmmo_api_keys` với SMM).
     */
    public static function saveUserKey(int $userId, string $apikey, string $label = ''): bool
    {
        $apikey = trim($apikey);
        if ($apikey === '') {
            return false;
        }

        $exists = Database::fetchColumn(
            'SELECT id FROM ttmmo_api_keys WHERE user_id = ? AND apikey = ?',
            [$userId, $apikey]
        );
        if ($exists) {
            Database::update('ttmmo_api_keys', [
                'is_active'  => 1,
                'label'      => $label !== '' ? $label : null,
                'updated_at' => now(),
            ], 'id = :id', ['id' => (int)$exists]);
            return true;
        }

        Database::insert('ttmmo_api_keys', [
            'user_id'    => $userId,
            'apikey'     => $apikey,
            'label'      => $label !== '' ? $label : null,
            'is_active'  => 1,
            'updated_at' => now(),
        ]);

        return true;
    }

    /**
     * Lấy apikey đang dùng của user.
     */
    public static function userKey(int $userId): ?string
    {
        $row = Database::fetch(
            'SELECT apikey FROM ttmmo_api_keys WHERE user_id = ? AND is_active = 1 ORDER BY id DESC LIMIT 1',
            [$userId]
        );
        return $row ? (string)$row['apikey'] : null;
    }

    public static function userKeys(int $userId): array
    {
        return Database::fetchAll(
            'SELECT * FROM ttmmo_api_keys WHERE user_id = ? ORDER BY id DESC',
            [$userId]
        );
    }

    /**
     * Xoá apikey của user.
     */
    public static function deleteUserKey(int $userId, int $keyId): bool
    {
        return Database::delete('ttmmo_api_keys', 'id = :id AND user_id = :u', ['id' => $keyId, 'u' => $userId]) > 0;
    }

    /**
     * Đặt apikey làm key chính (đang dùng).
     */
    public static function setPrimaryKey(int $userId, int $keyId): bool
    {
        Database::query('UPDATE ttmmo_api_keys SET is_active = 0 WHERE user_id = ?', [$userId]);
        return Database::update('ttmmo_api_keys', [
            'is_active'  => 1,
            'updated_at' => now(),
        ], 'id = :id AND user_id = :u', ['id' => $keyId, 'u' => $userId]) > 0;
    }

    /**
     * Đồng bộ trạng thái đơn từ API (dùng cho nút "Làm mới" & cron).
     */
    public static function refreshStatuses(int $limit = 30): array
    {
        $rows = Database::fetchAll(
            'SELECT id, remote_order FROM automxh_orders
             WHERE status IN ("pending","processing") AND remote_order IS NOT NULL
             ORDER BY id DESC LIMIT ' . (int)$limit
        );

        $updated = 0;
        foreach ($rows as $row) {
            $st = self::status((int)$row['remote_order']);
            if (!$st['ok']) {
                continue;
            }
            $current = Database::fetchColumn('SELECT status FROM automxh_orders WHERE id = ?', [(int)$row['id']]);
            if ($current !== $st['status']) {
                $updated++;
            }
            self::updateOrderStatus(
                (int)$row['id'],
                $st['status'],
                $st['progress_status'],
                $st['progress_remains']
            );
        }

        return ['ok' => true, 'checked' => count($rows), 'updated' => $updated];
    }

    /**
     * Tự đồng bộ danh mục (cache 5 phút).
     */
    public static function shouldSync(): bool
    {
        $last = (int)self::cacheGet('automxh_sync');
        return (time() - $last) > 300;
    }

    public static function markSynced(): void
    {
        self::cacheSet('automxh_sync', (string)time(), 3600);
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
