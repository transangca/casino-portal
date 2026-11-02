<?php
/**
 * Dịch vụ VPS StoreVPS / Virtualizor — tích hợp XVPS API `/api/virt/*`.
 *
 * Base URL: https://api.xvps.vn  (sandbox: https://api-sandbox.xvps.vn)
 *
 * Xác thực — mọi request gửi 3 header bắt buộc:
 *   api-username, api-app, api-secret
 *   + auth-token (JWT lấy từ POST /api/virt/get-token, TTL ~8h)
 *
 * Endpoint map (StoreVPS / Virtualizor):
 *   POST /api/virt/get-token             → lấy JWT token
 *   GET  /api/virt/get-info               → thông tin tài khoản, số dư, thống kê
 *   GET  /api/virt/get-product            → catalog gói VPS + addon + locations
 *   GET  /api/virt/get-list-os            → danh sách hệ điều hành
 *   POST /api/virt/order/create-order     → tạo đơn (plan_id, cycle_id, os_id, hostname...)
 *   GET  /api/virt/vps/get-list-vps       → danh sách VPS (page, limit, status)
 *   GET  /api/virt/vps/get-info-vps       → chi tiết 1 VPS (id / vps_id)
 *   GET  /api/virt/vps/get-status-batch   → trạng thái nhiều VPS (ids, tối đa 50)
 *   POST /api/virt/vps/action-vps         → start / stop / restart / poweroff
 *   POST /api/virt/vps/rebuild-vps        → cài lại OS (os_id, new_password?)
 *   POST /api/virt/vps/change-password    → đổi root password (new_password, on_boot)
 *   POST /api/virt/vps/change-hostname    → đổi hostname (new_hostname)
 *   POST /api/virt/vps/renew-vps          → gia hạn (cycle_id) — trừ ví đối tác
 *   POST /api/virt/vps/addon-vps          → nâng cấp cpu/ram/disk/ip/ipv6/bandwidth
 *   POST /api/virt/vps/auto-renew         → bật/tắt tự động gia hạn (enabled)
 *   POST /api/virt/vps/cancel-vps         → hủy dịch vụ (không khôi phục được)
 *
 * Quy ước response: thành công `error: 0`; lỗi `error != 0` kèm `message`.
 *
 * Nguyên tắc nghiệp vụ:
 *  - Token JWT cache lại, tự làm mới khi hết hạn.
 *  - Giá bán = giá bảng giá đối tác + biên lợi nhuận, KHÔNG cộng VAT.
 *  - Tạo đơn → trừ ví chính users.balance, hoàn tiền tức thì nếu lỗi.
 *  - `idempotency-key` gửi kèm mỗi đơn để retry an toàn.
 */

namespace App\Services;

use App\Core\Crypto;
use App\Core\Database;

class VirtualizorService
{
    public const DEFAULT_BASE_URL = 'https://api.xvps.vn';
    public const SANDBOX_BASE_URL = 'https://api-sandbox.xvps.vn';

    /** TTL cache token (giây) — token đối tác dùng 8h, cache 7h cho an toàn */
    private const TOKEN_TTL = 25200;

    // ================= Trạng thái đơn nội bộ =================
    public const STATUS_PENDING    = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED  = 'completed';
    public const STATUS_FAILED     = 'failed';

    /** Chu kỳ thanh toán hỗ trợ (khớp với cycle_id của API) */
    public const CYCLES = [
        'monthly'        => '1 Tháng',
        'quarterly'      => '3 Tháng',
        'semi_annually'  => '6 Tháng',
        'annually'       => '12 Tháng',
    ];

    /** Action nguồn hỗ trợ */
    public const POWER_ACTIONS = [
        'start'     => 'Khởi động',
        'stop'      => 'Tắt máy',
        'restart'   => 'Khởi động lại',
        'poweroff'  => 'Tắt cứng',
    ];

    /** Loại addon hỗ trợ (khoá API) */
    public const ADDON_TYPES = [
        'cores'     => 'CPU Core',
        'ram'       => 'RAM',
        'disk'      => 'Disk',
        'ipv4'      => 'IPv4',
        'ipv6'      => 'IPv6',
        'bandwidth' => 'Bandwidth',
    ];

    /** Ánh xạ type_addon của API → khoá addon trong request addon-vps */
    public const ADDON_REQUEST_KEY = [
        'cores'     => 'cpu',
        'ram'       => 'ram',
        'disk'      => 'disk',
        'ipv4'      => 'ip',
        'ipv6'      => 'ipv6',
        'bandwidth' => 'bandwidth',
    ];

    /** Quy đổi trạng thái VPS Virtualizor → nội bộ */
    public const STATUS_MAP = [
        'running'      => self::STATUS_COMPLETED,
        'stopped'      => self::STATUS_COMPLETED,
        'creating'     => self::STATUS_PROCESSING,
        'installing'   => self::STATUS_PROCESSING,
        'rebuilding'   => self::STATUS_PROCESSING,
        'reinstalling' => self::STATUS_PROCESSING,
        'migrating'    => self::STATUS_PROCESSING,
        'expired'      => self::STATUS_FAILED,
        'suspended'    => self::STATUS_FAILED,
        'deleted'      => self::STATUS_FAILED,
        'cancelled'    => self::STATUS_FAILED,
        'creating_error' => self::STATUS_FAILED,
    ];

    /** Trạng thái chặn thao tác (không thể start/stop/rebuild...) */
    public const BLOCKED_STATUSES = [
        'creating', 'installing', 'rebuilding', 'reinstalling',
        'migrating', 'expired', 'suspended', 'deleted', 'cancelled', 'creating_error',
    ];

    // =================================================================
    //  Cấu hình
    // =================================================================

    public static function baseUrl(): string
    {
        $url = trim((string)setting('vps_vz_base_url', ''));
        if ($url === '') {
            $url = (string)config('vps_vz.base_url', self::DEFAULT_BASE_URL);
        }
        $url = rtrim(trim($url), '/');

        return $url !== '' ? $url : self::DEFAULT_BASE_URL;
    }

    /** Bật sandbox → dùng domain sandbox (tài liệu: api-sandbox.xvps.vn) */
    public static function sandbox(): bool
    {
        return setting('vps_vz_sandbox', '0') === '1';
    }

    public static function apiUsername(): string
    {
        $v = trim((string)setting('vps_vz_api_username', ''));
        if ($v === '') {
            $v = trim((string)config('vps_vz.api_username', ''));
        }
        return $v;
    }

