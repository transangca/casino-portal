<?php
/**
 * Dịch vụ SMM — mua like / view / comment / share / follow (API đối tác TrungTamMMO).
 *
 * Base URL: https://trungtammmo.vn/api/external/smm
 *
 * Endpoint (tất cả dùng apikey riêng của user):
 *   GET  /services?platform=&category=&search=&page=&per_page=  → dịch vụ + giá web
 *   GET  /categories?platform=&search=                        → card category
 *   GET  /category/{slug}                                     → dịch vụ trong nhóm
 *   POST /quote                                               → tính giá (không tạo đơn)
 *   POST /order  (alias /add)                                 → tạo đơn thật + trừ ví chính
 *   GET  /status?order=                                       → trạng thái 1 đơn
 *   GET  /status?orders=1024,1025                             → trạng thái nhiều đơn (tối đa 100)
 *   GET  /orders?service_ids=&limit=                          → lịch sử đơn
 *   GET  /balance                                             → số dư ví chính
 *
 * Công thức giá (không VAT):
 *   total = ceil(quantity / 1000 * price_per_1k_vnd)
 *   price_per_unit_vnd = price_per_1k_vnd / 1000
 *
 * Dịch vụ comment: quantity lấy theo số dòng comment hợp lệ (mỗi dòng 1 comment),
 * khách không tự nhập quantity cho các dịch vụ này.
 */

namespace App\Services;

use App\Core\Crypto;
use App\Core\Database;

class SmmService
{
    public const DEFAULT_BASE_URL = 'https://trungtammmo.vn/api/external/smm';

    public const STATUS_PENDING    = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED  = 'completed';
    public const STATUS_FAILED     = 'failed';

    /** Quy đổi trạng thái đối tác → trạng thái nội bộ */
    private const STATUS_MAP = [
        'completed'   => self::STATUS_COMPLETED,
        'complete'    => self::STATUS_COMPLETED,
        'success'     => self::STATUS_COMPLETED,
        'done'        => self::STATUS_COMPLETED,
        'finished'    => self::STATUS_COMPLETED,
        'partial'     => self::STATUS_PROCESSING,
        'processing'  => self::STATUS_PROCESSING,
        'running'     => self::STATUS_PROCESSING,
        'in_progress' => self::STATUS_PROCESSING,
        'pending'     => self::STATUS_PENDING,
        'queued'      => self::STATUS_PENDING,
        'waiting'     => self::STATUS_PENDING,
        'failed'      => self::STATUS_FAILED,
        'error'       => self::STATUS_FAILED,
        'cancelled'   => self::STATUS_FAILED,
        'canceled'    => self::STATUS_FAILED,
    ];

    /** Trạng thái đơn đã kết thúc (không poll lại) */
    public const CLOSED_STATUSES = [self::STATUS_COMPLETED, self::STATUS_FAILED];

    // =================================================================
    //  Cấu hình
    // =================================================================

    public static function baseUrl(): string
    {
        $url = rtrim(trim((string)setting('smm_base_url', '')), '/');
        if ($url === '') {
            $url = (string)config('smm.base_url', self::DEFAULT_BASE_URL);
        }
        $url = rtrim(trim($url), '/');

        return $url !== '' ? $url : self::DEFAULT_BASE_URL;
    }

    /**
     * API key hệ thống (key đại lý) — dùng để đồng bộ bảng giá & quote đối chiếu.
     */
    public static function apiKey(): string
    {
        $raw = (string)setting('smm_api_key', '');
        if ($raw !== '') {
            $dec = Crypto::decrypt($raw);
            $key = $dec ?? $raw;
            if ($key !== '') {
                return trim($key);
            }
        }

        return trim((string)config('smm.api_key', ''));
    }

    public static function saveApiKey(string $key): void
    {
        $key = trim($key);
        setting_set('smm_api_key', $key === '' ? '' : Crypto::encrypt($key), 'SMM', 'password');
    }

    public static function configured(): bool
    {
        return self::apiKey() !== '';
    }

    public static function enabled(): bool
    {
        $v = (string)setting('smm_service_on', '');
        if ($v === '') {
            return (bool)config('smm.service_on', true);
        }
        return $v === '1';
    }

    /** Biên lợi nhuận (%) áp thêm lên giá bảng giá đối tác */
    public static function markup(): float
    {
        return (float)setting('smm_markup', '0');
    }

    /** VAT của dịch vụ SMM luôn bằng 0 */
    public static function vatPercent(): float
    {
        return 0.0;
    }

    // =================================================================
    //  Tính tiền
    // =================================================================

