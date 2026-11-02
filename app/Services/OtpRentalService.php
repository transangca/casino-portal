<?php
/**
 * Dịch vụ thuê số nhận OTP — API ViOTP v1 (https://api.viotp.net).
 *
 * Endpoints:
 *  GET  /v1/balance              → số dư tài khoản (VND)
 *  GET  /v1/carriers             → danh sách nhà mạng
 *  GET  /v1/services             → danh sách dịch vụ (id, name, price)
 *  POST /v1/rentals              → thuê số mới
 *  POST /v1/rentals/rerent       → thuê lại số cũ
 *  GET  /v1/rentals/{id}         → trạng thái + mã OTP
 *  GET  /v1/rentals?...          → lịch sử thuê (5 query param bắt buộc)
 *
 * Xác thực: header `Authorization: Bearer {token}` (không đặt token trên URL).
 * Lỗi trả về theo chuẩn application/problem+json: {type,title,status,code,detail}.
 */

namespace App\Services;

use App\Core\Crypto;
use App\Core\Database;

class OtpRentalService
{
    public const DEFAULT_BASE_URL = 'https://api.viotp.net';

    /** Mã lỗi → thông báo tiếng Việt */
    public const ERROR_CODES = [
        'invalid_body'         => 'Thân request không hợp lệ hoặc thiếu trường bắt buộc.',
        'invalid_query'        => 'Tham số truy vấn sai hoặc thiếu.',
        'invalid_carrier'      => 'Nhà mạng không thuộc quốc gia được chọn.',
        'invalid_prefix'       => 'Đầu số không thuộc nhà mạng đã chọn.',
        'invalid_token'        => 'Token API thiếu, sai hoặc đã hết hạn.',
        'api_disabled'         => 'Tài khoản chưa được cấp quyền sử dụng API.',
        'rental_not_found'     => 'Không tìm thấy lượt thuê này trong tài khoản.',
        'api_not_supported'    => 'Dịch vụ không hỗ trợ API.',
        'insufficient_balance' => 'Số dư tài khoản không đủ.',
        'out_of_stock'         => 'Hết số phù hợp, vui lòng thử lại sau.',
        'service_closed'       => 'Dịch vụ đang tạm đóng.',
        'service_unavailable'  => 'Dịch vụ chưa sẵn sàng để thuê lại.',
        'rerent_unavailable'   => 'Số muốn thuê lại chưa sẵn sàng.',
        'rerent_forbidden'     => 'Không thuê lại được: đã quá 15-30 phút hoặc SIM đã tháo.',
        'rerent_taken'         => 'Số đang có người khác thuê.',
        'waiting_limit'        => 'Đã vượt giới hạn số lượt đang chờ mã cùng lúc.',
        'too_many_requests'    => 'Gọi quá nhanh, vui lòng chờ Retry-After rồi thử lại.',
        'maintenance'          => 'Hệ thống đang bảo trì ngắn hạn.',
        'busy'                 => 'Hệ thống đang bận, vui lòng thử lại sau.',
        'internal'             => 'Lỗi hệ thống của đối tác.',
    ];

    /** Nhà mạng hỗ trợ */
    public const CARRIERS = [
        'MOBIFONE'    => 'Mobifone',
        'VINAPHONE'   => 'Vinaphone',
        'VIETTEL'     => 'Viettel',
        'VIETNAMOBILE'=> 'Vietnamobile',
        'ITELECOM'    => 'Itelecom',
        'WINTEL'      => 'Wintel',
        'GTEL'        => 'Gtel',
        'VODAFONE_RO' => 'Vodafone (Roaming)',
        'METFONE'     => 'Metfone',
        'UNITEL'      => 'Unitel',
        'ETL'         => 'ETL',
        'BEELINE'     => 'Beeline',
        'LAOTEL'      => 'Laotel',
        'VIETNAMOBILE' => 'Vietnamobile',
        'VNPT'        => 'VNPT',
        'GIL'         => 'GIL',
        'FPT'         => 'FPT',
        'FPT-5G'      => 'FPT 5G',
        'STARTELECOM' => 'Startelecom',
    ];

    /** Dịch vụ hay dùng (đưa lên đầu) */
    public const POPULAR_SERVICES = [
        'Facebook', 'Gmail/Google', 'Shopee / ShopeePay', 'ShopeeFood', 'Lazada',
        'Tiktok', 'Telegram', 'Instagram', 'Grab', 'Gojek',
        'VinID', 'Paypal', 'Hotmail/Outlook', 'Yahoo', 'Vinaphone', 'Viettel',
    ];