    public static function apiApp(): string
    {
        $v = trim((string)setting('vps_vz_api_app', ''));
        if ($v === '') {
            $v = trim((string)config('vps_vz.api_app', ''));
        }
        return $v;
    }

    public static function apiSecret(): string
    {
        $raw = (string)setting('vps_vz_api_secret', '');
        if ($raw !== '') {
            $dec = Crypto::decrypt($raw);
            $key = $dec ?? $raw;
            if ($key !== '') {
                return trim($key);
            }
        }

        return trim((string)config('vps_vz.api_secret', ''));
    }

    public static function saveCredentials(string $username, string $app, string $secret): void
    {
        setting_set('vps_vz_api_username', trim($username), 'XVPS Virtualizor');
        setting_set('vps_vz_api_app', trim($app), 'XVPS Virtualizor');
        setting_set(
            'vps_vz_api_secret',
            trim($secret) === '' ? '' : Crypto::encrypt(trim($secret)),
            'XVPS Virtualizor',
            'password'
        );
    }

    public static function configured(): bool
    {
        return self::apiUsername() !== '' && self::apiApp() !== '' && self::apiSecret() !== '';
    }

    public static function enabled(): bool
    {
        $v = (string)setting('vps_vz_service_on', '');
        if ($v === '') {
            return (bool)config('vps_vz.service_on', true);
        }
        return $v === '1';
    }

    /** Biên lợi nhuận (%) */
    public static function markup(): float
    {
        return (float)setting('vps_vz_markup', '0');
    }

    /** VAT của dịch vụ luôn bằng 0 */
    public static function vatPercent(): float
    {
        return 0.0;
    }

    // =================================================================
    //  Giao tiếp API
    // =================================================================

