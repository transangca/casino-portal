<?php
/**
 * Dịch vụ VPS VMware — tích hợp XVPS Agency API.
 *
 * Base URL: https://api.xvps.vn
 *
 * Xác thực (3 headers bắt buộc mọi request):
 *   api-username, api-app, api-secret
 *   + auth-token (JWT) cho mọi endpoint trừ get-token
 *
 * Endpoint:
 *   POST /api/agency/get-token              → lấy JWT token (TTL 8h)
 *   GET  /api/agency/get-info                → thông tin tài khoản (credit, tên)
 *   GET  /api/agency/get-product             → danh sách gói VPS & addons
 *   GET  /api/agency/get-list-os             → danh sách hệ điều hành
 *   POST /api/agency/order/create-order      → tạo đơn VPS mới
 *   GET  /api/agency/vps/get-list-vps        → danh sách VPS đang sở hữu
 *   GET  /api/agency/vps/get-info-vps        → chi tiết VPS (IP, credentials)
 *   POST /api/agency/vps/action-vps          → start/stop/restart/rebuild/renew/addon
 *
 * Nguyên tắc nghiệp vụ:
 *  - Token JWT được cache lại (TTL 8h, hết hạn thì lấy token mới tự động).
 *  - Giá bán = giá bảng giá đối tác + biên lợi nhuận, KHÔNG cộng VAT.
 *  - Tạo đơn → trừ ví chính users.balance của user, hoàn tiền tức thì nếu lỗi.
 *  - Đơn qua web khách luôn tính đúng giá bán hiển thị.
 */

namespace App\Services;

use App\Core\Crypto;
use App\Core\Database;

class XvpsService
{
    public const DEFAULT_BASE_URL = 'https://api.xvps.vn';

    /** TTL cache token (giây) — token đối tác dùng 8h, cache 7h để an toàn */
    private const TOKEN_TTL = 25200;

    public const STATUS_PENDING    = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED  = 'completed';
    public const STATUS_FAILED     = 'failed';

    /** Chu kỳ thanh toán hỗ trợ */
    public const CYCLES = [
        'monthly'        => '1 Tháng',
        'quarterly'      => '3 Tháng',
        'semi-annually'  => '6 Tháng',
        'annually'       => '12 Tháng',
        'yearly'         => '12 Tháng',
    ];

    /** Action hỗ trợ trên VPS */
    public const ACTIONS = [
        'start'              => 'Khởi động',
        'stop'               => 'Tắt máy',
        'restart'            => 'Khởi động lại',
        'confirm-rebuild-vps' => 'Cài lại OS',
        'renew-vps'          => 'Gia hạn',
        'addon-vps'          => 'Nâng cấp tài nguyên',
    ];

    /** Quy đổi trạng thái VPS đối tác → nội bộ */
    public const STATUS_MAP = [
        'on'          => self::STATUS_COMPLETED,
        'off'         => self::STATUS_COMPLETED,
        'running'     => self::STATUS_COMPLETED,
        'stopped'     => self::STATUS_COMPLETED,
        'expire'      => self::STATUS_FAILED,
        'expired'     => self::STATUS_FAILED,
        'suspend'     => self::STATUS_FAILED,
        'suspended'   => self::STATUS_FAILED,
        'pending'     => self::STATUS_PENDING,
        'progressing' => self::STATUS_PROCESSING,
        'creating'    => self::STATUS_PROCESSING,
        'deleted'     => self::STATUS_FAILED,
        'cancelled'   => self::STATUS_FAILED,
    ];

    // =================================================================
    //  Cấu hình
    // =================================================================

    public static function baseUrl(): string
    {
        $url = rtrim(trim((string)setting('xvps_base_url', '')), '/');
        if ($url === '') {
            $url = (string)config('xvps.base_url', self::DEFAULT_BASE_URL);
        }
        $url = rtrim(trim($url), '/');

        return $url !== '' ? $url : self::DEFAULT_BASE_URL;
    }

    public static function apiUsername(): string
    {
        $v = trim((string)setting('xvps_api_username', ''));
        if ($v === '') {
            $v = trim((string)config('xvps.api_username', ''));
        }
        return $v;
    }