    // =================================================================
    //  Cấu hình
    // =================================================================

    public static function baseUrl(): string
    {
        $url = rtrim(trim((string)setting('viotp_base_url', '')), '/');
        if ($url === '') {
            $url = (string)config('viotp.base_url', self::DEFAULT_BASE_URL);
        }
        $url = rtrim(trim($url), '/');

        return $url !== '' ? $url : self::DEFAULT_BASE_URL;
    }

    public static function token(): string
    {
        $raw = (string)setting('viotp_token', '');
        if ($raw !== '') {
            $dec = Crypto::decrypt($raw);
            $tok = $dec ?? $raw;
            if ($tok !== '') {
                return $tok;
            }
        }

        return trim((string)config('viotp.token', ''));
    }

    public static function configured(): bool
    {
        return self::token() !== '';
    }

    public static function enabled(): bool
    {
        $v = (string)setting('viotp_service_on', '');
        if ($v === '') {
            return (bool)config('viotp.service_on', true);
        }
        return $v === '1';
    }

    public static function saveToken(string $token): void
    {
        $token = trim($token);
        setting_set('viotp_token', $token === '' ? '' : Crypto::encrypt($token), 'Thuê số OTP', 'password');
    }

    // =================================================================
    //  HTTP
    // =================================================================

    /**
     * Gọi API ViOTP.
     *
     * @return array ['ok'=>bool,'data'=>mixed,'code'=>string,'message'=>string,'status'=>int,'retryAfter'=>int]
     */
    public static function request(string $method, string $endpoint, array $payload = [], array $query = []): array
    {
        if (!self::configured()) {
            return [
                'ok' => false, 'data' => null, 'code' => 'invalid_token',
                'message' => 'Chưa cấu hình token ViOTP. Admin nhập tại /admin/otp-rental.',
                'status' => 401, 'retryAfter' => 0,
            ];
        }

        $url = self::baseUrl() . '/' . ltrim($endpoint, '/');
        if ($query) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        $headers = [
            'Authorization: Bearer ' . self::token(),
            'Accept: application/json',
        ];

        $res = HttpClient::requestFull(
            $method,
            $url,
            $method === 'POST' ? $payload : null,
            $headers,
            ['timeout' => 30, 'connect_timeout' => 10]
        );

        $retryAfter = (int)($res['headers']['retry-after'] ?? 0);

        if ($res['body'] === null) {
            return [
                'ok' => false, 'data' => null, 'code' => 'network_error',
                'message' => 'Không kết nối được tới API ViOTP. Vui lòng thử lại sau.',
                'status' => 0, 'retryAfter' => 0,
            ];
        }

        $decoded = json_decode($res['body'], true);

        if ($res['ok']) {
            return [
                'ok' => true, 'data' => $decoded, 'code' => '', 'message' => '',
                'status' => $res['status'], 'retryAfter' => $retryAfter,
            ];
        }

        // Lỗi theo chuẩn problem+json: {type,title,status,code,detail}
        $code   = is_array($decoded) ? (string)($decoded['code'] ?? '') : '';
        $detail = is_array($decoded) ? (string)($decoded['detail'] ?? ($decoded['title'] ?? '')) : '';

        if ($code === '') {
            $code = match ($res['status']) {
                401 => 'invalid_token',
                404 => 'rental_not_found',
                422 => 'invalid_body',
                429 => 'too_many_requests',
                503 => 'busy',
                500 => 'internal',
                default => 'unknown_error',
            };
        }

        return [
            'ok' => false, 'data' => is_array($decoded) ? $decoded : null, 'code' => $code,
            'message' => $detail !== '' ? $detail : (self::ERROR_CODES[$code] ?? 'Không rõ nguyên nhân.'),
            'status' => $res['status'], 'retryAfter' => $retryAfter,
        ];
    }

    // =================================================================
    //  Tài khoản
    // =================================================================

    public static function balance(): array
    {
        $res = self::request('GET', '/v1/balance');
        if (!$res['ok']) {
            return [
                'ok' => false, 'balance' => 0.0, 'code' => $res['code'],
                'message' => $res['message'],
            ];
        }

        $d = is_array($res['data']) ? $res['data'] : [];

        return [
            'ok' => true,
            'balance' => (float)($d['balance'] ?? 0),
            'message' => '',
            'code' => '',
        ];
    }