    /**
     * Gọi API Virtualizor.
     *
     * @param string               $method   GET|POST
     * @param string               $endpoint ví dụ /api/virt/get-product
     * @param array                $payload  body JSON (POST)
     * @param array                $query    query string (GET)
     * @param array<string,string> $extraHeaders header bổ sung (vd idempotency-key)
     *
     * @return array ['ok'=>bool,'data'=>array,'message'=>string,'status'=>int]
     */
    public static function request(
        string $method,
        string $endpoint,
        array $payload = [],
        array $query = [],
        bool $withToken = true,
        array $extraHeaders = []
    ): array {
        if (!self::configured()) {
            return [
                'ok'      => false,
                'data'    => [],
                'message' => 'Chưa cấu hình thông tin API StoreVPS. Admin nhập tại /admin/vps-storevps.',
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
            'api-username: ' . self::apiUsername(),
            'api-app: ' . self::apiApp(),
            'api-secret: ' . self::apiSecret(),
            'Accept: application/json',
        ];

        if ($withToken) {
            $token = self::token();
            if ($token === '') {
                return [
                    'ok'      => false,
                    'data'    => [],
                    'message' => 'Không lấy được token xác thực từ API StoreVPS. Kiểm tra lại thông tin API.',
                    'status'  => 401,
                ];
            }
            $headers[] = 'auth-token: ' . $token;
        }

        foreach ($extraHeaders as $k => $v) {
            $headers[] = $k . ': ' . $v;
        }

        $opts = ['timeout' => 30, 'retries' => 1, 'allow_error' => true];

        if (strtoupper($method) === 'POST') {
            $body = HttpClient::postJson($url, $payload, $headers, $opts);
        } else {
            $body = HttpClient::get($url, $headers, $opts);
        }

        if ($body === null) {
            return [
                'ok'      => false,
                'data'    => [],
                'message' => 'Không kết nối được tới API StoreVPS. Vui lòng thử lại sau.',
                'status'  => 0,
            ];
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return [
                'ok'      => false,
                'data'    => [],
                'message' => 'API StoreVPS trả về dữ liệu không hợp lệ.',
                'status'  => 0,
            ];
        }

        // StoreVPS dùng `error` field: 0 = thành công, khác 0 = lỗi
        $error = (int)($decoded['error'] ?? 0);
        $ok    = $error === 0;
        $msg   = trim((string)($decoded['message'] ?? ''));

        return [
            'ok'      => $ok,
            'data'    => $decoded,
            'message' => $msg !== '' ? $msg : ($ok ? 'OK' : 'Không rõ nguyên nhân.'),
            'status'  => $ok ? 200 : ($error === 9999 ? 401 : 400),
        ];
    }

    // =================================================================
    //  Token
    // =================================================================

    /**
     * Lấy JWT token (cache trong settings, tự làm mới khi hết hạn).
     */
    public static function token(bool $force = false): string
    {
        $cached = (string)setting('vps_vz_auth_token', '');
        $ts     = (int)setting('vps_vz_token_time', '0');

        if (!$force && $cached !== '' && (time() - $ts) < self::TOKEN_TTL) {
            return $cached;
        }

        $res = self::request('POST', '/api/virt/get-token', [], [], false);
        if (!$res['ok']) {
            return '';
        }

        $token = (string)($res['data']['auth-token'] ?? '');
        if ($token === '') {
            return '';
        }

        setting_set('vps_vz_auth_token', $token, 'XVPS Virtualizor', 'password');
        setting_set('vps_vz_token_time', (string)time(), 'XVPS Virtualizor');

        return $token;
    }

    public static function clearToken(): void
    {
        setting_set('vps_vz_auth_token', '', 'XVPS Virtualizor', 'password');
        setting_set('vps_vz_token_time', '0', 'XVPS Virtualizor');
    }

    // =================================================================
    //  Tính tiền
    // =================================================================

    /**
     * Giá bán sau khi cộng biên lợi nhuận.
     *
     * Nhân với số nguyên rồi chia lại để tránh lỗi làm tròn số thực.
     */
    public static function sellPrice(float $price): float
    {
        $markup = self::markup();
        if ($markup <= 0) {
            return $price;
        }
        $bps = (int)round($markup * 100);

        return (float)ceil(($price * (10000 + $bps)) / 10000);
    }

    /** Chu kỳ của hệ thống → khoá pricing của API */
    public static function cycleKey(string $cycle): string
    {
        $map = [
            'monthly'       => 'monthly',
            'quarterly'     => 'quarterly',
            'semi-annually' => 'semi_annually',
            'semi_annually' => 'semi_annually',
            'annually'      => 'annually',
            'yearly'        => 'annually',
        ];

        return $map[$cycle] ?? 'monthly';
    }

    /**
     * Giá gốc của 1 gói theo chu kỳ.
     */
    public static function cyclePrice(array $product, string $cycle): float
    {
        $col = 'price_' . self::cycleKey($cycle);

        return (float)($product[$col] ?? 0);
    }

    /**
     * Tính tổng tiền đơn (không VAT).
     *
     * @param array $product  gói trong DB (vps_vz_products)
     * @param string $cycle   khoá chu kỳ (monthly / quarterly / semi_annually / annually)
     * @param int   $quantity số VPS
     * @param array $addons   ['cores'=>1,'ram'=>2,...] số đơn vị mỗi loại
     * @param float $surcharge phí cộng thêm theo location
     */
    public static function calcTotal(
        array $product,
        string $cycle,
        int $quantity = 1,
        array $addons = [],
        float $surcharge = 0.0
    ): array {
        $quantity = max(1, $quantity);

        $unit = self::sellPrice(self::cyclePrice($product, $cycle));

        $addonCost = 0.0;
        foreach (self::ADDON_TYPES as $type => $_label) {
            $qty = (int)($addons[$type] ?? 0);
            if ($qty <= 0) {
                continue;
            }
            $addonCost += self::addonUnitPrice($product, $type, $cycle) * $qty;
        }

        // Phí location cộng 1 lần / VPS
        $locCost = self::sellPrice($surcharge) * $quantity;

        $subtotal = ($unit + $addonCost + self::sellPrice($surcharge)) * $quantity;
        $vat      = 0.0;

        return [
            'item'      => $unit,
            'addon'     => $addonCost,
            'surcharge' => self::sellPrice($surcharge),
            'subtotal'  => $subtotal,
            'vat'       => $vat,
            'total'     => $subtotal + $vat,
            'cycle'     => $cycle,
            'quantity'  => $quantity,
            'locCost'   => $locCost,
        ];
    }

    /**
     * Đơn giá bán của 1 đơn vị addon theo chu kỳ.
     *
     * Nguồn giá:
     *  1. pricing_json nội tuyến trong bản thân product
     *  2. Bảng addon trong DB (vps_vz_products.is_addon = 1, type_addon = ...)
     *  3. Chính product hiện tại (khi product là addon)
     */
    public static function addonUnitPrice(array $product, string $type, string $cycle = 'monthly'): float
    {
        $cycleKey = self::cycleKey($cycle);

        // 1. pricing_json nội tuyến theo đúng loại addon: pricing[type][cycle][amount]
        $pricing = json_decode((string)($product['pricing_json'] ?? '{}'), true);
        if (is_array($pricing)) {
            if (isset($pricing[$type][$cycleKey]['amount'])) {
                return self::sellPrice((float)$pricing[$type][$cycleKey]['amount']);
            }
        }

        // 2. Bảng addon trong DB (vps_vz_products.is_addon = 1, type_addon = ...)
        $addon = self::findAddon($type);
        if ($addon) {
            return self::sellPrice(self::cyclePrice($addon, $cycle));
        }

        // 3. pricing_json nội tuyến dạng pricing[cycle][amount]
        if (is_array($pricing) && isset($pricing[$cycleKey]['amount'])) {
            return self::sellPrice((float)$pricing[$cycleKey]['amount']);
        }

        // 4. Fallback
        return self::sellPrice(self::cyclePrice($product, $cycle));
    }

    /** Tìm gói addon theo type_addon trong DB. */
    public static function findAddon(string $type): ?array
    {
        if ($type === '') {
            return null;
        }

        return Database::fetch(
            'SELECT * FROM vps_vz_products
             WHERE is_active = 1 AND is_addon = 1 AND type_addon = ?
             ORDER BY sort_order, id LIMIT 1',
            [$type]
        );
    }

    // =================================================================
    //  Endpoint wrappers — Catalog
    // =================================================================

    /**
     * GET /api/virt/get-info — thông tin tài khoản, số dư, thống kê VPS.
     */
    public static function info(): array
    {
        $res = self::request('GET', '/api/virt/get-info');
        if (!$res['ok']) {
            return ['ok' => false, 'balance' => 0.0, 'currency' => 'VND', 'message' => $res['message'], 'user' => [], 'stats' => []];
        }

        $d = (array)($res['data']['data'] ?? []);

        return [
            'ok'       => true,
            'balance'  => (float)($d['credit'] ?? 0),
            'currency' => 'VND',
            'user'     => [
                'name' => (string)($d['name'] ?? ''),
            ],
            'scopes'   => (array)($d['scopes'] ?? []),
            'stats'    => (array)($d['vps'] ?? []),
            'message'  => '',
        ];
    }

    /**
     * GET /api/virt/get-product — catalog gói VPS + addon (+ locations).
     *
     * @return array ['vps'=>[...], 'addons'=>[...]]
     */
    public static function products(): array
    {
        $res = self::request('GET', '/api/virt/get-product');
        if (!$res['ok']) {
            return ['vps' => [], 'addons' => []];
        }

        $out = ['vps' => [], 'addons' => []];

        foreach ((array)($res['data']['products']['vps'] ?? []) as $g) {
            $groupName = (string)($g['group_product_name'] ?? '');
            foreach ((array)($g['product'] ?? []) as $p) {
                $out['vps'][] = self::normalizeProduct($p, $groupName, false);
            }
        }

        foreach ((array)($res['data']['products']['addon_vps'] ?? []) as $g) {
            $groupName = (string)($g['group_product_name'] ?? '');
            foreach ((array)($g['product'] ?? []) as $p) {
                $out['addons'][] = self::normalizeProduct($p, $groupName, true);
            }
        }

        return $out;
    }

    /**
     * GET /api/virt/get-list-os — danh sách hệ điều hành.
     */
    public static function operatingSystems(): array
    {
        $res = self::request('GET', '/api/virt/get-list-os');
        if (!$res['ok']) {
            return [];
        }

        $out = [];
        foreach ((array)($res['data']['os-vps'] ?? []) as $os) {
            $out[] = [
                'os_id'   => (string)($os['os_id'] ?? ''),
                'os_name' => (string)($os['os_name'] ?? ''),
                'distro'  => (string)($os['distro'] ?? ''),
            ];
        }

        return $out;
    }

    // =================================================================
    //  Endpoint wrappers — Đơn hàng
    // =================================================================

    /**
     * POST /api/virt/order/create-order — tạo đơn VPS mới.
     *
     * @param string[] $addonProductIds danh sách product_id của addon (dạng "…:cores")
     */
    public static function createOrder(
        string $planId,
        string $cycleId,
        string $osId,
        string $hostname,
        string $locationId = '',
        int $quantity = 1,
        array $addonProductIds = [],
        string $idempotencyKey = ''
    ): array {
        $payload = [
            'plan_id'    => $planId,
            'cycle_id'   => $cycleId,
            'os_id'      => $osId,
            'hostname'   => $hostname,
            'quantity'   => max(1, $quantity),
        ];
        if ($locationId !== '') {
            $payload['location_id'] = $locationId;
        }
        if ($addonProductIds) {
            $payload['addons'] = array_values($addonProductIds);
        }

        $headers = [];
        if ($idempotencyKey !== '') {
            $headers['idempotency-key'] = $idempotencyKey;
        }

        $res = self::request('POST', '/api/virt/order/create-order', $payload, [], true, $headers);
        if (!$res['ok']) {
            return ['ok' => false, 'message' => $res['message'], 'data' => [], 'raw' => $res['data']];
        }

        $d = (array)($res['data']['data'] ?? []);

        return [
            'ok'       => true,
            'message'  => (string)($res['data']['message'] ?? 'Đơn VPS đang được xử lý.'),
            'status'   => (string)($d['status'] ?? 'creating'),
            'total'    => (float)($d['total'] ?? 0),
            'balance'  => (float)($d['balance'] ?? 0),
            'orders'   => (array)($d['orders'] ?? []),
            'data'     => $d,
            'raw'      => $res['data'],
        ];
    }

    // =================================================================
    //  Endpoint wrappers — VPS
    // =================================================================

    /**
     * GET /api/virt/vps/get-list-vps — danh sách VPS.
     */
    public static function listVps(int $page = 1, int $limit = 20, string $status = ''): array
    {
        $res = self::request('GET', '/api/virt/vps/get-list-vps', [], [
            'page'   => $page,
            'limit'  => $limit,
            'status' => $status,
        ]);

        if (!$res['ok']) {
            return ['ok' => false, 'message' => $res['message'], 'data' => [], 'total' => 0, 'page' => $page];
        }

        $pagination = (array)($res['data']['pagination'] ?? []);

        return [
            'ok'      => true,
            'message' => '',
            'data'    => (array)($res['data']['data'] ?? []),
            'total'   => (int)($pagination['total'] ?? 0),
            'page'    => (int)($pagination['page'] ?? $page),
            'limit'   => (int)($pagination['limit'] ?? $limit),
        ];
    }

    /**
     * GET /api/virt/vps/get-info-vps — chi tiết 1 VPS.
     *
     * @param string $vpsId MongoDB instance id
     */
    public static function infoVps(string $vpsId): array
    {
        if ($vpsId === '') {
            return ['ok' => false, 'message' => 'Thiếu vps_id', 'data' => []];
        }

        $res = self::request('GET', '/api/virt/vps/get-info-vps', [], [
            'id'     => $vpsId,
            'vps_id' => $vpsId,
        ]);

        if (!$res['ok']) {
            return ['ok' => false, 'message' => $res['message'], 'data' => []];
        }

        $d = $res['data']['data'] ?? [];
        if (isset($d[0]) && is_array($d[0])) {
            $d = $d[0];
        }

        return [
            'ok'      => true,
            'message' => '',
            'data'    => is_array($d) ? $d : [],
        ];
    }

    /**
     * GET /api/virt/vps/get-status-batch — trạng thái nhiều VPS (tối đa 50).
     *
     * @param string[] $vpsIds
     */
    public static function statusBatch(array $vpsIds): array
    {
        $ids = array_values(array_filter(array_map('trim', array_map('strval', $vpsIds))));
        if (!$ids) {
            return ['ok' => false, 'message' => 'Thiếu danh sách ids', 'data' => []];
        }
        if (count($ids) > 50) {
            $ids = array_slice($ids, 0, 50);
        }

        $res = self::request('GET', '/api/virt/vps/get-status-batch', [], [
            'ids' => implode(',', $ids),
        ]);

        if (!$res['ok']) {
            return ['ok' => false, 'message' => $res['message'], 'data' => []];
        }

        return [
            'ok'      => true,
            'message' => '',
            'data'    => (array)($res['data']['data'] ?? []),
        ];
    }

    /**
     * POST /api/virt/vps/action-vps — start / stop / restart / poweroff.
     */
    public static function actionVps(string $action, string $vpsId): array
    {
        if (!isset(self::POWER_ACTIONS[$action])) {
            return ['ok' => false, 'message' => 'Hành động không hợp lệ: ' . $action, 'data' => []];
        }
        if ($vpsId === '') {
            return ['ok' => false, 'message' => 'Thiếu vps_id', 'data' => []];
        }

        $res = self::request('POST', '/api/virt/vps/action-vps', [
            'vps_id' => $vpsId,
            'action' => $action,
        ]);

        if (!$res['ok']) {
            return ['ok' => false, 'message' => $res['message'], 'data' => $res['data']];
        }

        return [
            'ok'      => true,
            'message' => (string)($res['data']['message'] ?? 'Thực hiện thành công.'),
            'data'    => (array)($res['data']['data'] ?? []),
            'raw'     => $res['data'],
        ];
    }

    /**
     * POST /api/virt/vps/rebuild-vps — cài lại OS.
     *
     * @param string $newPassword mật khẩu mới (bỏ trống → dùng mật khẩu cũ nếu đọc được)
     */
    public static function rebuildVps(string $vpsId, string $osId, string $newPassword = ''): array
    {
        if ($vpsId === '') {
            return ['ok' => false, 'message' => 'Thiếu vps_id', 'data' => []];
        }

        $payload = [
            'vps_id' => $vpsId,
            'os_id'  => $osId,
        ];
        if ($newPassword !== '') {
            $payload['new_password'] = $newPassword;
        }

        $res = self::request('POST', '/api/virt/vps/rebuild-vps', $payload);
        if (!$res['ok']) {
            return ['ok' => false, 'message' => $res['message'], 'data' => $res['data']];
        }

        return [
            'ok'      => true,
            'message' => (string)($res['data']['message'] ?? 'Đã gửi lệnh rebuild VPS.'),
            'data'    => (array)($res['data']['data'] ?? []),
            'raw'     => $res['data'],
        ];
    }

    /**
     * POST /api/virt/vps/change-password — đổi root password.
     */
    public static function changePassword(string $vpsId, string $newPassword, bool $onBoot = false): array
    {
        if ($vpsId === '') {
            return ['ok' => false, 'message' => 'Thiếu vps_id', 'data' => []];
        }

        $res = self::request('POST', '/api/virt/vps/change-password', [
            'vps_id'       => $vpsId,
            'new_password' => $newPassword,
            'on_boot'      => $onBoot,
        ]);

        if (!$res['ok']) {
            return ['ok' => false, 'message' => $res['message'], 'data' => $res['data']];
        }

        return [
            'ok'      => true,
            'message' => (string)($res['data']['message'] ?? 'Đổi mật khẩu thành công.'),
            'data'    => (array)($res['data']['data'] ?? []),
            'raw'     => $res['data'],
        ];
    }

    /**
     * POST /api/virt/vps/change-hostname — đổi hostname.
     */
    public static function changeHostname(string $vpsId, string $newHostname): array
    {
        if ($vpsId === '') {
            return ['ok' => false, 'message' => 'Thiếu vps_id', 'data' => []];
        }

        $res = self::request('POST', '/api/virt/vps/change-hostname', [
            'vps_id'      => $vpsId,
            'new_hostname' => $newHostname,
        ]);

        if (!$res['ok']) {
            return ['ok' => false, 'message' => $res['message'], 'data' => $res['data']];
        }

        return [
            'ok'      => true,
            'message' => (string)($res['data']['message'] ?? 'Đổi hostname thành công.'),
            'data'    => (array)($res['data']['data'] ?? []),
            'raw'     => $res['data'],
        ];
    }

    /**
     * POST /api/virt/vps/renew-vps — gia hạn (API tự trừ ví đối tác).
     */
    public static function renewVps(string $vpsId, string $cycleId): array
    {
        if ($vpsId === '') {
            return ['ok' => false, 'message' => 'Thiếu vps_id', 'data' => []];
        }

        $res = self::request('POST', '/api/virt/vps/renew-vps', [
            'vps_id'   => $vpsId,
            'cycle_id' => $cycleId,
        ]);

        if (!$res['ok']) {
            return ['ok' => false, 'message' => $res['message'], 'data' => $res['data']];
        }

        $d = (array)($res['data']['data'] ?? []);

        return [
            'ok'       => true,
            'message'  => (string)($res['data']['message'] ?? 'Gia hạn VPS thành công.'),
            'amount'   => (float)($d['amount'] ?? 0),
            'newExpireAt' => (string)($d['newExpireAt'] ?? ''),
            'data'     => $d,
            'raw'      => $res['data'],
        ];
    }

    /**
     * POST /api/virt/vps/addon-vps — nâng cấp cpu/ram/disk/ip/ipv6/bandwidth.
     *
     * @param array $addons ['cores'=>1,'ram'=>2,...]
     */
    public static function addonVps(string $vpsId, array $addons, bool $rebootAfter = false, string $note = ''): array
    {
        if ($vpsId === '') {
            return ['ok' => false, 'message' => 'Thiếu vps_id', 'data' => []];
        }

        $payload = [
            'vps_id'                   => $vpsId,
            'reboot_after_fulfillment' => $rebootAfter,
        ];

        $total = 0;
        foreach (self::ADDON_REQUEST_KEY as $type => $key) {
            $qty = (int)($addons[$type] ?? 0);
            if ($qty < 0) {
                $qty = 0;
            }
            $payload[$key] = $qty;
            $total += $qty;
        }

        if ($total <= 0) {
            return [
                'ok'      => false,
                'message' => 'Cần ít nhất một addon > 0 (cpu, ram, disk, ip, ipv6 hoặc bandwidth).',
                'data'    => [],
            ];
        }

        if ($note !== '') {
            $payload['note'] = mb_substr($note, 0, 500);
        }

        $res = self::request('POST', '/api/virt/vps/addon-vps', $payload);
        if (!$res['ok']) {
            return ['ok' => false, 'message' => $res['message'], 'data' => $res['data']];
        }

        $d = (array)($res['data']['data'] ?? []);

        return [
            'ok'        => true,
            'message'   => (string)($res['data']['message'] ?? 'Tạo hóa đơn nâng cấp addon thành công.'),
            'invoiceId' => (string)($d['invoice_id'] ?? ''),
            'data'      => $d,
            'raw'       => $res['data'],
        ];
    }

    /**
     * POST /api/virt/vps/auto-renew — bật/tắt tự động gia hạn.
     */
    public static function autoRenew(string $vpsId, bool $enabled): array
    {
        if ($vpsId === '') {
            return ['ok' => false, 'message' => 'Thiếu vps_id', 'data' => []];
        }

        $res = self::request('POST', '/api/virt/vps/auto-renew', [
            'vps_id'  => $vpsId,
            'enabled' => $enabled,
        ]);

        if (!$res['ok']) {
            return ['ok' => false, 'message' => $res['message'], 'data' => $res['data']];
        }

        $d = (array)($res['data']['data'] ?? []);

        return [
            'ok'          => true,
            'message'     => (string)($res['data']['message'] ?? 'Đã cập nhật tự động gia hạn.'),
            'autoRenew'   => (bool)($d['auto_renew'] ?? $enabled),
            'currentCycle'=> (string)($d['current_cycle'] ?? ''),
            'nextBillAt'  => (string)($d['next_bill_at'] ?? ''),
            'data'        => $d,
            'raw'         => $res['data'],
        ];
    }

    /**
     * POST /api/virt/vps/cancel-vps — hủy dịch vụ (không khôi phục được).
     */
    public static function cancelVps(string $vpsId): array
    {
        if ($vpsId === '') {
            return ['ok' => false, 'message' => 'Thiếu vps_id', 'data' => []];
        }

        $res = self::request('POST', '/api/virt/vps/cancel-vps', ['vps_id' => $vpsId]);
        if (!$res['ok']) {
            return ['ok' => false, 'message' => $res['message'], 'data' => $res['data']];
        }

        $d = (array)($res['data']['data'] ?? []);

        return [
            'ok'            => true,
            'message'       => (string)($res['data']['message'] ?? 'Đã hủy dịch vụ VPS thành công.'),
            'status'        => (string)($d['status'] ?? 'cancelled'),
            'cancelledAt'   => (string)($d['cancelled_at'] ?? ''),
            'suspendWarning'=> (string)($d['suspend_warning'] ?? ''),
            'data'          => $d,
            'raw'           => $res['data'],
        ];
    }

    // =================================================================
    //  Chuẩn hoá
    // =================================================================

    /**
     * Chuẩn hoá 1 product (gói VPS hoặc addon) từ API.
     */
    public static function normalizeProduct(array $p, string $groupName = '', bool $isAddon = false): array
    {
        $pricing = (array)($p['pricing'] ?? []);
        $locations = (array)($p['locations'] ?? []);

        $price = static function (string $key) use ($pricing): float {
            return (float)($pricing[$key]['amount'] ?? 0);
        };

        return [
            'product_id'     => (string)($p['product_id'] ?? ''),
            'group_name'     => $groupName,
            'name'           => (string)($p['name'] ?? $p['display_name'] ?? ''),
            'display_name'   => (string)($p['display_name'] ?? $p['name'] ?? ''),
            'description'    => (string)($p['description'] ?? ''),
            'type_vps'       => (string)($p['type_vps'] ?? ''),
            'cpu'            => (int)($p['cpu'] ?? 0),
            'ram'            => (int)($p['ram'] ?? 0),
            'disk'           => (int)($p['disk'] ?? 0),
            'bandwidth'      => (int)($p['bandwidth'] ?? 0),
            'network_speed'  => (int)($p['network_speed'] ?? 0),
            'upload_speed'   => (int)($p['upload_speed'] ?? -1),
            'ipv4'           => (int)($p['ipv4'] ?? 0),
            'ipv6'           => (int)($p['ipv6'] ?? 0),
            'ipv6_subnet'    => (int)($p['ipv6_subnet'] ?? 0),
            'type_addon'     => (string)($p['type_addon'] ?? ''),
            'unit_value'     => (int)($p['unit_value'] ?? 1),
            'is_free'        => (int)($p['is_free'] ?? 0),
            'is_addon'       => $isAddon,
            'price_monthly'  => $price('monthly'),
            'price_quarterly'=> $price('quarterly'),
            'price_semi_annually' => $price('semi_annually'),
            'price_annually' => $price('annually'),
            'pricing_json'   => json_encode($pricing, JSON_UNESCAPED_UNICODE),
            'locations_json' => json_encode($locations, JSON_UNESCAPED_UNICODE),
        ];
    }

    /** Quy đổi trạng thái VPS Virtualizor → nội bộ. */
    public static function mapStatus(string $raw): string
    {
        $key = strtolower(trim($raw));
        return self::STATUS_MAP[$key] ?? ($key !== '' ? $key : self::STATUS_PENDING);
    }

    /** VPS có thể thực hiện thao tác nguồn hay không. */
    public static function isOperable(string $raw): bool
    {
        return !in_array(strtolower(trim($raw)), self::BLOCKED_STATUSES, true);
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

    /** Nhãn tiếng Việt của trạng thái VPS gốc (Virtualizor). */
    public static function vpsStatusLabel(string $raw): string
    {
        return [
            'running'        => 'Đang chạy',
            'stopped'        => 'Đã tắt',
            'creating'       => 'Đang khởi tạo',
            'installing'     => 'Đang cài đặt',
            'rebuilding'     => 'Đang cài lại',
            'reinstalling'   => 'Đang cài lại',
            'migrating'      => 'Đang di chuyển',
            'expired'        => 'Đã hết hạn',
            'suspended'      => 'Đang tạm ngưng',
            'deleted'        => 'Đã xoá',
            'cancelled'      => 'Đã hủy',
            'creating_error' => 'Lỗi khởi tạo',
            'unknown'        => 'Không rõ',
        ][strtolower(trim($raw))] ?? ucfirst($raw ?: '—');
    }

    public static function vpsStatusBadge(string $raw): string
    {
        return [
            'running'        => 'success',
            'stopped'        => 'secondary',
            'creating'       => 'info',
            'installing'     => 'info',
            'rebuilding'     => 'info',
            'reinstalling'   => 'info',
            'migrating'      => 'info',
            'expired'        => 'danger',
            'suspended'      => 'danger',
            'deleted'        => 'secondary',
            'cancelled'      => 'danger',
            'creating_error' => 'danger',
        ][strtolower(trim($raw))] ?? 'secondary';
    }

    // =================================================================
    //  Đồng bộ danh mục
    // =================================================================

    /**
     * Đồng bộ gói VPS + addon + locations + OS từ API vào DB.
     */
    public static function sync(): array
    {
        if (!self::configured()) {
            return ['ok' => false, 'message' => 'Chưa cấu hình thông tin API StoreVPS.', 'products' => 0, 'os' => 0, 'locations' => 0];
        }

        $catalog = self::products();
        if (!$catalog['vps'] && !$catalog['addons']) {
            return ['ok' => false, 'message' => 'Không tải được danh sách gói VPS từ API StoreVPS.', 'products' => 0, 'os' => 0, 'locations' => 0];
        }

        $nProd = 0;
        $nLoc  = 0;
        $seen  = [];

        foreach (array_merge($catalog['vps'], $catalog['addons']) as $p) {
            $pid = (string)$p['product_id'];
            if ($pid === '') {
                continue;
            }

            $locations = json_decode((string)$p['locations_json'], true);
            $locations = is_array($locations) ? $locations : [];

            $row = [
                'product_id'     => $pid,
                'group_name'     => mb_substr((string)$p['group_name'], 0, 300),
                'name'           => mb_substr((string)$p['name'], 0, 300),
                'display_name'   => mb_substr((string)$p['display_name'], 0, 300),
                'description'    => mb_substr((string)$p['description'], 0, 500),
                'type_vps'       => mb_substr((string)$p['type_vps'], 0, 60),
                'cpu'            => (int)$p['cpu'],
                'ram'            => (int)$p['ram'],
                'disk'           => (int)$p['disk'],
                'bandwidth'      => (int)$p['bandwidth'],
                'network_speed'  => (int)$p['network_speed'],
                'upload_speed'   => (int)$p['upload_speed'],
                'ipv4'           => (int)$p['ipv4'],
                'ipv6'           => (int)$p['ipv6'],
                'ipv6_subnet'    => (int)$p['ipv6_subnet'],
                'type_addon'     => mb_substr((string)$p['type_addon'], 0, 60),
                'unit_value'     => (int)$p['unit_value'],
                'is_free'        => (int)$p['is_free'],
                'is_addon'       => (int)$p['is_addon'],
                'price_monthly'  => (float)$p['price_monthly'],
                'price_quarterly'=> (float)$p['price_quarterly'],
                'price_semi_annually' => (float)$p['price_semi_annually'],
                'price_annually' => (float)$p['price_annually'],
                'pricing_json'   => (string)$p['pricing_json'],
                'locations_json' => (string)$p['locations_json'],
                'is_active'      => 1,
                'sort_order'     => $nProd,
                'updated_at'     => now(),
            ];

            $exists = Database::fetchColumn('SELECT id FROM vps_vz_products WHERE product_id = ?', [$pid]);
            if ($exists) {
                Database::update('vps_vz_products', $row, 'id = :id', ['id' => (int)$exists]);
            } else {
                Database::insert('vps_vz_products', $row);
            }

            // Lưu locations riêng để chọn datacenter khi đặt đơn
            foreach ($locations as $loc) {
                $lid = (string)($loc['location_id'] ?? '');
                if ($lid === '') {
                    continue;
                }
                self::upsertLocation($lid, $loc, $pid);
                $nLoc++;
            }

            $seen[] = $pid;
            $nProd++;
        }

        // Sản phẩm không còn trên API → tắt
        if ($seen) {
            $inList = "'" . implode("','", array_map(static fn($s) => str_replace("'", '', (string)$s), $seen)) . "'";
            Database::query(
                "UPDATE vps_vz_products SET is_active = 0, updated_at = NOW() WHERE is_active = 1 AND product_id NOT IN ($inList)"
            );
        }

        // Đồng bộ OS
        $nOs = 0;
        foreach (self::operatingSystems() as $os) {
            $osId = (string)$os['os_id'];
            if ($osId === '') {
                continue;
            }

            $row = [
                'os_id'     => $osId,
                'os_name'   => mb_substr((string)$os['os_name'], 0, 190),
                'distro'    => mb_substr((string)$os['distro'], 0, 120),
                'is_active' => 1,
                'updated_at' => now(),
            ];

            $exists = Database::fetchColumn('SELECT id FROM vps_vz_operating_systems WHERE os_id = ?', [$osId]);
            if ($exists) {
                Database::update('vps_vz_operating_systems', $row, 'id = :id', ['id' => (int)$exists]);
            } else {
                Database::insert('vps_vz_operating_systems', $row);
            }
            $nOs++;
        }

        return [
            'ok'        => true,
            'message'   => "Đã đồng bộ $nProd gói VPS, $nLoc datacenter, $nOs hệ điều hành.",
            'products'  => $nProd,
            'os'        => $nOs,
            'locations' => $nLoc,
        ];
    }

    /** Lưu 1 location. */
    private static function upsertLocation(string $locationId, array $loc, string $productId): void
    {
        $row = [
            'location_id' => $locationId,
            'code'        => mb_substr((string)($loc['code'] ?? ''), 0, 60),
            'name'        => mb_substr((string)($loc['name'] ?? ''), 0, 190),
            'surcharge'   => (float)($loc['surcharge'] ?? 0),
            'product_id'  => $productId,
            'is_active'   => 1,
            'updated_at'  => now(),
        ];

        $exists = Database::fetchColumn('SELECT id FROM vps_vz_locations WHERE location_id = ?', [$locationId]);
        if ($exists) {
            Database::update('vps_vz_locations', $row, 'id = :id', ['id' => (int)$exists]);
        } else {
            Database::insert('vps_vz_locations', $row);
        }
    }

    // =================================================================
    //  Truy vấn DB — Catalog
    // =================================================================

    public static function localProducts(bool $includeAddons = false): array
    {
        $sql = 'SELECT * FROM vps_vz_products WHERE is_active = 1';
        if (!$includeAddons) {
            $sql .= ' AND is_addon = 0';
        }
        $sql .= ' ORDER BY group_name, sort_order, name';

        return Database::fetchAll($sql);
    }

    public static function localAddons(): array
    {
        return Database::fetchAll(
            'SELECT * FROM vps_vz_products WHERE is_active = 1 AND is_addon = 1 ORDER BY sort_order, name'
        );
    }

    public static function findProduct(string $productId): ?array
    {
        return Database::fetch('SELECT * FROM vps_vz_products WHERE product_id = ?', [$productId]);
    }

    public static function localOperatingSystems(): array
    {
        return Database::fetchAll(
            'SELECT * FROM vps_vz_operating_systems WHERE is_active = 1 ORDER BY os_name'
        );
    }

    public static function findOperatingSystem(string $osId): ?array
    {
        return Database::fetch('SELECT * FROM vps_vz_operating_systems WHERE os_id = ?', [$osId]);
    }

    public static function localLocations(?string $productId = null): array
    {
        if ($productId !== null && $productId !== '') {
            return Database::fetchAll(
                'SELECT * FROM vps_vz_locations WHERE is_active = 1 AND product_id = ? ORDER BY name',
                [$productId]
            );
        }

        return Database::fetchAll('SELECT * FROM vps_vz_locations WHERE is_active = 1 ORDER BY name');
    }

    public static function findLocation(string $locationId): ?array
    {
        return Database::fetch('SELECT * FROM vps_vz_locations WHERE location_id = ?', [$locationId]);
    }

    public static function productGroups(): array
    {
        $groups = [];
        foreach (self::localProducts() as $p) {
            $g = (string)($p['group_name'] ?: 'StoreVPS');
            $groups[$g][] = $p;
        }
        return $groups;
    }

    // =================================================================
    //  Đơn hàng
    // =================================================================

    public static function createOrderRow(int $userId, array $data): int
    {
        $row = array_merge([
            'user_id'    => $userId,
            'status'     => self::STATUS_PENDING,
            'quantity'   => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], $data);

        // Idempotency: cùng key → trả về đơn cũ
        if (!empty($row['idempotency_key'])) {
            $existing = Database::fetch(
                'SELECT id FROM vps_vz_orders WHERE idempotency_key = ? AND user_id = ?',
                [(string)$row['idempotency_key'], $userId]
            );
            if ($existing) {
                return (int)$existing['id'];
            }
        }

        return Database::insert('vps_vz_orders', $row);
    }

    public static function findOrder(int $userId, int $orderId): ?array
    {
        return Database::fetch(
            'SELECT * FROM vps_vz_orders WHERE id = ? AND user_id = ?',
            [$orderId, $userId]
        );
    }

    public static function allOrders(int $limit = 100): array
    {
        return Database::fetchAll(
            'SELECT o.*, u.username FROM vps_vz_orders o
             JOIN users u ON u.id = o.user_id
             ORDER BY o.id DESC LIMIT ' . (int)$limit
        );
    }

    public static function userOrders(int $userId, int $limit = 50): array
    {
        return Database::fetchAll(
            'SELECT * FROM vps_vz_orders WHERE user_id = ? ORDER BY id DESC LIMIT ' . (int)$limit,
            [$userId]
        );
    }

    public static function stats(): array
    {
        return [
            'orders'   => (int)Database::fetchColumn('SELECT COUNT(*) FROM vps_vz_orders'),
            'active'   => (int)Database::fetchColumn(
                'SELECT COUNT(*) FROM vps_vz_instances WHERE vps_status IN ("running","stopped")'
            ),
            'pending'  => (int)Database::fetchColumn(
                'SELECT COUNT(*) FROM vps_vz_orders WHERE status IN ("pending","processing")'
            ),
            'products' => (int)Database::fetchColumn(
                'SELECT COUNT(*) FROM vps_vz_products WHERE is_active = 1 AND is_addon = 0'
            ),
            'revenue'  => (float)Database::fetchColumn(
                "SELECT COALESCE(SUM(amount),0) FROM vps_vz_orders WHERE status <> 'failed'"
            ),
            'autoRenew'=> (int)Database::fetchColumn(
                'SELECT COUNT(*) FROM vps_vz_instances WHERE auto_renew = 1'
            ),
        ];
    }

    // =================================================================
    //  VPS instances
    // =================================================================

    /**
     * Đồng bộ danh sách VPS của 1 user từ API vào DB.
     */
    public static function syncVps(int $userId): array
    {
        $res = self::listVps(1, 100);
        if (!$res['ok']) {
            return ['ok' => false, 'message' => $res['message'], 'count' => 0];
        }

        $n = 0;
        foreach ($res['data'] as $v) {
            self::upsertInstance($userId, $v);
            $n++;
        }

        return ['ok' => true, 'message' => "Đã đồng bộ $n VPS.", 'count' => $n];
    }

    /**
     * Lưu/cập nhật 1 VPS instance.
     *
     * Lưu ý: `get-list-vps` có thể không trả username/password,
     * nên các trường rỗng sẽ được giữ nguyên giá trị cũ.
     */
    public static function upsertInstance(int $userId, array $v): void
    {
        $vpsId = (string)($v['vps-id'] ?? $v['vps_id'] ?? '');
        if ($vpsId === '') {
            return;
        }

        $existing = Database::fetch('SELECT * FROM vps_vz_instances WHERE vps_id = ?', [$vpsId]);
        $old = $existing ?: [];

        $pick = static function (array $src, array $keys, string $fallback = ''): string {
            foreach ($keys as $k) {
                if (isset($src[$k]) && trim((string)$src[$k]) !== '') {
                    return (string)$src[$k];
                }
            }
            return $fallback;
        };

        $ipv4List = (array)($v['ipv4List'] ?? []);
        $ipv6List = (array)($v['ipv6List'] ?? []);

        $row = [
            'user_id'       => $userId,
            'vps_id'        => $vpsId,
            'hostname'      => $pick($v, ['hostname'], $old['hostname'] ?? ''),
            'order_code'    => $pick($v, ['order-code', 'order_code'], $old['order_code'] ?? ''),
            'product_id'    => $pick($v, ['product-id', 'product_id'], $old['product_id'] ?? ''),
            'product_name'  => $pick($v, ['description', 'product_name'], $old['product_name'] ?? ''),
            'ip'            => $pick($v, ['ip'], $old['ip'] ?? ''),
            'ipv6'          => $pick($v, ['ipv6'], $old['ipv6'] ?? ''),
            'username'      => $pick($v, ['username'], $old['username'] ?? ''),
            'password'      => $pick($v, ['password'], $old['password'] ?? ''),
            'os_name'       => $pick($v, ['os-name', 'os_name', 'os'], $old['os_name'] ?? ''),
            'cpu'           => (int)($v['cpu'] ?? $old['cpu'] ?? 0),
            'ram'           => (int)($v['ram'] ?? $old['ram'] ?? 0),
            'disk'          => (int)($v['disk'] ?? $old['disk'] ?? 0),
            'text_config'   => $pick($v, ['text-config', 'text_config'], $old['text_config'] ?? ''),
            'type_vps'      => $pick($v, ['type-vps', 'type_vps'], $old['type_vps'] ?? ''),
            'billing_cycle' => $pick($v, ['billing-cycle', 'billing_cycle'], $old['billing_cycle'] ?? ''),
            'day_left'      => $pick($v, ['day-left', 'day_left'], (string)($old['day_left'] ?? '')),
            'vps_status'    => (string)($v['vps-status'] ?? $v['vps_status'] ?? $old['vps_status'] ?? 'unknown'),
            'amount'        => (float)($v['amount'] ?? $old['amount'] ?? 0),
            'auto_renew'    => (int)($v['auto-renew'] ?? $v['auto_renew'] ?? $old['auto_renew'] ?? 0),
            'description'   => $pick($v, ['description'], $old['description'] ?? ''),
            'date_create'   => $pick($v, ['date_create'], $old['date_create'] ?? ''),
            'next_due_date' => $pick($v, ['next_due_date'], $old['next_due_date'] ?? ''),
            'last_sync_at'  => now(),
            'updated_at'    => now(),
        ];

        if ($existing) {
            Database::update('vps_vz_instances', $row, 'id = :id', ['id' => (int)$existing['id']]);
        } else {
            Database::insert('vps_vz_instances', $row);
        }
    }

    public static function userInstances(int $userId): array
    {
        return Database::fetchAll(
            'SELECT * FROM vps_vz_instances WHERE user_id = ? ORDER BY vps_status = "running" DESC, id DESC',
            [$userId]
        );
    }

    public static function findInstance(int $userId, string $vpsId): ?array
    {
        return Database::fetch(
            'SELECT * FROM vps_vz_instances WHERE vps_id = ? AND user_id = ?',
            [$vpsId, $userId]
        );
    }

    /** Tìm instance bất kỳ (dùng cho admin). */
    public static function findInstanceAny(string $vpsId): ?array
    {
        return Database::fetch('SELECT * FROM vps_vz_instances WHERE vps_id = ?', [$vpsId]);
    }

    public static function allInstances(int $limit = 200): array
    {
        return Database::fetchAll(
            'SELECT v.*, u.username FROM vps_vz_instances v
             JOIN users u ON u.id = v.user_id
             ORDER BY v.id DESC LIMIT ' . (int)$limit
        );
    }

    // =================================================================
    //  Lịch sử thao tác
    // =================================================================

    public static function logAction(?int $userId, string $vpsId, string $action, string $status, string $message = '', array $meta = []): void
    {
        try {
            Database::insert('vps_vz_actions', [
                'user_id'   => $userId,
                'vps_id'    => $vpsId,
                'action'    => $action,
                'status'    => $status,
                'message'   => mb_substr($message, 0, 500),
                'meta'      => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
                'created_at'=> now(),
            ]);
        } catch (\Throwable $e) {
            // Không ghi được log không ảnh hưởng nghiệp vụ
        }
    }

    public static function actionHistory(string $vpsId, int $limit = 30): array
    {
        return Database::fetchAll(
            'SELECT * FROM vps_vz_actions WHERE vps_id = ? ORDER BY id DESC LIMIT ' . (int)$limit,
            [$vpsId]
        );
    }
}