    public static function apiApp(): string
    {
        $v = trim((string)setting('xvps_api_app', ''));
        if ($v === '') {
            $v = trim((string)config('xvps.api_app', ''));
        }
        return $v;
    }

    public static function apiSecret(): string
    {
        $raw = (string)setting('xvps_api_secret', '');
        if ($raw !== '') {
            $dec = Crypto::decrypt($raw);
            $key = $dec ?? $raw;
            if ($key !== '') {
                return trim($key);
            }
        }

        return trim((string)config('xvps.api_secret', ''));
    }

    public static function saveCredentials(string $username, string $app, string $secret): void
    {
        setting_set('xvps_api_username', trim($username), 'XVPS');
        setting_set('xvps_api_app', trim($app), 'XVPS');
        setting_set(
            'xvps_api_secret',
            trim($secret) === '' ? '' : Crypto::encrypt(trim($secret)),
            'XVPS',
            'password'
        );
    }

    public static function configured(): bool
    {
        return self::apiUsername() !== '' && self::apiApp() !== '' && self::apiSecret() !== '';
    }

    public static function enabled(): bool
    {
        $v = (string)setting('xvps_service_on', '');
        if ($v === '') {
            return (bool)config('xvps.service_on', true);
        }
        return $v === '1';
    }

    /** Biên lợi nhuận (%) */
    public static function markup(): float
    {
        return (float)setting('xvps_markup', '0');
    }

    /** VAT của dịch vụ VPS luôn bằng 0 */
    public static function vatPercent(): float
    {
        return 0.0;
    }

    // =================================================================
    //  Giao tiếp API
    // =================================================================