    /**
     * Giá bán / 1.000 đơn vị sau khi cộng biên lợi nhuận.
     */
    public static function sellPricePer1k(float $pricePer1k): float
    {
        $markup = self::markup();
        return $markup > 0 ? (float)ceil($pricePer1k * (1 + $markup / 100)) : $pricePer1k;
    }

    /**
     * Tổng tiền = ceil(quantity / 1000 * price_per_1k_vnd) — không VAT.
     */
    public static function calcTotal(float $pricePer1k, int $quantity): float
    {
        if ($quantity <= 0) {
            return 0.0;
        }
        return (float)ceil($quantity / 1000 * $pricePer1k);
    }

    /**
     * Giá lẻ tham khảo (1 đơn vị).
     */
    public static function pricePerUnit(float $pricePer1k): float
    {
        return $pricePer1k / 1000;
    }

    // =================================================================
    //  Giảm giá đại lý (admin cài % giảm cho từng apikey)
    // =================================================================

    /**
     * Lấy % giảm của 1 apikey (0 nếu không có).
     */
    public static function keyDiscount(string $apikey): float
    {
        $apikey = trim($apikey);
        if ($apikey === '') {
            return 0.0;
        }

        $row = Database::fetch(
            'SELECT agent_discount_percent FROM ttmmo_api_keys WHERE apikey = ? LIMIT 1',
            [$apikey]
        );
        if (!$row) {
            return 0.0;
        }

        return max(0.0, min(100.0, (float)$row['agent_discount_percent']));
    }

    /**
     * Giá / 1.000 sau khi áp % giảm đại lý.
     */
    public static function applyKeyDiscount(float $pricePer1k, float $discountPercent): float
    {
        if ($discountPercent <= 0) {
            return $pricePer1k;
        }

        return (float)ceil($pricePer1k * (1 - $discountPercent / 100));
    }

    /**
     * Tính giá bán cuối cùng cho 1 apikey: giá gốc + markup - giảm đại lý.
     *
     * @return array{price:float,list_price:?float,discount:float,discount_amount:float}
     */
    public static function priceForKey(float $costPer1k, string $apikey): array
    {
        $sell = self::sellPricePer1k($costPer1k);
        $disc = self::keyDiscount($apikey);
        $price = self::applyKeyDiscount($sell, $disc);

        return [
            'price'          => $price,
            'list_price'     => $sell,
            'discount'       => $disc,
            'discount_amount'=> $sell - $price,
        ];
    }

    /**
     * Đếm số dòng comment hợp lệ — dùng làm quantity cho dịch vụ comment.
     */
    public static function countCommentLines(string $comments): int
    {
        if (trim($comments) === '') {
            return 0;
        }
        $lines = preg_split('/\r\n|\r|\n/', $comments) ?: [];
        $n = 0;
        foreach ($lines as $line) {
            if (trim($line) !== '') {
                $n++;
            }
        }
        return $n;
    }

    /**
     * Chuẩn hoá danh sách comment (bỏ dòng trống, giữ nguyên nội dung).
     */
    public static function normalizeComments(string $comments): string
    {
        if (trim($comments) === '') {
            return '';
        }
        $lines = preg_split('/\r\n|\r|\n/', $comments) ?: [];
        $out = [];
        foreach ($lines as $line) {
            $t = trim($line);
            if ($t !== '') {
                $out[] = $t;
            }
        }
        return implode("\n", $out);
    }

    // =================================================================
    //  HTTP
    // =================================================================

    /**
     * Gọi API SMM của đối tác.
     *
     * @param string|null $apiKey  key dùng để gọi (mặc định key hệ thống)
     * @return array ['ok'=>bool,'data'=>array,'message'=>string,'status'=>int]
     */
    public static function request(string $method, string $endpoint, array $payload = [], array $query = [], ?string $apiKey = null): array
    {
        $apiKey = $apiKey !== null ? trim($apiKey) : self::apiKey();
        if ($apiKey === '') {
            return [
                'ok'      => false,
                'data'    => [],
                'message' => 'Chưa cấu hình API key cho dịch vụ SMM. Admin nhập tại /admin/smm.',
                'status'  => 401,
            ];
        }

        $url = self::baseUrl() . '/' . ltrim($endpoint, '/');
        if ($query) {
            $clean = array_filter($query, static fn($v) => $v !== '' && $v !== null);
            if ($clean) {
                $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($clean);
            }
        }

        $headers = [
            'x-api-key: ' . $apiKey,
            'Authorization: Bearer ' . $apiKey,
            'Accept: application/json',
        ];

        $opts = ['timeout' => 30, 'retries' => 1, 'allow_error' => true];

        if ($method === 'POST') {
            $body = HttpClient::postJson($url, $payload, $headers, $opts);
        } else {
            $body = HttpClient::get($url, $headers, $opts);
        }

        if ($body === null) {
            return [
                'ok'      => false,
                'data'    => [],
                'message' => 'Không kết nối được tới API SMM. Vui lòng thử lại sau.',
                'status'  => 0,
            ];
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return [
                'ok'      => false,
                'data'    => [],
                'message' => 'API SMM trả về dữ liệu không hợp lệ.',
                'status'  => 0,
            ];
        }

        $success = (bool)($decoded['success'] ?? false);
        $message = (string)($decoded['message'] ?? '');

        return [
            'ok'      => $success,
            'data'    => $decoded,
            'message' => $message !== '' ? $message : ($success ? 'OK' : 'Không rõ nguyên nhân.'),
            'status'  => $success ? 200 : 400,
        ];
    }