    public static function carriers(): array
    {
        $res = self::request('GET', '/v1/carriers');
        if (!$res['ok'] || !is_array($res['data'])) {
            return [];
        }

        $out = [];
        foreach ($res['data'] as $c) {
            if (!is_array($c)) {
                continue;
            }
            $name = (string)($c['name'] ?? '');
            $out[] = [
                'id'   => (int)($c['id'] ?? 0),
                'name' => $name,
                'label'=> self::CARRIERS[$name] ?? $name,
            ];
        }

        return $out;
    }

    // =================================================================
    //  Dịch vụ
    // =================================================================

    /**
     * Danh sách dịch vụ (id, name, price).
     */
    public static function services(bool $refresh = false): array
    {
        static $cache = null;
        if (!$refresh && $cache !== null) {
            return $cache;
        }

        $res = self::request('GET', '/v1/services');
        if (!$res['ok'] || !is_array($res['data'])) {
            $cache = self::localServices();
            return $cache;
        }

        $out = [];
        foreach ($res['data'] as $s) {
            if (!is_array($s) || !isset($s['id'])) {
                continue;
            }
            $out[] = [
                'id'      => (int)$s['id'],
                'name'    => (string)($s['name'] ?? ''),
                'price'   => (float)($s['price'] ?? 0),
                'icon'    => self::serviceIcon((string)($s['name'] ?? '')),
                'is_active' => 1,
            ];
        }

        // Đồng bộ vào DB để dùng khi API lỗi
        self::syncServices($out);
        $cache = $out;

        return $out;
    }