    /**
     * Gọi API XVPS.
     *
     * @return array ['ok'=>bool,'data'=>array,'message'=>string,'status'=>int]
     */
    public static function request(string $method, string $endpoint, array $payload = [], array $query = [], bool $withToken = true): array
    {
        if (!self::configured()) {
            return [
                'ok'      => false,
                'data'    => [],
                'message' => 'Chưa cấu hình thông tin API XVPS. Admin nhập tại /admin/vps.',
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
                    'message' => 'Không lấy được token xác thực từ XVPS. Kiểm tra lại thông tin API.',
                    'status'  => 401,
                ];
            }
            $headers[] = 'auth-token: ' . $token;
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
                'message' => 'Không kết nối được tới API XVPS. Vui lòng thử lại sau.',
                'status'  => 0,
            ];
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return [
                'ok'      => false,
                'data'    => [],
                'message' => 'API XVPS trả về dữ liệu không hợp lệ.',
                'status'  => 0,
            ];
        }

        // XVPS dùng `error` field: 0 = thành công, khác 0 = lỗi
        $error = (int)($decoded['error'] ?? 0);
        $ok    = $error === 0;
        $msg   = (string)($decoded['message'] ?? '');

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
        $cached = (string)setting('xvps_auth_token', '');
        $ts     = (int)setting('xvps_token_time', '0');

        if (!$force && $cached !== '' && (time() - $ts) < self::TOKEN_TTL) {
            return $cached;
        }

        $res = self::request('POST', '/api/agency/get-token', [], [], false);
        if (!$res['ok']) {
            return '';
        }

        $token = (string)($res['data']['auth-token'] ?? '');
        if ($token === '') {
            return '';
        }

        setting_set('xvps_auth_token', $token, 'XVPS', 'password');
        setting_set('xvps_token_time', (string)time(), 'XVPS');

        return $token;
    }

    public static function clearToken(): void
    {
        setting_set('xvps_auth_token', '', 'XVPS', 'password');
        setting_set('xvps_token_time', '0', 'XVPS');
    }

    // =================================================================
    //  Tính tiền
    // =================================================================

    /**
     * Giá bán sau khi cộng biên lợi nhuận.
     *
     * Dùng phép nhân với số nguyên rồi chia lại để tránh lỗi làm tròn
     * của số thực (100000 * 1.1 = 110000.00000000001 → ceil thành 110001).
     * Biên lợi nhuận tính theo phần trăm, giữ đến 2 chữ số thập phân.
     */
    public static function sellPrice(float $price): float
    {
        $markup = self::markup();
        if ($markup <= 0) {
            return $price;
        }
        // Điểm phần trăm (0.01%) — tránh lỗi số thực
        $bps = (int)round($markup * 100);

        return (float)ceil(($price * (10000 + $bps)) / 10000);
    }

    /**
     * Giá gốc của 1 gói theo chu kỳ.
     */
    public static function cyclePrice(array $product, string $cycle): float
    {
        $map = [
            'monthly'        => 'price_monthly',
            'quarterly'      => 'price_quarterly',
            'semi-annually'  => 'price_semi_annually',
            'annually'       => 'price_annually',
            'yearly'         => 'price_annually',
        ];
        $col = $map[$cycle] ?? 'price_monthly';

        return (float)($product[$col] ?? 0);
    }

    /**
     * Tính tổng tiền đơn (không VAT).
     *
     * Quy ước đơn vị addon theo API XVPS:
     *   - addon-cpu  : 1 đơn vị = 1 vCPU
     *   - addon-ram  : 1 đơn vị = 1 GB RAM
     *   - addon-disk : 1 đơn vị = 10 GB Disk (API yêu cầu số GB chia hết cho 10)
     *
     * @return array{item:float,subtotal:float,vat:float,total:float,cycle:string,quantity:int}
     */
    public static function calcTotal(array $product, string $cycle, int $quantity = 1, int $addonCpu = 0, int $addonRam = 0, int $addonDisk = 0): array
    {
        $quantity = max(1, $quantity);

        $unit = self::sellPrice(self::cyclePrice($product, $cycle));

        // Addon: tính theo số đơn vị của từng loại
        $addonCost = 0.0;
        $addons = [
            ['addon_cpu',  $addonCpu],
            ['addon_ram',  $addonRam],
            ['addon_disk', $addonDisk],
        ];

        foreach ($addons as [$type, $qty]) {
            if ($qty <= 0) {
                continue;
            }

            // Disk bán theo khối 10GB
            $units = ($type === 'addon_disk') ? intdiv($qty, 10) : $qty;
            if ($units <= 0) {
                continue;
            }

            $addonCost += self::addonUnitPrice($product, $type, $cycle) * $units;
        }

        $subtotal = $unit * $quantity + $addonCost;
        $vat      = 0.0;

        return [
            'item'     => $unit,
            'subtotal' => $subtotal,
            'vat'      => $vat,
            'total'    => $subtotal + $vat,
            'cycle'    => $cycle,
            'quantity' => $quantity,
        ];
    }

    /**
     * Đơn giá bán của 1 đơn vị addon theo chu kỳ.
     *
     * Nguồn giá (theo thứ tự ưu tiên):
     *  1. pricing_json nội tuyến trong bản thân product
     *  2. Bảng addon trong DB (vps_products.is_addon = 1, type_addon = ...)
     *  3. Chính product hiện tại (khi product là addon)
     */
    public static function addonUnitPrice(array $product, string $type, string $cycle = 'monthly'): float
    {
        $cycleKey = $cycle === 'yearly' ? 'annually' : $cycle;

        // 1. pricing_json nội tuyến
        $pricing = json_decode((string)($product['pricing_json'] ?? '{}'), true);
        if (is_array($pricing) && isset($pricing[$type][$cycleKey]['amount'])) {
            return self::sellPrice((float)$pricing[$type][$cycleKey]['amount']);
        }

        // 2. Bảng addon trong DB
        $addon = self::findAddon($type);
        if ($addon) {
            return self::sellPrice(self::cyclePrice($addon, $cycle));
        }

        // 3. Fallback: nếu chính product là addon
        return self::sellPrice(self::cyclePrice($product, $cycle));
    }

    /**
     * Tìm gói addon theo type_addon trong DB.
     */
    public static function findAddon(string $type): ?array
    {
        if ($type === '') {
            return null;
        }

        return Database::fetch(
            'SELECT * FROM vps_products
             WHERE is_active = 1 AND is_addon = 1 AND type_addon = ?
             ORDER BY sort_order, id LIMIT 1',
            [$type]
        );
    }

    // =================================================================
    //  Endpoint wrappers
    // =================================================================

    /**
     * GET /api/agency/get-info — thông tin tài khoản (credit, tên).
     */
    public static function info(): array
    {
        $res = self::request('GET', '/api/agency/get-info');
        if (!$res['ok']) {
            return ['ok' => false, 'balance' => 0.0, 'currency' => 'VND', 'message' => $res['message'], 'user' => []];
        }

        $d = (array)($res['data']['data'] ?? []);

        return [
            'ok'       => true,
            'balance'  => (float)($d['credit'] ?? 0),
            'currency' => 'VND',
            'user'     => [
                'agency_name' => (string)($d['agency_name'] ?? $d['name'] ?? ''),
            ],
            'message'  => '',
        ];
    }

    /**
     * GET /api/agency/get-product — danh sách gói VPS & addons.
     */
    public static function products(): array
    {
        $res = self::request('GET', '/api/agency/get-product');
        if (!$res['ok']) {
            return [];
        }

        $out = [];
        $groups = (array)($res['data']['products']['vps'] ?? []);
        foreach ($groups as $g) {
            foreach ((array)($g['product'] ?? []) as $p) {
                $out[] = self::normalizeProduct($p, (string)($g['group_product_name'] ?? ''), false);
            }
        }

        $addonGroups = (array)($res['data']['products']['addon_vps'] ?? []);
        foreach ($addonGroups as $g) {
            foreach ((array)($g['product'] ?? []) as $p) {
                $out[] = self::normalizeProduct($p, (string)($g['group_product_name'] ?? ''), true);
            }
        }

        return $out;
    }

    /**
     * GET /api/agency/get-list-os — danh sách hệ điều hành.
     */
    public static function operatingSystems(): array
    {
        $res = self::request('GET', '/api/agency/get-list-os');
        if (!$res['ok']) {
            return [];
        }

        $out = [];
        foreach ((array)($res['data']['os-vps'] ?? []) as $os) {
            $out[] = [
                'os_id'   => (string)($os['os-id'] ?? $os['os_id'] ?? ''),
                'os_name' => (string)($os['os-name'] ?? $os['os_name'] ?? ''),
                'os_icon' => (string)($os['os-icon'] ?? $os['os_icon'] ?? ''),
                'distro'  => (string)($os['distro'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * POST /api/agency/order/create-order — tạo đơn VPS mới.
     */
    public static function createOrder(
        string $productId,
        string $billingCycle,
        string $osId,
        int $quantity = 1,
        int $addonCpu = 0,
        int $addonRam = 0,
        int $addonDisk = 0
    ): array {
        $payload = [
            'product-id'     => $productId,
            'billing-cycle'  => $billingCycle,
            'os'             => (int)$osId,
            'quantity'       => $quantity,
            'addon-cpu'      => $addonCpu,
            'addon-ram'      => $addonRam,
            'addon-disk'     => $addonDisk,
        ];

        $res = self::request('POST', '/api/agency/order/create-order', $payload);
        if (!$res['ok']) {
            return ['ok' => false, 'message' => $res['message'], 'data' => [], 'raw' => $res['data']];
        }

        $d = (array)$res['data'];

        return [
            'ok'       => true,
            'message'  => (string)($d['message'] ?? 'Đơn VPS đã được tạo.'),
            'credit'   => (float)($d['credit'] ?? 0),
            'total'    => (float)($d['total'] ?? 0),
            'data'     => (array)($d['data'] ?? []),
            'raw'      => $d,
        ];
    }

    /**
     * GET /api/agency/vps/get-list-vps — danh sách VPS.
     */
    public static function listVps(string $type = 'all', int $qtt = 300, int $page = 1): array
    {
        $res = self::request('GET', '/api/agency/vps/get-list-vps', [], [
            'type' => $type,
            'qtt'  => $qtt,
            'page' => $page,
        ]);

        if (!$res['ok']) {
            return ['ok' => false, 'message' => $res['message'], 'data' => [], 'total' => 0, 'page' => $page];
        }

        return [
            'ok'      => true,
            'message' => '',
            'data'    => (array)($res['data']['data'] ?? []),
            'total'   => (int)($res['data']['total'] ?? 0),
            'page'    => (int)($res['data']['page'] ?? $page),
        ];
    }

    /**
     * GET /api/agency/vps/get-info-vps — chi tiết VPS (IP, credentials).
     *
     * @param array<int|string> $vpsIds
     */
    public static function infoVps(array $vpsIds): array
    {
        if (!$vpsIds) {
            return ['ok' => false, 'message' => 'Thiếu vps-id', 'data' => []];
        }

        $query = [];
        foreach ($vpsIds as $id) {
            $query[] = 'vps-id=' . rawurlencode((string)$id);
        }

        $url = self::baseUrl() . '/api/agency/vps/get-info-vps?' . implode('&', $query);

        $headers = [
            'api-username: ' . self::apiUsername(),
            'api-app: ' . self::apiApp(),
            'api-secret: ' . self::apiSecret(),
            'Accept: application/json',
        ];

        $token = self::token();
        if ($token === '') {
            return ['ok' => false, 'message' => 'Không lấy được token xác thực từ XVPS.', 'data' => []];
        }
        $headers[] = 'auth-token: ' . $token;

        $raw = HttpClient::get($url, $headers, ['timeout' => 30, 'retries' => 1, 'allow_error' => true]);
        if ($raw === null) {
            return ['ok' => false, 'message' => 'Không kết nối được tới API XVPS.', 'data' => []];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || (int)($decoded['error'] ?? 0) !== 0) {
            return [
                'ok'      => false,
                'message' => (string)($decoded['message'] ?? 'API XVPS trả về lỗi.'),
                'data'    => [],
            ];
        }

        return [
            'ok'      => true,
            'message' => '',
            'data'    => (array)($decoded['data'] ?? []),
        ];
    }

    /**
     * POST /api/agency/vps/action-vps — hành động trên VPS.
     */
    public static function actionVps(string $action, string $vpsId, array $extra = []): array
    {
        if (!isset(self::ACTIONS[$action])) {
            return ['ok' => false, 'message' => 'Hành động không hợp lệ: ' . $action, 'data' => []];
        }

        $payload = array_merge([
            'action' => $action,
            'vps-id' => $vpsId,
        ], $extra);

        $res = self::request('POST', '/api/agency/vps/action-vps', $payload);
        if (!$res['ok']) {
            return ['ok' => false, 'message' => $res['message'], 'data' => $res['data']];
        }

        return [
            'ok'      => true,
            'message' => (string)($res['data']['message'] ?? 'Thực hiện thành công.'),
            'data'    => $res['data'],
        ];
    }

    // =================================================================
    //  Chuẩn hoá
    // =================================================================

    /**
     * Chuẩn hoá 1 product (gói VPS hoặc addon).
     */
    public static function normalizeProduct(array $p, string $groupName = '', bool $isAddon = false): array
    {
        $pricing = (array)($p['pricing'] ?? []);

        return [
            'product_id'    => (string)($p['product_id'] ?? ''),
            'group_name'    => $groupName,
            'name'          => (string)($p['name'] ?? $p['display_name'] ?? ''),
            'special'       => (int)($p['special'] ?? 0),
            'cpu'           => (int)($p['cpu'] ?? 0),
            'ram'           => (int)($p['ram'] ?? 0),
            'disk'          => (int)($p['disk'] ?? 0),
            'ip_info'       => (string)($p['ip'] ?? ''),
            'os_support'    => (string)($p['os'] ?? ''),
            'bandwidth'     => (string)($p['bandwidth'] ?? ''),
            'type_addon'    => (string)($p['type_addon'] ?? ''),
            'is_addon'      => $isAddon,
            'price_monthly' => (float)($pricing['monthly']['amount'] ?? 0),
            'price_quarterly' => (float)($pricing['quarterly']['amount'] ?? 0),
            'price_semi_annually' => (float)($pricing['semi_annually']['amount'] ?? 0),
            'price_annually' => (float)($pricing['annually']['amount'] ?? 0),
            'pricing_json'  => json_encode($pricing, JSON_UNESCAPED_UNICODE),
        ];
    }

    /**
     * Quy đổi trạng thái VPS đối tác → nội bộ.
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

    /** Nhãn tiếng Việt của trạng thái VPS gốc */
    public static function vpsStatusLabel(string $raw): string
    {
        return [
            'on'          => 'Đang chạy',
            'off'         => 'Đã tắt',
            'expire'      => 'Đã hết hạn',
            'suspend'     => 'Đã đình chỉ',
            'pending'     => 'Đang chờ',
            'progressing' => 'Đang tạo',
            'deleted'     => 'Đã xoá',
        ][strtolower(trim($raw))] ?? ucfirst($raw ?: '—');
    }

    public static function vpsStatusBadge(string $raw): string
    {
        return [
            'on'          => 'success',
            'off'         => 'secondary',
            'expire'      => 'danger',
            'suspend'     => 'danger',
            'pending'     => 'warning',
            'progressing' => 'info',
            'deleted'     => 'secondary',
        ][strtolower(trim($raw))] ?? 'secondary';
    }

    // =================================================================
    //  Đồng bộ danh mục
    // =================================================================

    /**
     * Đồng bộ gói VPS + addons + OS từ API vào DB.
     */
    public static function sync(): array
    {
        if (!self::configured()) {
            return ['ok' => false, 'message' => 'Chưa cấu hình thông tin API XVPS.', 'products' => 0, 'os' => 0];
        }

        $products = self::products();
        if (!$products) {
            return ['ok' => false, 'message' => 'Không tải được danh sách gói VPS từ API XVPS.', 'products' => 0, 'os' => 0];
        }

        $nProd = 0;
        $seen  = [];

        foreach ($products as $p) {
            $pid = (string)$p['product_id'];
            if ($pid === '') {
                continue;
            }

            $row = [
                'product_id'     => $pid,
                'group_name'     => mb_substr((string)$p['group_name'], 0, 300),
                'name'           => mb_substr((string)$p['name'], 0, 300),
                'special'        => (int)$p['special'],
                'cpu'            => (int)$p['cpu'],
                'ram'            => (int)$p['ram'],
                'disk'           => (int)$p['disk'],
                'ip_info'        => mb_substr((string)$p['ip_info'], 0, 190),
                'os_support'     => mb_substr((string)$p['os_support'], 0, 190),
                'bandwidth'      => mb_substr((string)$p['bandwidth'], 0, 190),
                'type_addon'     => mb_substr((string)$p['type_addon'], 0, 120),
                'is_addon'       => (int)$p['is_addon'],
                'price_monthly'  => (float)$p['price_monthly'],
                'price_quarterly' => (float)$p['price_quarterly'],
                'price_semi_annually' => (float)$p['price_semi_annually'],
                'price_annually' => (float)$p['price_annually'],
                'pricing_json'   => (string)$p['pricing_json'],
                'is_active'      => 1,
                'sort_order'     => $nProd,
                'updated_at'     => now(),
            ];

            $exists = Database::fetchColumn('SELECT id FROM vps_products WHERE product_id = ?', [$pid]);
            if ($exists) {
                Database::update('vps_products', $row, 'id = :id', ['id' => (int)$exists]);
            } else {
                Database::insert('vps_products', $row);
            }

            $seen[] = $pid;
            $nProd++;
        }

        // Sản phẩm không còn trên API → tắt
        if ($seen) {
            $inList = "'" . implode("','", array_map(static fn($s) => str_replace("'", '', (string)$s), $seen)) . "'";
            Database::query(
                "UPDATE vps_products SET is_active = 0, updated_at = NOW() WHERE is_active = 1 AND product_id NOT IN ($inList)"
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
                'os_icon'   => mb_substr((string)$os['os_icon'], 0, 500),
                'distro'    => mb_substr((string)$os['distro'], 0, 120),
                'is_active' => 1,
                'updated_at' => now(),
            ];

            $exists = Database::fetchColumn('SELECT id FROM vps_operating_systems WHERE os_id = ?', [$osId]);
            if ($exists) {
                Database::update('vps_operating_systems', $row, 'id = :id', ['id' => (int)$exists]);
            } else {
                Database::insert('vps_operating_systems', $row);
            }
            $nOs++;
        }

        return [
            'ok'       => true,
            'message'  => "Đã đồng bộ $nProd gói VPS, $nOs hệ điều hành.",
            'products' => $nProd,
            'os'       => $nOs,
        ];
    }

    // =================================================================
    //  Truy vấn DB
    // =================================================================

    public static function localProducts(bool $includeAddons = false): array
    {
        $sql = 'SELECT * FROM vps_products WHERE is_active = 1';
        if (!$includeAddons) {
            $sql .= ' AND is_addon = 0';
        }
        $sql .= ' ORDER BY group_name, sort_order, name';

        return Database::fetchAll($sql);
    }

    /** Danh sách addon dùng để tính giá nâng cấp */
    public static function localAddons(): array
    {
        return Database::fetchAll(
            'SELECT * FROM vps_products WHERE is_active = 1 AND is_addon = 1 ORDER BY sort_order, name'
        );
    }

    public static function findProduct(string $productId): ?array
    {
        return Database::fetch('SELECT * FROM vps_products WHERE product_id = ?', [$productId]);
    }

    public static function localOperatingSystems(): array
    {
        return Database::fetchAll(
            'SELECT * FROM vps_operating_systems WHERE is_active = 1 ORDER BY os_name'
        );
    }

    public static function findOperatingSystem(string $osId): ?array
    {
        return Database::fetch('SELECT * FROM vps_operating_systems WHERE os_id = ?', [$osId]);
    }

    public static function productGroups(): array
    {
        $groups = [];
        foreach (self::localProducts() as $p) {
            $g = (string)($p['group_name'] ?: 'VPS');
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
            'user_id'        => $userId,
            'status'         => self::STATUS_PENDING,
            'quantity'       => 1,
            'created_at'     => now(),
            'updated_at'     => now(),
        ], $data);

        // Idempotency: cùng key → trả về đơn cũ
        if (!empty($row['idempotency_key'])) {
            $existing = Database::fetch(
                'SELECT id FROM vps_orders WHERE idempotency_key = ? AND user_id = ?',
                [(string)$row['idempotency_key'], $userId]
            );
            if ($existing) {
                return (int)$existing['id'];
            }
        }

        $newId = Database::insert('vps_orders', $row);

        return $newId;
    }

    public static function findOrder(int $userId, int $orderId): ?array
    {
        return Database::fetch(
            'SELECT * FROM vps_orders WHERE id = ? AND user_id = ?',
            [$orderId, $userId]
        );
    }

    public static function allOrders(int $limit = 100): array
    {
        return Database::fetchAll(
            'SELECT o.*, u.username FROM vps_orders o
             JOIN users u ON u.id = o.user_id
             ORDER BY o.id DESC LIMIT ' . (int)$limit
        );
    }

    public static function userOrders(int $userId, int $limit = 50): array
    {
        return Database::fetchAll(
            'SELECT * FROM vps_orders WHERE user_id = ? ORDER BY id DESC LIMIT ' . (int)$limit,
            [$userId]
        );
    }

    public static function stats(): array
    {
        return [
            'orders'    => (int)Database::fetchColumn('SELECT COUNT(*) FROM vps_orders'),
            'active'    => (int)Database::fetchColumn(
                'SELECT COUNT(*) FROM vps_instances WHERE vps_status IN ("on","off","running","stopped")'
            ),
            'pending'   => (int)Database::fetchColumn(
                'SELECT COUNT(*) FROM vps_orders WHERE status IN ("pending","processing")'
            ),
            'products'  => (int)Database::fetchColumn(
                'SELECT COUNT(*) FROM vps_products WHERE is_active = 1 AND is_addon = 0'
            ),
            'revenue'   => (float)Database::fetchColumn(
                "SELECT COALESCE(SUM(amount),0) FROM vps_orders WHERE status <> 'failed'"
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
        $res = self::listVps();
        if (!$res['ok']) {
            return ['ok' => false, 'message' => $res['message'], 'count' => 0];
        }

        $n = 0;
        foreach ($res['data'] as $v) {
            $vpsId = (string)($v['vps-id'] ?? '');
            if ($vpsId === '') {
                continue;
            }
            self::upsertInstance($userId, $v);
            $n++;
        }

        return ['ok' => true, 'message' => "Đã đồng bộ $n VPS.", 'count' => $n];
    }

    /**
     * Lưu/cập nhật 1 VPS instance.
     *
     * Lưu ý: endpoint `get-list-vps` KHÔNG trả về username/password,
     * chỉ `get-info-vps` mới có. Vì vậy các trường rỗng sẽ được giữ
     * nguyên giá trị cũ thay vì bị ghi đè thành chuỗi rỗng.
     */
    public static function upsertInstance(int $userId, array $v): void
    {
        $vpsId = (string)($v['vps-id'] ?? '');
        if ($vpsId === '') {
            return;
        }

        $existing = Database::fetch('SELECT * FROM vps_instances WHERE vps_id = ?', [$vpsId]);

        $pick = static function (array $src, array $keys, string $fallback = ''): string {
            foreach ($keys as $k) {
                if (isset($src[$k]) && trim((string)$src[$k]) !== '') {
                    return (string)$src[$k];
                }
            }
            // Không có giá trị mới → giữ giá trị cũ nếu có
            return $fallback;
        };

        $old = $existing ?: [];

        $row = [
            'user_id'       => $userId,
            'vps_id'        => $vpsId,
            'order_code'    => $pick($v, ['order-code', 'order_code'], $old['order_code'] ?? ''),
            'product_id'    => $pick($v, ['product-id', 'product_id'], $old['product_id'] ?? ''),
            'ip'            => $pick($v, ['ip'], $old['ip'] ?? ''),
            'main_ipv4'     => $pick($v, ['main-ipv4', 'main_ipv4', 'ip'], $old['main_ipv4'] ?? ''),
            'username'      => $pick($v, ['username'], $old['username'] ?? ''),
            'password'      => $pick($v, ['password'], $old['password'] ?? ''),
            'os'            => $pick($v, ['os'], $old['os'] ?? ''),
            'cpu'           => (int)($v['cpu'] ?? $old['cpu'] ?? 0),
            'ram'           => (int)($v['ram'] ?? $old['ram'] ?? 0),
            'disk'          => (int)($v['disk'] ?? $old['disk'] ?? 0),
            'text_config'   => $pick($v, ['text-config', 'text_config'], $old['text_config'] ?? ''),
            'type_vps'      => $pick($v, ['type-vps', 'type_vps'], $old['type_vps'] ?? ''),
            'billing_cycle' => $pick($v, ['billing-cycle', 'billing_cycle'], $old['billing_cycle'] ?? ''),
            'day_left'      => (int)($v['day_left'] ?? $old['day_left'] ?? 0),
            'vps_status'    => (string)($v['vps-status'] ?? $v['vps_status'] ?? $old['vps_status'] ?? 'unknown'),
            'date_create'   => $pick($v, ['date_create'], $old['date_create'] ?? ''),
            'next_due_date' => $pick($v, ['next_due_date'], $old['next_due_date'] ?? ''),
            'intro'         => $pick($v, ['intro'], $old['intro'] ?? ''),
            'last_sync_at'  => now(),
            'updated_at'    => now(),
        ];

        if ($existing) {
            Database::update('vps_instances', $row, 'id = :id', ['id' => (int)$existing['id']]);
        } else {
            Database::insert('vps_instances', $row);
        }
    }

    public static function userInstances(int $userId): array
    {
        return Database::fetchAll(
            'SELECT * FROM vps_instances WHERE user_id = ? ORDER BY vps_status = "on" DESC, id DESC',
            [$userId]
        );
    }

    public static function findInstance(int $userId, string $vpsId): ?array
    {
        return Database::fetch(
            'SELECT * FROM vps_instances WHERE vps_id = ? AND user_id = ?',
            [$vpsId, $userId]
        );
    }

    public static function allInstances(int $limit = 200): array
    {
        return Database::fetchAll(
            'SELECT v.*, u.username FROM vps_instances v
             JOIN users u ON u.id = v.user_id
             ORDER BY v.id DESC LIMIT ' . (int)$limit
        );
    }
}