    // =================================================================
    //  Endpoint wrappers
    // =================================================================

    /**
     * Danh sách dịch vụ + giá web.
     */
    public static function services(array $query = [], ?string $apiKey = null): array
    {
        $res = self::request('GET', '/services', [], $query, $apiKey);
        if (!$res['ok']) {
            return [];
        }

        $out = [];
        foreach ((array)($res['data']['data'] ?? []) as $s) {
            if (is_array($s)) {
                $out[] = self::normalizeService($s);
            }
        }
        return $out;
    }

    /**
     * Card category (nhóm dịch vụ).
     */
    public static function categories(string $platform = '', string $search = '', ?string $apiKey = null): array
    {
        $query = [];
        if ($platform !== '') {
            $query['platform'] = $platform;
        }
        if ($search !== '') {
            $query['search'] = $search;
        }

        $res = self::request('GET', '/categories', [], $query, $apiKey);
        if (!$res['ok']) {
            return [];
        }

        $out = [];
        foreach ((array)($res['data']['data'] ?? []) as $c) {
            if (!is_array($c)) {
                continue;
            }
            $priceRange = (array)($c['price_range'] ?? []);
            $out[] = [
                'category'           => (string)($c['category'] ?? ''),
                'clean_category'     => (string)($c['clean_category'] ?? ''),
                'category_slug'      => (string)($c['category_slug'] ?? ''),
                'platform'           => (string)($c['platform'] ?? ''),
                'service_count'      => (int)($c['service_count'] ?? 0),
                'min'                => (int)($c['min'] ?? 0),
                'max'                => (int)($c['max'] ?? 0),
                'price_min_per_unit' => (float)($priceRange['min_per_unit_vnd'] ?? 0),
                'price_max_per_unit' => (float)($priceRange['max_per_unit_vnd'] ?? 0),
                'display_per_unit'   => (string)($priceRange['display_per_unit'] ?? ''),
            ];
        }
        return $out;
    }

    /**
     * Dịch vụ thuộc một nhóm.
     */
    public static function categoryServices(string $slug, ?string $apiKey = null): array
    {
        if ($slug === '') {
            return [];
        }

        $res = self::request('GET', '/category/' . rawurlencode($slug), [], [], $apiKey);
        if (!$res['ok']) {
            return [];
        }

        $out = [];
        foreach ((array)($res['data']['data'] ?? []) as $s) {
            if (is_array($s)) {
                $out[] = self::normalizeService($s);
            }
        }
        return $out;
    }

    /**
     * Tính giá (không tạo đơn).
     */
    public static function quote(int $serviceId, int $quantity = 0, string $link = '', string $comments = '', string $reaction = '', ?string $apiKey = null): array
    {
        $payload = ['service_ref' => $serviceId];
        if ($quantity > 0) {
            $payload['quantity'] = $quantity;
        }
        if ($link !== '') {
            $payload['link'] = $link;
        }
        $comments = self::normalizeComments($comments);
        if ($comments !== '') {
            $payload['comments'] = $comments;
        }
        if ($reaction !== '') {
            $payload['reaction'] = $reaction;
        }

        $res = self::request('POST', '/quote', $payload, [], $apiKey);
        if (!$res['ok']) {
            return ['ok' => false, 'message' => $res['message'], 'service' => [], 'checkout' => [], 'data' => []];
        }

        return [
            'ok'       => true,
            'message'  => '',
            'service'  => self::normalizeService((array)($res['data']['service'] ?? [])),
            'checkout' => (array)($res['data']['checkout'] ?? []),
            'discount' => (array)($res['data']['agent_discount'] ?? []),
            'data'     => $res['data'],
        ];
    }