    /**
     * Danh sách dịch vụ lấy từ DB (đã đồng bộ), có tìm kiếm + phân trang.
     *
     * @param string $keyword Từ khoá tìm theo tên
     * @param int    $page    Trang hiện tại (1-based)
     * @param int    $perPage Số dịch vụ mỗi trang
     */
    public static function catalog(string $keyword = '', int $page = 1, int $perPage = 60): array
    {
        $keyword = trim($keyword);
        $page    = max(1, $page);
        $perPage = max(12, min(120, $perPage));

        $where  = 'is_active = 1';
        $params = [];

        if ($keyword !== '') {
            $where .= ' AND name LIKE ?';
            $params[] = '%' . $keyword . '%';
        }

        $total = (int)Database::fetchColumn(
            "SELECT COUNT(*) FROM otp_services WHERE {$where}",
            $params
        );

        $offset = ($page - 1) * $perPage;
        $rows   = Database::fetchAll(
            "SELECT * FROM otp_services WHERE {$where} ORDER BY name LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $items = [];
        foreach ($rows as $r) {
            $items[] = [
                'id'        => (int)$r['service_id'],
                'name'      => (string)$r['name'],
                'price'     => (float)$r['price'],
                'icon'      => (string)($r['icon'] ?: self::serviceIcon((string)$r['name'])),
                'is_active' => (int)$r['is_active'],
            ];
        }

        return [
            'items'    => $items,
            'total'    => $total,
            'page'     => $page,
            'perPage'  => $perPage,
            'pages'    => max(1, (int)ceil($total / $perPage)),
            'keyword'  => $keyword,
        ];
    }

    /**
     * Danh sách dịch vụ phổ biến (đưa lên đầu trang).
     */
    public static function popularServices(int $limit = 24): array
    {
        $all = self::localServices();
        $out = [];

        foreach (self::POPULAR_SERVICES as $needle) {
            foreach ($all as $s) {
                if (stripos($s['name'], $needle) !== false && !in_array($s['id'], array_column($out, 'id'), true)) {
                    $out[] = $s;
                    break;
                }
            }
            if (count($out) >= $limit) {
                break;
            }
        }

        // Bổ sung nếu chưa đủ
        foreach ($all as $s) {
            if (count($out) >= $limit) {
                break;
            }
            if (!in_array($s['id'], array_column($out, 'id'), true)) {
                $out[] = $s;
            }
        }

        return array_slice($out, 0, $limit);
    }

    /**
     * Icon theo tên dịch vụ.
     */
    public static function serviceIcon(string $name): string
    {
        $n = strtolower($name);
        $map = [
            'facebook'  => 'fa-brands fa-facebook',
            'momo'      => 'fa-solid fa-mobile-screen',
            'zalo'      => 'fa-solid fa-comment-dots',
            'shopee'    => 'fa-solid fa-cart-shopping',
            'lazada'    => 'fa-solid fa-cart-shopping',
            'tiktok'    => 'fa-brands fa-tiktok',
            'instagram' => 'fa-brands fa-instagram',
            'telegram'  => 'fa-brands fa-telegram',
            'google'    => 'fa-brands fa-google',
            'twitter'   => 'fa-brands fa-x-twitter',
            'linkedin'  => 'fa-brands fa-linkedin',
            'bank'      => 'fa-solid fa-building-columns',
            'viettel'   => 'fa-solid fa-tower-broadcast',
            'vinaphone' => 'fa-solid fa-tower-broadcast',
            'vinamobi'  => 'fa-solid fa-tower-broadcast',
            'mobifone'  => 'fa-solid fa-tower-broadcast',
        ];
        foreach ($map as $k => $icon) {
            if (str_contains($n, $k)) {
                return $icon;
            }
        }

        return 'fa-solid fa-comment-sms';
    }

    /**
     * Đồng bộ danh sách dịch vụ vào DB.
     */
    public static function syncServices(array $services): void
    {
        $existing = [];
        foreach (Database::fetchAll('SELECT id, service_id FROM otp_services') as $r) {
            $existing[(int)$r['service_id']] = (int)$r['id'];
        }

        foreach ($services as $s) {
            $data = [
                'service_id' => (int)$s['id'],
                'name'       => mb_substr((string)$s['name'], 0, 100),
                'price'      => (float)$s['price'],
                'icon'       => (string)$s['icon'],
                'is_active'  => 1,
                'updated_at' => now(),
            ];
            if (isset($existing[(int)$s['id']])) {
                Database::update('otp_services', $data, 'id = :id', ['id' => $existing[(int)$s['id']]]);
            } else {
                Database::insert('otp_services', $data);
            }
        }
    }

    /**
     * Dịch vụ lưu cục bộ (fallback khi API lỗi).
     */
    public static function localServices(): array
    {
        $out = [];
        foreach (Database::fetchAll('SELECT * FROM otp_services WHERE is_active = 1 ORDER BY price, name') as $r) {
            $out[] = [
                'id'        => (int)$r['service_id'],
                'name'      => (string)$r['name'],
                'price'     => (float)$r['price'],
                'icon'      => (string)($r['icon'] ?: self::serviceIcon((string)$r['name'])),
                'is_active' => (int)$r['is_active'],
            ];
        }

        return $out;
    }

    // =================================================================
    //  Thuê số
    // =================================================================

    /**
     * Thuê số mới.
     *
     * @param int      $serviceId       ID dịch vụ (bắt buộc)
     * @param string[] $carriers        Danh sách nhà mạng (tùy chọn)
     * @param string[] $includePrefixes Đầu số muốn lấy (tùy chọn)
     * @param string[] $excludePrefixes Đầu số loại trừ (tùy chọn)
     */
    public static function rent(int $serviceId, array $carriers = [], array $includePrefixes = [], array $excludePrefixes = []): array
    {
        if ($serviceId <= 0) {
            return ['ok' => false, 'code' => 'invalid_body', 'message' => 'Vui lòng chọn dịch vụ cần thuê.'];
        }

        $payload = ['serviceId' => $serviceId];
        if ($carriers) {
            $payload['carriers'] = array_values(array_map('strval', $carriers));
        }
        if ($includePrefixes) {
            $payload['includePrefixes'] = array_values(array_map('strval', $includePrefixes));
        }
        if ($excludePrefixes) {
            $payload['excludePrefixes'] = array_values(array_map('strval', $excludePrefixes));
        }

        $res = self::request('POST', '/v1/rentals', $payload);
        if (!$res['ok']) {
            return [
                'ok' => false, 'code' => $res['code'], 'message' => $res['message'],
                'retryAfter' => $res['retryAfter'],
            ];
        }

        $d = is_array($res['data']) ? $res['data'] : [];

        return [
            'ok' => true,
            'id'         => (int)($d['id'] ?? 0),
            'phone'      => (string)($d['phone'] ?? ''),
            'phoneLocal' => (string)($d['phoneLocal'] ?? ''),
            'countryIso' => (string)($d['countryIso'] ?? ''),
            'countryCode'=> (string)($d['countryCode'] ?? ''),
            'balance'    => (float)($d['balance'] ?? 0),
            'code' => '', 'message' => '',
        ];
    }

    /**
     * Thuê lại số cũ.
     */
    public static function rerent(int $serviceId, string $phone): array
    {
        if ($serviceId <= 0 || trim($phone) === '') {
            return ['ok' => false, 'code' => 'invalid_body', 'message' => 'Thiếu dịch vụ hoặc số điện thoại.'];
        }

        $res = self::request('POST', '/v1/rentals/rerent', [
            'serviceId' => $serviceId,
            'phone'     => trim($phone),
        ]);

        if (!$res['ok']) {
            return [
                'ok' => false, 'code' => $res['code'], 'message' => $res['message'],
                'retryAfter' => $res['retryAfter'],
            ];
        }

        $d = is_array($res['data']) ? $res['data'] : [];

        return [
            'ok' => true,
            'id'         => (int)($d['id'] ?? 0),
            'phone'      => (string)($d['phone'] ?? ''),
            'phoneLocal' => (string)($d['phoneLocal'] ?? ''),
            'balance'    => (float)($d['balance'] ?? 0),
            'code' => '', 'message' => '',
        ];
    }

    /**
     * Lấy trạng thái lượt thuê (gọi mỗi 5 giây).
     */
    public static function status(int $rentalId): array
    {
        if ($rentalId <= 0) {
            return ['ok' => false, 'code' => 'invalid_query', 'message' => 'ID lượt thuê không hợp lệ.'];
        }

        $res = self::request('GET', '/v1/rentals/' . $rentalId);
        if (!$res['ok']) {
            return [
                'ok' => false, 'code' => $res['code'], 'message' => $res['message'],
                'retryAfter' => $res['retryAfter'],
            ];
        }

        $d = is_array($res['data']) ? $res['data'] : [];

        return [
            'ok' => true,
            'id'          => (int)($d['id'] ?? 0),
            'serviceId'   => (int)($d['serviceId'] ?? 0),
            'serviceName' => (string)($d['serviceName'] ?? ''),
            'price'       => (float)($d['price'] ?? 0),
            'status'      => (int)($d['status'] ?? 0),
            'content'     => (string)($d['content'] ?? ''),
            'code'        => (string)($d['code'] ?? ''),
            'isAudio'     => (bool)($d['isAudio'] ?? false),
            'phone'       => (string)($d['phone'] ?? ''),
            'phoneLocal'  => (string)($d['phoneLocal'] ?? ''),
            'createdAt'   => (string)($d['createdAt'] ?? ''),
            'errorCode'   => '',
            'message'     => '',
        ];
    }

    /**
     * Lịch sử thuê số (cả 5 query param đều bắt buộc).
     */
    public static function history(int $serviceId, int $status = 0, int $limit = 100, ?string $from = null, ?string $to = null): array
    {
        $from = $from ?: date('Y-m-d', strtotime('-30 days'));
        $to   = $to   ?: date('Y-m-d');

        $res = self::request('GET', '/v1/rentals', [], [
            'serviceId' => $serviceId,
            'status'    => $status,
            'limit'     => max(1, min(1000, $limit)),
            'from'      => $from,
            'to'        => $to,
        ]);

        if (!$res['ok'] || !is_array($res['data'])) {
            return [];
        }

        $out = [];
        foreach ($res['data'] as $r) {
            if (!is_array($r)) {
                continue;
            }
            $out[] = [
                'id'          => (int)($r['id'] ?? 0),
                'serviceId'   => (int)($r['serviceId'] ?? 0),
                'serviceName' => (string)($r['serviceName'] ?? ''),
                'price'       => (float)($r['price'] ?? 0),
                'status'      => (int)($r['status'] ?? 0),
                'content'     => (string)($r['content'] ?? ''),
                'code'        => (string)($r['code'] ?? ''),
                'isAudio'     => (bool)($r['isAudio'] ?? false),
                'phone'       => (string)($r['phone'] ?? ''),
                'createdAt'   => (string)($r['createdAt'] ?? ''),
            ];
        }

        return $out;
    }

    // =================================================================
    //  Bản sao cục bộ
    // =================================================================

    /**
     * Tạo bản ghi lượt thuê.
     */
    public static function createRental(int $userId, array $data): int
    {
        return Database::insert('otp_rentals', [
            'user_id'       => $userId,
            'rental_id'     => (int)($data['rental_id'] ?? 0),
            'service_id'    => (int)($data['service_id'] ?? 0),
            'service_name'  => (string)($data['service_name'] ?? ''),
            'phone'         => (string)($data['phone'] ?? ''),
            'carriers'      => implode(',', (array)($data['carriers'] ?? [])),
            'price'         => (float)($data['price'] ?? 0),
            'status'        => (int)($data['status'] ?? 0),
            'otp_code'      => (string)($data['otp_code'] ?? ''),
            'content'       => (string)($data['content'] ?? ''),
            'is_audio'      => !empty($data['is_audio']) ? 1 : 0,
            'balance_after' => (float)($data['balance_after'] ?? 0),
            'message'       => mb_substr((string)($data['message'] ?? ''), 0, 255),
        ]);
    }

    /**
     * Cập nhật trạng thái lượt thuê.
     *
     * Nếu lượt thuê chuyển sang trạng thái 2 (hết thời gian chờ) thì đối tác đã tự hoàn tiền
     * → hệ thống hoàn tiền lại cho khách (tránh trừ oan).
     */
    public static function updateRental(int $userId, array $data): void
    {
        $rentalId = (int)($data['rental_id'] ?? 0);
        $status   = (int)($data['status'] ?? 0);

        $before = Database::fetch(
            'SELECT id, status, price FROM otp_rentals WHERE user_id = ? AND rental_id = ?',
            [$userId, $rentalId]
        );

        Database::update('otp_rentals', [
            'status'        => $status,
            'otp_code'      => (string)($data['otp_code'] ?? ''),
            'content'       => (string)($data['content'] ?? ''),
            'is_audio'      => !empty($data['is_audio']) ? 1 : 0,
            'message'       => mb_substr((string)($data['message'] ?? ''), 0, 255),
        ], 'user_id = :uid AND rental_id = :rid', [
            'uid' => $userId,
            'rid' => $rentalId,
        ]);

        // Chuyển sang hết hạn (2) lần đầu → hoàn tiền khách
        if ($before && (int)$before['status'] === 0 && $status === 2) {
            $price = (float)$before['price'];
            if ($price > 0) {
                \App\Core\Wallet::apply(
                    $userId,
                    'adjustment',
                    $price,
                    'Hoàn tiền thuê số OTP hết thời gian chờ',
                    'otp_rental_refund',
                    (string)$rentalId
                );
            }
        }
    }

    /**
     * Lượt thuê đang chờ của user.
     */
    public static function pendingRentals(int $userId): array
    {
        return Database::fetchAll(
            'SELECT * FROM otp_rentals WHERE user_id = ? AND status = 0 ORDER BY id DESC LIMIT 50',
            [$userId]
        );
    }

    /**
     * Lịch sử thuê của user.
     */
    public static function myRentals(int $userId, int $limit = 50): array
    {
        return Database::fetchAll(
            'SELECT * FROM otp_rentals WHERE user_id = ? ORDER BY id DESC LIMIT ' . (int)$limit,
            [$userId]
        );
    }

    /**
     * Tất cả lượt thuê (admin).
     */
    public static function allRentals(int $limit = 100): array
    {
        return Database::fetchAll(
            'SELECT r.*, u.username FROM otp_rentals r
             LEFT JOIN users u ON u.id = r.user_id
             ORDER BY r.id DESC LIMIT ' . (int)$limit
        );
    }

    /**
     * Thống kê (admin).
     */
    public static function stats(): array
    {
        return [
            'rentals'   => (int)Database::fetchColumn('SELECT COUNT(*) FROM otp_rentals'),
            'completed' => (int)Database::fetchColumn('SELECT COUNT(*) FROM otp_rentals WHERE status = 1'),
            'expired'   => (int)Database::fetchColumn('SELECT COUNT(*) FROM otp_rentals WHERE status = 2'),
            'pending'   => (int)Database::fetchColumn('SELECT COUNT(*) FROM otp_rentals WHERE status = 0'),
            'revenue'   => (float)Database::fetchColumn('SELECT COALESCE(SUM(price),0) FROM otp_rentals'),
            'services'  => (int)Database::fetchColumn('SELECT COUNT(*) FROM otp_services WHERE is_active = 1'),
        ];
    }
}