    /**
     * Tạo đơn thật trên hệ đối tác.
     */
    public static function createOrder(int $serviceId, string $link, int $quantity = 0, string $comments = '', string $reaction = '', ?string $apiKey = null): array
    {
        $payload = [
            'service_ref' => $serviceId,
            'link'        => $link,
        ];
        if ($quantity > 0) {
            $payload['quantity'] = $quantity;
        }
        $comments = self::normalizeComments($comments);
        if ($comments !== '') {
            $payload['comments'] = $comments;
        }
        if ($reaction !== '') {
            $payload['reaction'] = $reaction;
        }

        $res = self::request('POST', '/order', $payload, [], $apiKey);
        if (!$res['ok']) {
            return ['ok' => false, 'message' => $res['message'], 'data' => [], 'raw' => $res['data']];
        }

        $d = (array)($res['data']['data'] ?? []);
        $orderId = (int)($res['data']['order'] ?? $d['order'] ?? $d['id'] ?? 0);

        return [
            'ok'       => true,
            'message'  => (string)($res['data']['message'] ?? 'Đơn SMM đã được tạo và trừ tiền ví chính.'),
            'order_id' => $orderId,
            'charge'   => (float)($res['data']['charge'] ?? $d['total_to_pay'] ?? 0),
            'balance'  => (float)($res['data']['balance'] ?? $d['balance_after'] ?? 0),
            'status'   => self::mapStatus((string)($d['status'] ?? self::STATUS_PENDING)),
            'data'     => $d,
            'raw'      => $res['data'],
        ];
    }

    /**
     * Trạng thái 1 đơn.
     */
    public static function status(int $orderId, ?string $apiKey = null): array
    {
        $res = self::request('GET', '/status', [], ['order' => $orderId], $apiKey);
        if (!$res['ok']) {
            return ['ok' => false, 'message' => $res['message'], 'data' => []];
        }

        return [
            'ok'      => true,
            'message' => '',
            'status'  => self::mapStatus((string)($res['data']['data']['status'] ?? '')),
            'data'    => (array)($res['data']['data'] ?? []),
        ];
    }

    /**
     * Trạng thái nhiều đơn (tối đa 100 mã).
     */
    public static function statusBatch(array $orderIds, ?string $apiKey = null): array
    {
        $ids = [];
        foreach ($orderIds as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        $ids = array_slice(array_values(array_unique($ids)), 0, 100);
        if (!$ids) {
            return ['ok' => false, 'message' => 'Thiếu danh sách order.', 'data' => []];
        }

        $res = self::request('GET', '/status', [], ['orders' => implode(',', $ids)], $apiKey);
        if (!$res['ok']) {
            return ['ok' => false, 'message' => $res['message'], 'data' => []];
        }

        return ['ok' => true, 'message' => '', 'data' => (array)($res['data']['data'] ?? [])];
    }

    /**
     * Lịch sử đơn của đối tác.
     */
    public static function orders(int $limit = 50, string $serviceIds = '', ?string $apiKey = null): array
    {
        $query = ['limit' => max(1, min(100, $limit))];
        if ($serviceIds !== '') {
            $query['service_ids'] = $serviceIds;
        }

        $res = self::request('GET', '/orders', [], $query, $apiKey);
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
                'order'        => (string)($o['order'] ?? ''),
                'service_id'   => (int)($o['service_id'] ?? 0),
                'service_name' => (string)($o['service_name'] ?? ''),
                'quantity'     => (int)($o['quantity'] ?? 0),
                'price'        => (float)($o['price'] ?? 0),
                'status'       => self::mapStatus((string)($o['status'] ?? '')),
                'raw_status'   => (string)($o['status'] ?? ''),
                'start_count'  => (int)($o['start_count'] ?? 0),
                'remains'      => (int)($o['remains'] ?? 0),
                'created_at'   => (string)($o['created_at'] ?? ''),
                'updated_at'   => (string)($o['updated_at'] ?? ''),
            ];
        }
        return $out;
    }

    /**
     * Số dư ví chính.
     */
    public static function balance(?string $apiKey = null): array
    {
        $res = self::request('GET', '/balance', [], [], $apiKey);
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
                'user_id'    => (int)($d['user_id'] ?? 0),
                'username'   => (string)($d['username'] ?? ''),
                'api_status' => (string)($d['api_status'] ?? ''),
            ],
            'message'  => '',
        ];
    }

    // =================================================================
    //  Chuẩn hoá
    // =================================================================

    /**
     * Chuẩn hoá 1 service SMM.
     */
    public static function normalizeService(array $s): array
    {
        $price1k = (float)($s['price_per_1k_vnd'] ?? 0);
        $list    = $s['list_price_per_1k_vnd'] ?? null;

        return [
            'service_id'               => (int)($s['id'] ?? $s['service'] ?? 0),
            'name'                     => (string)($s['name'] ?? ''),
            'type'                     => (string)($s['type'] ?? ''),
            'category'                 => (string)($s['category'] ?? ''),
            'category_slug'            => (string)($s['category_slug'] ?? ''),
            'clean_category'           => (string)($s['clean_category'] ?? ''),
            'platform'                 => (string)($s['platform'] ?? ''),
            'min'                      => (int)($s['min'] ?? 0),
            'max'                      => (int)($s['max'] ?? 0),
            'refill'                   => (bool)($s['refill'] ?? false),
            'price_per_1k_vnd'         => $price1k,
            'price_per_unit_vnd'       => (float)($s['price_per_unit_vnd'] ?? self::pricePerUnit($price1k)),
            'list_price_per_1k_vnd'    => $list === null ? null : (float)$list,
            'agent_discount_percent'   => (float)($s['agent_discount_percent'] ?? 0),
            'is_comment_service'       => (bool)($s['is_comment_service'] ?? false),
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
     * Đồng bộ category + service từ API vào DB.
     */
    public static function sync(): array
    {
        if (!self::configured()) {
            return ['ok' => false, 'message' => 'Chưa cấu hình API key SMM.', 'categories' => 0, 'services' => 0];
        }

        $services = self::services(['per_page' => 500]);
        if (!$services) {
            return ['ok' => false, 'message' => 'Không tải được danh sách dịch vụ từ API SMM.', 'categories' => 0, 'services' => 0];
        }

        $nCat = 0;
        $nSvc = 0;
        $seenServiceIds = [];

        // Nhóm dịch vụ từ endpoint /categories (card trên web)
        $slugByCategory = [];
        foreach (self::categories() as $c) {
            $slug = (string)$c['category_slug'];
            if ($slug === '') {
                continue;
            }
            $row = [
                'category_slug'      => $slug,
                'category'           => (string)$c['category'],
                'clean_category'     => (string)$c['clean_category'],
                'platform'           => (string)$c['platform'],
                'service_count'      => (int)$c['service_count'],
                'min'                => (int)$c['min'],
                'max'                => (int)$c['max'],
                'price_min_per_unit' => (float)$c['price_min_per_unit'],
                'price_max_per_unit' => (float)$c['price_max_per_unit'],
                'display_per_unit'   => (string)$c['display_per_unit'],
                'sort_order'         => $nCat,
                'updated_at'         => now(),
            ];
            $exists = Database::fetchColumn('SELECT id FROM smm_categories WHERE category_slug = ?', [$slug]);
            if ($exists) {
                Database::update('smm_categories', $row, 'id = :id', ['id' => (int)$exists]);
            } else {
                Database::insert('smm_categories', $row);
            }

            // Ánh xạ tên category (gốc & đã bỏ prefix) → slug chuẩn của đối tác
            $slugByCategory[self::normalizeCategoryName((string)$c['category'])] = $slug;
            $slugByCategory[self::normalizeCategoryName((string)$c['clean_category'])] = $slug;
            $nCat++;
        }

        foreach ($services as $s) {
            $sid = (int)$s['service_id'];
            if ($sid <= 0) {
                continue;
            }

            // Ưu tiên slug chuẩn của đối tác, fallback tự sinh
            $slug = $slugByCategory[self::normalizeCategoryName((string)$s['category'])] ?? '';
            if ($slug === '') {
                $slug = $slugByCategory[self::normalizeCategoryName((string)$s['clean_category'])] ?? '';
            }
            if ($slug === '') {
                $slug = self::slugify((string)($s['category'] ?: $s['clean_category']));
            }
            $row = [
                'service_id'             => $sid,
                'name'                   => mb_substr((string)$s['name'], 0, 400),
                'type'                   => (string)$s['type'],
                'category'               => (string)$s['category'],
                'category_slug'          => $slug,
                'clean_category'         => (string)$s['clean_category'],
                'platform'               => (string)$s['platform'],
                'min'                    => (int)$s['min'],
                'max'                    => (int)$s['max'],
                'refill'                 => $s['refill'] ? 1 : 0,
                'price_per_1k_vnd'       => (float)$s['price_per_1k_vnd'],
                'price_per_unit_vnd'     => (float)$s['price_per_unit_vnd'],
                'list_price_per_1k_vnd'  => $s['list_price_per_1k_vnd'],
                'agent_discount_percent' => (float)$s['agent_discount_percent'],
                'is_comment_service'     => $s['is_comment_service'] ? 1 : 0,
                'is_active'              => 1,
                'sort_order'             => $nSvc,
                'updated_at'             => now(),
            ];

            $exists = Database::fetchColumn('SELECT id FROM smm_services WHERE service_id = ?', [$sid]);
            if ($exists) {
                Database::update('smm_services', $row, 'id = :id', ['id' => (int)$exists]);
            } else {
                Database::insert('smm_services', $row);
            }
            $seenServiceIds[] = $sid;
            $nSvc++;
        }

        // Dịch vụ không còn trên API đối tác → tắt (không xoá để giữ lịch sử đơn)
        if ($seenServiceIds) {
            $inList = implode(',', array_map('intval', $seenServiceIds));
            Database::query(
                "UPDATE smm_services SET is_active = 0, updated_at = NOW() WHERE is_active = 1 AND service_id NOT IN ($inList)"
            );
        }

        return [
            'ok'         => true,
            'message'    => "Đã đồng bộ $nCat nhóm, $nSvc dịch vụ.",
            'categories' => $nCat,
            'services'   => $nSvc,
        ];
    }

    /**
     * Tạo slug nhóm dịch vụ (giống logic web SMM).
     */
    public static function slugify(string $text): string
    {
        $text = trim($text);
        $text = preg_replace('/\[[^\]]*\]/', '', $text);       // bỏ prefix [FB], [TT]...
        $text = trim((string)preg_replace('/[^\p{L}\p{N}]+/u', '-', $text));
        $text = trim($text, '-');
        $text = mb_strtolower($text, 'UTF-8');
        return $text === '' ? '' : $text;
    }

    /**
     * Chuẩn hoá tên category để so khớp (bỏ prefix [FB], bỏ khoảng trắng, không phân biệt hoa thường).
     */
    public static function normalizeCategoryName(string $name): string
    {
        $name = preg_replace('/\[[^\]]*\]/', '', $name);
        $name = trim((string)preg_replace('/\s+/u', ' ', (string)$name));
        return mb_strtolower($name, 'UTF-8');
    }

    // =================================================================
    //  Truy vấn DB
    // =================================================================

    public static function localCategories(): array
    {
        return Database::fetchAll('SELECT * FROM smm_categories ORDER BY sort_order, category');
    }

    public static function localServices(?string $platform = null, ?string $categorySlug = null, string $search = ''): array
    {
        $sql = 'SELECT * FROM smm_services WHERE is_active = 1';
        $params = [];

        if ($platform !== null && $platform !== '') {
            $sql .= ' AND platform = ?';
            $params[] = $platform;
        }
        if ($categorySlug !== null && $categorySlug !== '') {
            $sql .= ' AND category_slug = ?';
            $params[] = $categorySlug;
        }
        if ($search !== '') {
            $sql .= ' AND (name LIKE ? OR category LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
        }
        $sql .= ' ORDER BY platform, category, sort_order, name';

        return $params ? Database::fetchAll($sql, $params) : Database::fetchAll($sql);
    }

    public static function platforms(): array
    {
        return Database::fetchAll(
            'SELECT DISTINCT platform FROM smm_services WHERE is_active = 1 AND platform <> "" ORDER BY platform'
        );
    }

    public static function findService(int $serviceId): ?array
    {
        return Database::fetch('SELECT * FROM smm_services WHERE service_id = ?', [$serviceId]);
    }

    /**
     * Cây nhóm → dịch vụ cho trang khách.
     */
    public static function catalogTree(string $platform = '', string $search = ''): array
    {
        $services = self::localServices($platform !== '' ? $platform : null, null, $search);

        $tree = [];
        foreach ($services as $s) {
            $slug = (string)$s['category_slug'];
            $key  = $slug !== '' ? $slug : (string)$s['category'];
            if (!isset($tree[$key])) {
                $tree[$key] = [
                    'category_slug' => $slug,
                    'category'      => (string)$s['category'],
                    'clean_category'=> (string)$s['clean_category'],
                    'platform'      => (string)$s['platform'],
                    'services'      => [],
                ];
            }
            $tree[$key]['services'][] = $s;
        }

        return array_values($tree);
    }

    // =================================================================
    //  Đơn hàng
    // =================================================================

    /**
     * Tạo bản ghi đơn trong DB (idempotent theo idempotency_key).
     */
    public static function createOrderRow(int $userId, array $data): int
    {
        $idem = (string)($data['idempotency_key'] ?? '');

        $row = [
            'user_id'         => $userId,
            'remote_order'    => isset($data['remote_order']) && (string)$data['remote_order'] !== ''
                                    ? (string)$data['remote_order'] : null,
            'service_id'      => (int)($data['service_id'] ?? 0),
            'service_name'    => mb_substr((string)($data['service_name'] ?? ''), 0, 400),
            'category_slug'   => (string)($data['category_slug'] ?? ''),
            'platform'        => (string)($data['platform'] ?? ''),
            'link'            => (string)($data['link'] ?? ''),
            'quantity'        => max(0, (int)($data['quantity'] ?? 0)),
            'comments'        => $data['comments'] ?? null,
            'reaction'        => (string)($data['reaction'] ?? ''),
            'price'           => (float)($data['price'] ?? 0),
            'amount'          => (float)($data['amount'] ?? 0),
            'charge'          => (float)($data['charge'] ?? 0),
            'status'          => (string)($data['status'] ?? self::STATUS_PENDING),
            'start_count'     => (string)($data['start_count'] ?? ''),
            'remains'         => (string)($data['remains'] ?? ''),
            'idempotency_key' => $idem !== '' ? $idem : null,
            'message'         => mb_substr((string)($data['message'] ?? ''), 0, 500),
            'raw'             => isset($data['raw']) ? json_encode($data['raw'], JSON_UNESCAPED_UNICODE) : null,
            'updated_at'      => now(),
        ];

        if ($idem !== '') {
            $exists = Database::fetchColumn('SELECT id FROM smm_orders WHERE idempotency_key = ?', [$idem]);
            if ($exists) {
                // Không ghi đè trạng thái của đơn cũ khi khách bấm lại.
                unset($row['status'], $row['start_count'], $row['remains']);
                Database::update('smm_orders', $row, 'id = :id', ['id' => (int)$exists]);
                return (int)$exists;
            }
        }

        return Database::insert('smm_orders', $row);
    }

    public static function myOrders(int $userId, int $limit = 20): array
    {
        return Database::fetchAll(
            'SELECT * FROM smm_orders WHERE user_id = ? ORDER BY id DESC LIMIT ' . (int)$limit,
            [$userId]
        );
    }

    public static function findOrder(int $userId, int $id): ?array
    {
        return Database::fetch('SELECT * FROM smm_orders WHERE id = ? AND user_id = ?', [$id, $userId]);
    }

    public static function allOrders(int $limit = 200): array
    {
        return Database::fetchAll(
            'SELECT o.*, u.username FROM smm_orders o
             LEFT JOIN users u ON u.id = o.user_id
             ORDER BY o.id DESC LIMIT ' . (int)$limit
        );
    }

    public static function pendingOrders(int $limit = 100): array
    {
        return Database::fetchAll(
            'SELECT o.*, u.username FROM smm_orders o
             LEFT JOIN users u ON u.id = o.user_id
             WHERE o.status IN ("pending","processing")
             ORDER BY o.id DESC LIMIT ' . (int)$limit
        );
    }

    public static function updateOrderStatus(int $orderId, string $status, string $startCount = '', string $remains = ''): void
    {
        Database::update('smm_orders', [
            'status'      => $status,
            'start_count' => $startCount,
            'remains'     => $remains,
            'updated_at'  => now(),
        ], 'id = :id', ['id' => $orderId]);
    }

    public static function stats(): array
    {
        return [
            'orders'     => (int)Database::fetchColumn('SELECT COUNT(*) FROM smm_orders'),
            'pending'    => (int)Database::fetchColumn('SELECT COUNT(*) FROM smm_orders WHERE status = "pending"'),
            'processing' => (int)Database::fetchColumn('SELECT COUNT(*) FROM smm_orders WHERE status = "processing"'),
            'completed'  => (int)Database::fetchColumn('SELECT COUNT(*) FROM smm_orders WHERE status = "completed"'),
            'failed'     => (int)Database::fetchColumn('SELECT COUNT(*) FROM smm_orders WHERE status = "failed"'),
            'revenue'    => (float)Database::fetchColumn('SELECT COALESCE(SUM(amount),0) FROM smm_orders WHERE status IN ("processing","completed")'),
            'services'   => (int)Database::fetchColumn('SELECT COUNT(*) FROM smm_services WHERE is_active = 1'),
            'categories' => (int)Database::fetchColumn('SELECT COUNT(*) FROM smm_categories'),
        ];
    }

    // =================================================================
    //  Đồng bộ trạng thái đơn
    // =================================================================

    /**
     * Cập nhật trạng thái các đơn đang chạy (dùng cho nút "Làm mới" & cron).
     * Dùng endpoint batch /status?orders=... tối đa 100 mã/lần.
     */
    public static function refreshStatuses(int $limit = 100, ?string $apiKey = null): array
    {
        $rows = Database::fetchAll(
            'SELECT id, remote_order FROM smm_orders
             WHERE status IN ("pending","processing") AND remote_order IS NOT NULL
             ORDER BY id DESC LIMIT ' . (int)$limit
        );
        if (!$rows) {
            return ['ok' => true, 'checked' => 0, 'updated' => 0];
        }

        // Gom mã đơn theo user (mỗi user 1 lần gọi với key tương ứng)
        $updated = 0;
        $checked = 0;

        foreach (self::groupRowsByUser($rows) as $userId => $group) {
            // Map: remote_order => id trong DB
            $map = [];
            foreach ($group as $r) {
                $map[(string)$r['remote_order']] = (int)$r['id'];
            }
            $ids = array_keys($map);
            if (!$ids) {
                continue;
            }

            $key = $apiKey;
            if ($key === null) {
                $key = self::userKey((int)$userId) ?? self::apiKey();
            }

            $res = self::statusBatch($ids, $key);
            if (!$res['ok']) {
                continue;
            }

            foreach ($res['data'] as $orderKey => $d) {
                if (!is_array($d)) {
                    continue;
                }
                $localId = $map[(string)$orderKey] ?? null;
                if ($localId === null) {
                    continue;
                }
                $checked++;
                $status = self::mapStatus((string)($d['status'] ?? ''));
                $current = Database::fetchColumn('SELECT status FROM smm_orders WHERE id = ?', [$localId]);
                if ($current !== $status) {
                    $updated++;
                }
                self::updateOrderStatus(
                    (int)$localId,
                    $status,
                    (string)($d['start_count'] ?? ''),
                    (string)($d['remains'] ?? '')
                );
            }
        }

        return ['ok' => true, 'checked' => $checked, 'updated' => $updated];
    }

    /**
     * Nhóm các dòng đơn theo user_id (bản ghi có user_id).
     */
    private static function groupRowsByUser(array $rows): array
    {
        $ids = [];
        foreach ($rows as $r) {
            $ids[] = (int)$r['id'];
        }
        if (!$ids) {
            return [];
        }
        $in = implode(',', array_map('intval', $ids));
        $map = Database::fetchAll(
            'SELECT id, user_id, remote_order FROM smm_orders WHERE id IN (' . $in . ')'
        );

        $out = [];
        foreach ($map as $m) {
            $out[(int)$m['user_id']][] = $m;
        }
        return $out;
    }

    /**
     * Tự đồng bộ danh mục (cache 5 phút).
     */
    public static function shouldSync(): bool
    {
        return (time() - (int)self::cacheGet('smm_sync')) > 300;
    }

    public static function markSynced(): void
    {
        self::cacheSet('smm_sync', (string)time());
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

    // =================================================================
    //  API key của user (hệ key dùng chung SMM + Auto MXH)
    // =================================================================

    /**
     * Lưu apikey cho user.
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
     * Apikey đang dùng của user.
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

    public static function deleteUserKey(int $userId, int $keyId): bool
    {
        return Database::delete('ttmmo_api_keys', 'id = :id AND user_id = :u', ['id' => $keyId, 'u' => $userId]) > 0;
    }

    public static function setPrimaryKey(int $userId, int $keyId): bool
    {
        Database::query('UPDATE ttmmo_api_keys SET is_active = 0 WHERE user_id = ?', [$userId]);
        return Database::update('ttmmo_api_keys', [
            'is_active'  => 1,
            'updated_at' => now(),
        ], 'id = :id AND user_id = :u', ['id' => $keyId, 'u' => $userId]) > 0;
    }

    // =================================================================
    //  Quản trị: danh sách key + giảm giá đại lý
    // =================================================================

    /**
     * Danh sách toàn bộ API key kèm user và % giảm đại lý.
     */
    public static function allKeys(int $limit = 200): array
    {
        return Database::fetchAll(
            'SELECT k.id, k.user_id, k.apikey, k.label, k.is_active, k.agent_discount_percent,
                    k.created_at, u.username, u.balance
             FROM ttmmo_api_keys k
             JOIN users u ON u.id = k.user_id
             ORDER BY k.id DESC
             LIMIT ' . (int)$limit
        );
    }

    /**
     * Admin đặt % giảm cho 1 apikey (0 = không giảm).
     */
    public static function setKeyDiscount(int $keyId, float $percent): bool
    {
        $percent = max(0.0, min(100.0, $percent));

        return Database::update('ttmmo_api_keys', [
            'agent_discount_percent' => $percent,
            'updated_at' => now(),
        ], 'id = :id', ['id' => $keyId]) > 0;
    }
}
