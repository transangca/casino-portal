<?php
/**
 * Dịch vụ Bank ảo — tích hợp API HeyBank v2 (https://heybank.pro/api/v2).
 *
 * Xác thực bằng ACCESS_KEY (lấy từ bot @WsVenderBot bằng lệnh /mykey).
 * Key được lưu trong bảng `settings` (đã mã hóa AES-256-GCM).
 *
 * Toàn bộ thao tác ghi (tạo / sửa / gia hạn / xoá) đều đi qua đây,
 * sau đó đồng bộ lại bản sao cục bộ `virtual_bank_accounts`.
 */

namespace App\Services;

use App\Core\Crypto;
use App\Core\Database;
use App\Core\Wallet;

class VirtualBankService
{
    public const DEFAULT_BASE_URL = 'https://heybank.pro/api/v2';

    /** Loại ngân hàng hỗ trợ (fallback khi API không trả về) */
    public const BANK_TYPES = [        'MB'             => 'MB Bank',
        'MB-PRO'         => 'MB Bank Priority',
        'MB-EVENT'       => 'MB Bank 30/4',
        'MB-PRO-EVENT'   => 'MB Bank Priority 30/4',
        'VCB'            => 'Vietcombank',
        'VCB-PRO'        => 'Vietcombank Priority',
        'TCB'            => 'Techcombank',
        'TPB'            => 'TPBank',
        'ICB'            => 'Vietinbank',
        'BIDV'           => 'SmartBanking',
        'ACB'            => 'ACB ONE',
    ];

    /** Ngân hàng hỗ trợ tạo thông báo biến động số dư */
    public const NOTIFY_BANKS = ['ACB', 'MB', 'BIDV'];

    /** Key trong phiên hiện tại (dùng cho test / admin, null = đọc từ DB) */
    private static ?string $keyOverride = null;

    /**
     * Đặt key cho phiên hiện tại (không ghi DB) — dùng cho CLI/test.
     */
    public static function useAccessKey(?string $key): void
    {
        self::$keyOverride = ($key === null || trim($key) === '') ? null : trim($key);
    }

    // =================================================================
    //  Cấu hình
    // =================================================================

    public static function baseUrl(): string
    {
        $url = rtrim(trim((string)setting('heybank_base_url', '')), '/');
        if ($url === '') {
            $url = (string)config('heybank.base_url', self::DEFAULT_BASE_URL);
        }
        $url = rtrim(trim($url), '/');

        return $url !== '' ? $url : self::DEFAULT_BASE_URL;
    }

    /**
     * ACCESS_KEY đã giải mã (rỗng nếu chưa cấu hình).
     *
     * Nguồn: bảng `settings` (mã hóa AES-256-GCM) → fallback biến môi trường.
     */
    public static function accessKey(): string
    {
        if (self::$keyOverride !== null) {
            return self::$keyOverride;
        }

        $raw = (string)setting('heybank_access_key', '');
        if ($raw !== '') {
            $dec = Crypto::decrypt($raw);
            $key = $dec ?? $raw;
            if ($key !== '') {
                return $key;
            }
        }

        return trim((string)config('heybank.access_key', ''));
    }

    public static function configured(): bool
    {
        return self::accessKey() !== '';
    }

    public static function enabled(): bool
    {
        $v = (string)setting('heybank_service_on', '');
        if ($v === '') {
            return (bool)config('heybank.service_on', true);
        }
        return $v === '1';
    }

    /**
     * Lưu ACCESS_KEY (mã hóa trước khi ghi DB).
     */
    public static function saveAccessKey(string $key): void
    {
        $key = trim($key);
        $value = $key === '' ? '' : Crypto::encrypt($key);

        setting_set('heybank_access_key', $value, 'Bank ảo', 'password');
    }

    // =================================================================
    //  File / link tải xuống (quản lý bởi admin)
    // =================================================================

    /**
     * Danh sách file/link tải xuống hiển thị ở tab "Tải xuống".
     *
     * @return array<int, array{name:string,bank_code:string,type:string,url:string,note:string}>
     */
    public static function downloadApps(): array
    {
        $raw = json_decode((string)setting('virtual_bank_apps', '[]'), true);
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }
            $out[] = [
                'name'      => (string)($item['name'] ?? ''),
                'bank_code' => (string)($item['bank_code'] ?? ''),
                'type'      => (string)($item['type'] ?? 'apk'),
                'url'       => (string)($item['url'] ?? ''),
                'note'      => (string)($item['note'] ?? ''),
            ];
        }
        return array_values(array_filter($out, static fn($a) => $a['name'] !== ''));
    }

    /**
     * Lưu danh sách file tải xuống.
     */
    public static function saveDownloadApps(array $apps): void
    {
        $clean = [];
        foreach ($apps as $item) {
            if (!is_array($item)) {
                continue;
            }
            $name = trim((string)($item['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $clean[] = [
                'name'      => $name,
                'bank_code' => trim((string)($item['bank_code'] ?? '')),
                'type'      => trim((string)($item['type'] ?? 'apk')) ?: 'apk',
                'url'       => trim((string)($item['url'] ?? '')),
                'note'      => trim((string)($item['note'] ?? '')),
            ];
        }
        setting_set('virtual_bank_apps', json_encode($clean, JSON_UNESCAPED_UNICODE), 'Bank ảo');
    }

    // =================================================================
    //  HTTP
    // =================================================================

    /**
     * Gọi API HeyBank.
     *
     * @return array ['ok'=>bool,'data'=>mixed,'message'=>string,'raw'=>array]
     */
    public static function request(string $method, string $endpoint, array $payload = [], array $query = []): array
    {
        if (!self::configured()) {
            return [
                'ok'      => false,
                'data'    => null,
                'message' => 'Chưa cấu hình ACCESS_KEY cho dịch vụ Bank ảo. Admin nhập tại /admin/virtual-bank.',
                'raw'     => [],
            ];
        }

        $url = self::baseUrl() . '/' . ltrim($endpoint, '/');
        if ($query) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        $headers = [
            'Authorization: Bearer ' . self::accessKey(),
            'Accept: application/json',
        ];

        $opts = ['timeout' => 25, 'retries' => 1, 'allow_error' => true];

        if ($method === 'GET') {
            $body = HttpClient::get($url, $headers, $opts);
        } elseif ($method === 'POST') {
            $body = HttpClient::postJson($url, $payload, $headers, $opts);
        } elseif ($method === 'PUT') {
            $body = HttpClient::putJson($url, $payload, $headers, $opts);
        } elseif ($method === 'DELETE') {
            $body = HttpClient::deleteJson($url, $payload, $headers, $opts);
        } else {
            $body = null;
        }

        if ($body === null) {
            return [
                'ok'      => false,
                'data'    => null,
                'message' => 'Không kết nối được tới API HeyBank. Vui lòng thử lại sau.',
                'raw'     => [],
            ];
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return [
                'ok'      => false,
                'data'    => null,
                'message' => 'API trả về dữ liệu không hợp lệ.',
                'raw'     => [],
            ];
        }

        $ok = (bool)($decoded['ok'] ?? false);
        $msg = (string)($decoded['message'] ?? ($ok ? 'Thành công' : 'Thất bại'));

        return [
            'ok'      => $ok,
            'data'    => $decoded['data'] ?? null,
            'message' => $msg,
            'raw'     => $decoded,
        ];
    }

    // =================================================================
    //  Danh mục & bảng giá
    // =================================================================

    /**
     * Bảng giá thuê fallback (khi API chưa sẵn sàng).
     * Khớp với bảng giá chính thức của HeyBank.
     */
    public const FALLBACK_PRICES = [
        '1hour'   => ['price' => 417,    'label' => '1 giờ'],
        '1day'    => ['price' => 10000,  'label' => '1 ngày'],
        '7days'   => ['price' => 50000,  'label' => '7 ngày'],
        '1week'   => ['price' => 50000,  'label' => '1 tuần'],
        '1month'  => ['price' => 120000, 'label' => '1 tháng'],
        '3months' => ['price' => 300000, 'label' => '3 tháng'],
        '1year'   => ['price' => 1200000,'label' => '1 năm'],
    ];

    /** Cache bảng giá trong request (tránh gọi API lặp) */
    private static ?array $priceCache = null;

    /**
     * Bảng giá thuê (1 giờ / 1 ngày / 1 tháng...).
     */
    public static function rentalPrices(): array
    {
        if (self::$priceCache !== null) {
            return self::$priceCache;
        }

        $res = self::request('GET', '/rental-prices');
        if (!$res['ok'] || !is_array($res['data'])) {
            // Fallback về bảng giá nội bộ
            $out = [];
            foreach (self::FALLBACK_PRICES as $period => $cfg) {
                $out[] = [
                    'value'  => 1,
                    'unit'   => 'day',
                    'price'  => (float)$cfg['price'],
                    'period' => $period,
                    'label'  => $cfg['label'],
                ];
            }
            self::$priceCache = $out;
            return $out;
        }

        $out = [];
        foreach ($res['data'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $value  = (int)($row['value'] ?? 1);
            $unit   = (string)($row['unit'] ?? 'day');
            $price  = (float)($row['price'] ?? 0);
            $out[] = [
                'value'    => $value,
                'unit'     => $unit,
                'price'    => $price,
                'period'   => self::periodKey($value, $unit),
                'label'    => self::periodLabel($value, $unit),
            ];
        }

        self::$priceCache = $out;
        return $out;
    }

    /**
     * Chuẩn hoá period: 1 day → "1day", 7 days → "7days", 3 months → "3months".
     */
    public static function periodKey(int $value, string $unit): string
    {
        $unit = strtolower(trim($unit));
        // Giữ nguyên số nhiều theo đúng định dạng API (days / months)
        $plural = str_ends_with($unit, 's') ? $unit : $unit . 's';

        return $value . $plural;
    }

    public static function periodLabel(int $value, string $unit): string
    {
        $unit = strtolower(trim($unit));
        $map  = [
            'hour'  => 'giờ',
            'hours' => 'giờ',
            'day'   => 'ngày',
            'days'  => 'ngày',
            'week'  => 'tuần',
            'weeks' => 'tuần',
            'month' => 'tháng',
            'months'=> 'tháng',
            'year'  => 'năm',
            'years' => 'năm',
        ];
        return $value . ' ' . ($map[$unit] ?? $unit);
    }

    /**
     * Loại ngân hàng + trạng thái (lấy từ API, fallback nội bộ).
     */
    public static function types(): array
    {
        $res = self::request('GET', '/bank-accounts/types');
        if ($res['ok'] && is_array($res['data'])) {
            $d = $res['data'];
            return [
                'bankTypes'  => $d['bankTypes'] ?? self::BANK_TYPES,
                'bankNames'  => $d['bankNames'] ?? [],
                'statuses'   => $d['statuses'] ?? [],
            ];
        }
        return ['bankTypes' => self::BANK_TYPES, 'bankNames' => [], 'statuses' => []];
    }

    // =================================================================
    //  Tài khoản ngân hàng ảo
    // =================================================================

    /**
     * Danh sách tài khoản từ API (đồng thời đồng bộ về DB).
     */
    public static function syncAccounts(int $userId, string $search = '', int $page = 1, int $limit = 30): array
    {
        $res = self::request('GET', '/bank-accounts', [], array_filter([
            'page'   => $page,
            'limit'  => $limit,
            'search' => $search,
        ]));

        if (!$res['ok']) {
            return ['ok' => false, 'message' => $res['message'], 'rows' => [], 'total' => 0];
        }

        $rows = [];
        $data = $res['data'];
        $list = is_array($data) ? ($data['data'] ?? $data) : [];

        foreach ((array)$list as $row) {
            if (!is_array($row)) {
                continue;
            }
            self::upsertAccount($userId, $row);
            $rows[] = $row;
        }

        $total = (int)($res['raw']['total'] ?? count($rows));

        return ['ok' => true, 'message' => 'Đã đồng bộ.', 'rows' => $rows, 'total' => $total];
    }

    /**
     * Lưu / cập nhật bản sao cục bộ của 1 tài khoản ảo.
     */
    public static function upsertAccount(int $userId, array $row): int
    {
        $remoteId = (int)($row['id'] ?? 0);
        if ($remoteId <= 0) {
            return 0;
        }

        $type = (string)($row['type'] ?? 'MB');
        $name = (string)($row['name'] ?? ($row['bank_name'] ?? ''));
        if ($name === '' && isset(self::BANK_TYPES[$type])) {
            $name = self::BANK_TYPES[$type];
        }

        $data = [
            'user_id'         => $userId,
            'remote_id'       => $remoteId,
            'bank_type'       => $type,
            'bank_name'       => $name !== '' ? $name : null,
            'bank_username'   => (string)($row['bank_username'] ?? $row['bank_user_name'] ?? '') ?: null,
            'phone'           => (string)($row['phone'] ?? '') ?: null,
            'bank_number'     => (string)($row['bank_number'] ?? '') ?: null,
            'account_balance' => (float)($row['account_balance'] ?? 0),
            'avatar'          => self::absoluteAvatar((string)($row['avatar'] ?? '')) ?: null,
            'status'          => (int)($row['status'] ?? 1),
            'expired_at'      => self::toDatetime((string)($row['expired_at'] ?? '')) ?: null,
        ];

        $exists = Database::fetch(
            'SELECT id FROM virtual_bank_accounts WHERE remote_id = ?',
            [$remoteId]
        );

        if ($exists) {
            Database::update(
                'virtual_bank_accounts',
                $data,
                'id = :id',
                ['id' => (int)$exists['id']]
            );
            return (int)$exists['id'];
        }

        $id = Database::insert('virtual_bank_accounts', $data);
        return $id;
    }

    /**
     * Chi tiết 1 tài khoản (kèm mật khẩu/OTP nếu API trả về).
     */
    public static function accountDetail(int $remoteId, int $userId = 0): array
    {
        $res = self::request('GET', '/bank-accounts/get_detail', [], ['bank_account_id' => $remoteId]);
        if (!$res['ok']) {
            return ['ok' => false, 'message' => $res['message'], 'data' => null];
        }

        $data = is_array($res['data']) ? $res['data'] : [];
        if ($userId > 0) {
            $localId = self::upsertAccount($userId, $data);
            // Lưu mật khẩu / OTP dạng mã hóa
            if ($localId > 0) {
                $secret = [];
                if (!empty($data['password_decryption'])) {
                    $secret['password_enc'] = Crypto::encrypt((string)$data['password_decryption']);
                }
                if (!empty($data['password_level_two'])) {
                    $secret['otp_enc'] = Crypto::encrypt((string)$data['password_level_two']);
                }
                if ($secret) {
                    Database::update('virtual_bank_accounts', $secret, 'id = :id', ['id' => $localId]);
                }
            }
        }

        return ['ok' => true, 'message' => $res['message'], 'data' => $data];
    }

    /**
     * Tạo tài khoản ngân hàng ảo (đã trừ tiền ví).
     */
    public static function createAccount(int $userId, array $data): array
    {
        if (!self::configured()) {
            return ['ok' => false, 'message' => 'Dịch vụ Bank ảo chưa được cấu hình ACCESS_KEY. Vui lòng liên hệ quản trị viên.'];
        }

        $type = strtoupper(trim((string)($data['type'] ?? 'MB')));
        if (!isset(self::BANK_TYPES[$type])) {
            return ['ok' => false, 'message' => 'Loại ngân hàng không hợp lệ.'];
        }

        $phone = trim((string)($data['phone'] ?? ''));
        $number = trim((string)($data['bank_number'] ?? ''));
        $holder = trim((string)($data['bank_username'] ?? ''));
        $password = (string)($data['password'] ?? '');
        $otp = trim((string)($data['otp'] ?? ''));

        if ($phone === '' || $number === '' || $holder === '' || $password === '') {
            return ['ok' => false, 'message' => 'Vui lòng nhập đầy đủ số điện thoại, số tài khoản, chủ tài khoản và mật khẩu.'];
        }

        $period = trim((string)($data['expired_period'] ?? '1day'));
        $price  = self::priceOfPeriod($period);
        if ($price <= 0) {
            return ['ok' => false, 'message' => 'Chu kỳ thuê không hợp lệ.'];
        }

        $balance = (float)Database::fetchColumn('SELECT balance FROM users WHERE id = ?', [$userId]);
        if ($balance < $price) {
            return ['ok' => false, 'message' => 'Số dư không đủ. Cần ' . money($price) . ', hiện có ' . money($balance) . '.'];
        }

        // Trừ tiền ví trước (hoàn lại nếu API lỗi)
        Wallet::apply($userId, 'adjustment', -$price, 'Thuê tài khoản bank ảo ' . $type, 'virtual_bank', $period);

        $payload = [
            'phone'           => $phone,
            'bank_number'     => $number,
            'bank_username'   => $holder,
            'password'        => $password,
            'otp'             => $otp,
            'type'            => $type,
            'account_balance' => (float)($data['account_balance'] ?? 0),
            'expired_period'  => $period,
        ];

        $res = self::request('POST', '/bank-accounts/create', $payload);

        if (!$res['ok']) {
            Wallet::apply($userId, 'adjustment', $price, 'Hoàn tiền thuê bank ảo thất bại', 'virtual_bank_refund', $period);
            self::logOrder($userId, 0, 'create', $period, $price, 'failed', $res['message']);
            return ['ok' => false, 'message' => $res['message']];
        }

        $row = is_array($res['data']) ? $res['data'] : [];
        $localId = self::upsertAccount($userId, $row + ['id' => $row['id'] ?? 0, 'type' => $type]);

        if ($localId > 0) {
            Database::update('virtual_bank_accounts', [
                'password_enc' => Crypto::encrypt($password),
                'otp_enc'      => $otp !== '' ? Crypto::encrypt($otp) : null,
            ], 'id = :id', ['id' => $localId]);
        }

        self::logOrder($userId, $localId, 'create', $period, $price, 'success', $res['message']);

        return [
            'ok'      => true,
            'message' => $res['message'] ?: 'Đã tạo tài khoản ngân hàng ảo.',
            'data'    => $row,
            'id'      => $localId,
        ];
    }

    /**
     * Gia hạn tài khoản (đã trừ tiền ví).
     */
    public static function extendAccount(int $userId, int $localId, string $period): array
    {
        if (!self::configured()) {
            return ['ok' => false, 'message' => 'Dịch vụ Bank ảo chưa được cấu hình ACCESS_KEY. Vui lòng liên hệ quản trị viên.'];
        }

        $acc = Database::fetch('SELECT * FROM virtual_bank_accounts WHERE id = ? AND user_id = ?', [$localId, $userId]);
        if (!$acc || !$acc['remote_id']) {
            return ['ok' => false, 'message' => 'Không tìm thấy tài khoản.'];
        }

        $price = self::priceOfPeriod($period);
        if ($price <= 0) {
            return ['ok' => false, 'message' => 'Chu kỳ gia hạn không hợp lệ.'];
        }

        $balance = (float)Database::fetchColumn('SELECT balance FROM users WHERE id = ?', [$userId]);
        if ($balance < $price) {
            return ['ok' => false, 'message' => 'Số dư không đủ để gia hạn. Cần ' . money($price) . '.'];
        }

        Wallet::apply($userId, 'adjustment', -$price, 'Gia hạn bank ảo #' . $localId, 'virtual_bank', (string)$period);

        $res = self::request('POST', '/bank-accounts', [
            'bank_account_id' => (int)$acc['remote_id'],
            'extend_period'   => $period,
        ]);

        if (!$res['ok']) {
            Wallet::apply($userId, 'adjustment', $price, 'Hoàn tiền gia hạn bank ảo thất bại', 'virtual_bank_refund', (string)$period);
            self::logOrder($userId, $localId, 'extend', $period, $price, 'failed', $res['message']);
            return ['ok' => false, 'message' => $res['message']];
        }

        $data = is_array($res['data']) ? $res['data'] : [];
        if (!empty($data['expired_at'])) {
            Database::update('virtual_bank_accounts', [
                'expired_at' => self::toDatetime((string)$data['expired_at']),
            ], 'id = :id', ['id' => $localId]);
        }

        self::logOrder($userId, $localId, 'extend', $period, $price, 'success', $res['message']);

        return ['ok' => true, 'message' => $res['message'] ?: 'Đã gia hạn thành công.', 'data' => $data];
    }

    /**
     * Cập nhật thông tin tài khoản (số dư, tên, số TK...).
     */
    public static function updateAccount(int $userId, int $localId, array $data): array
    {
        $acc = Database::fetch('SELECT * FROM virtual_bank_accounts WHERE id = ? AND user_id = ?', [$localId, $userId]);
        if (!$acc || !$acc['remote_id']) {
            return ['ok' => false, 'message' => 'Không tìm thấy tài khoản.'];
        }

        $payload = ['bank_account_id' => (int)$acc['remote_id']];

        if (isset($data['bank_username'])) {
            $payload['bank_username'] = trim((string)$data['bank_username']);
        }
        if (isset($data['bank_number'])) {
            $payload['bank_number'] = trim((string)$data['bank_number']);
        }
        if (isset($data['account_balance'])) {
            $payload['account_balance'] = (float)$data['account_balance'];
        }
        if (isset($data['password_decryption'])) {
            $payload['password_decryption'] = (string)$data['password_decryption'];
        }
        if (isset($data['password_level_two'])) {
            $payload['password_level_two'] = (string)$data['password_level_two'];
        }

        $res = self::request('POST', '/bank-accounts', $payload);
        if (!$res['ok']) {
            return ['ok' => false, 'message' => $res['message']];
        }

        $local = [];
        if (isset($data['bank_username'])) {
            $local['bank_username'] = $payload['bank_username'];
        }
        if (isset($data['bank_number'])) {
            $local['bank_number'] = $payload['bank_number'];
        }
        if (isset($data['account_balance'])) {
            $local['account_balance'] = $payload['account_balance'];
        }
        if (!empty($data['password_decryption'])) {
            $local['password_enc'] = Crypto::encrypt((string)$data['password_decryption']);
        }
        if (!empty($data['password_level_two'])) {
            $local['otp_enc'] = Crypto::encrypt((string)$data['password_level_two']);
        }
        if ($local) {
            Database::update('virtual_bank_accounts', $local, 'id = :id', ['id' => $localId]);
        }

        return ['ok' => true, 'message' => $res['message'] ?: 'Đã cập nhật tài khoản.'];
    }

    /**
     * Tạo thông báo biến động số dư (ảo).
     */
    public static function createNotification(int $userId, int $localId, array $data): array
    {
        $acc = Database::fetch('SELECT * FROM virtual_bank_accounts WHERE id = ? AND user_id = ?', [$localId, $userId]);
        if (!$acc || !$acc['remote_id']) {
            return ['ok' => false, 'message' => 'Không tìm thấy tài khoản.'];
        }

        $amount = (float)($data['amount'] ?? 0);
        $senderName = trim((string)($data['sender_name'] ?? ''));
        $senderAcc = trim((string)($data['sender_account'] ?? ''));
        $note = trim((string)($data['note'] ?? ''));

        if ($amount <= 0 || $senderName === '' || $senderAcc === '' || $note === '') {
            return ['ok' => false, 'message' => 'Vui lòng nhập số tiền, tên người gửi, số tài khoản và nội dung.'];
        }

        $res = self::request('POST', '/bank-accounts', [
            'bank_account_id'     => (int)$acc['remote_id'],
            'create_notification' => true,
            'amount'              => $amount,
            'sender_name'         => $senderName,
            'sender_account'      => $senderAcc,
            'note'                => $note,
        ]);

        if (!$res['ok']) {
            return ['ok' => false, 'message' => $res['message']];
        }

        $out = is_array($res['data']) ? $res['data'] : [];
        if (isset($out['new_balance'])) {
            Database::update('virtual_bank_accounts', [
                'account_balance' => (float)$out['new_balance'],
            ], 'id = :id', ['id' => $localId]);
        }

        // Ghi vào sổ giao dịch cục bộ
        Database::insert('virtual_bank_transactions', [
            'user_id'                => $userId,
            'account_id'             => $localId,
            'remote_id'              => (int)$acc['remote_id'],
            'transaction_code'       => (string)($out['transaction_id'] ?? ''),
            'amount'                 => $amount,
            'from_name'              => $senderName,
            'from_number'            => $senderAcc,
            'recipient_name'         => (string)$acc['bank_username'],
            'recipient_account_number' => (string)$acc['bank_number'],
            'bank_name'              => (string)$acc['bank_name'],
            'bank_code_id'           => (string)$acc['bank_type'],
            'type'                   => 'RECEIVE',
            'note'                   => $note,
            'transaction_note'       => $note,
        ]);

        return ['ok' => true, 'message' => $res['message'] ?: 'Đã tạo thông báo biến động số dư.', 'data' => $out];
    }

    /**
     * Xoá tài khoản ảo.
     */
    public static function deleteAccount(int $userId, int $localId): array
    {
        $acc = Database::fetch('SELECT * FROM virtual_bank_accounts WHERE id = ? AND user_id = ?', [$localId, $userId]);
        if (!$acc || !$acc['remote_id']) {
            return ['ok' => false, 'message' => 'Không tìm thấy tài khoản.'];
        }

        $res = self::request('DELETE', '/bank-accounts', [
            'bank_account_id' => (int)$acc['remote_id'],
        ]);

        if (!$res['ok']) {
            return ['ok' => false, 'message' => $res['message']];
        }

        Database::query(
            'DELETE FROM virtual_bank_accounts WHERE id = ? AND user_id = ?',
            [$localId, $userId]
        );

        return ['ok' => true, 'message' => $res['message'] ?: 'Đã xoá tài khoản.'];
    }

    /**
     * Upload avatar cho tài khoản ảo.
     */
    public static function uploadAvatar(int $userId, int $localId, array $file): array
    {
        $acc = Database::fetch('SELECT * FROM virtual_bank_accounts WHERE id = ? AND user_id = ?', [$localId, $userId]);
        if (!$acc || !$acc['remote_id']) {
            return ['ok' => false, 'message' => 'Không tìm thấy tài khoản.'];
        }

        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['ok' => false, 'message' => 'Vui lòng chọn ảnh.'];
        }

        $mime = (string)($file['mime'] ?? '');
        if (!str_starts_with($mime, 'image/')) {
            return ['ok' => false, 'message' => 'Chỉ chấp nhận file ảnh.'];
        }
        if ((int)($file['size'] ?? 0) > 3 * 1024 * 1024) {
            return ['ok' => false, 'message' => 'Ảnh vượt quá 3MB.'];
        }

        $res = self::requestMultipart(
            '/bank-accounts',
            ['bank_account_id' => (int)$acc['remote_id']],
            ['avatar' => $file]
        );

        if (!$res['ok']) {
            return ['ok' => false, 'message' => $res['message']];
        }

        $data = is_array($res['data']) ? $res['data'] : [];
        $url  = self::absoluteAvatar((string)($data['avatar'] ?? ''));
        if ($url !== '') {
            Database::update('virtual_bank_accounts', ['avatar' => $url], 'id = :id', ['id' => $localId]);
        }

        return ['ok' => true, 'message' => $res['message'] ?: 'Đã cập nhật ảnh đại diện.', 'data' => $data];
    }

    /**
     * Xoá avatar.
     */
    public static function removeAvatar(int $userId, int $localId): array
    {
        $acc = Database::fetch('SELECT * FROM virtual_bank_accounts WHERE id = ? AND user_id = ?', [$localId, $userId]);
        if (!$acc || !$acc['remote_id']) {
            return ['ok' => false, 'message' => 'Không tìm thấy tài khoản.'];
        }

        $res = self::request('POST', '/bank-accounts', [
            'bank_account_id' => (int)$acc['remote_id'],
            'remove_avatar'   => true,
        ]);

        if (!$res['ok']) {
            return ['ok' => false, 'message' => $res['message']];
        }

        Database::update('virtual_bank_accounts', ['avatar' => null], 'id = :id', ['id' => $localId]);

        return ['ok' => true, 'message' => $res['message'] ?: 'Đã xoá ảnh đại diện.'];
    }

    // =================================================================
    //  Giao dịch
    // =================================================================

    /**
     * Lấy giao dịch từ API + đồng bộ về DB.
     */
    public static function syncTransactions(int $userId, string $search = '', string $type = '', int $limit = 30): array
    {
        $res = self::request('GET', '/transactions', [], array_filter([
            'limit'  => $limit,
            'search' => $search,
            'type'   => $type,
        ]));

        if (!$res['ok']) {
            return ['ok' => false, 'message' => $res['message'], 'rows' => []];
        }

        $list = $res['data'];
        if (is_array($list) && isset($list['data'])) {
            $list = $list['data'];
        }

        $rows = [];
        foreach ((array)$list as $row) {
            if (!is_array($row)) {
                continue;
            }
            $accountId = 0;
            if (!empty($row['user_bank_account_id'])) {
                $local = Database::fetch(
                    'SELECT id FROM virtual_bank_accounts WHERE remote_id = ?',
                    [(int)$row['user_bank_account_id']]
                );
                $accountId = $local ? (int)$local['id'] : 0;
            }

            Database::insert('virtual_bank_transactions', [
                'user_id'      => $userId,
                'account_id'   => $accountId ?: null,
                'remote_id'    => (int)($row['user_bank_account_id'] ?? 0) ?: null,
                'transaction_code' => (string)($row['transaction_code'] ?? ''),
                'amount'       => (float)($row['amount'] ?? 0),
                'from_name'    => (string)($row['from_name'] ?? ''),
                'from_number'  => (string)($row['from_number'] ?? ''),
                'recipient_name' => (string)($row['recipient_name'] ?? ''),
                'recipient_account_number' => (string)($row['recipient_account_number'] ?? ''),
                'bank_name'    => (string)($row['bank_name'] ?? ''),
                'bank_code_id' => (string)($row['bank_code_id'] ?? ''),
                'type'         => (string)($row['type'] ?? 'RECEIVE'),
                'note'         => (string)($row['note'] ?? ''),
                'transaction_note' => (string)($row['transaction_note'] ?? ''),
                'created_at'   => self::toDatetime((string)($row['created_at'] ?? '')),
            ]);

            $rows[] = $row;
        }

        return ['ok' => true, 'message' => 'Đã đồng bộ giao dịch.', 'rows' => $rows];
    }

    /**
     * Sửa giao dịch (theo id của API).
     */
    public static function updateTransaction(int $remoteId, array $data): array
    {
        $payload = [];
        foreach (['created_at', 'transaction_code', 'amount', 'from_name', 'recipient_name', 'recipient_account_number', 'bank_code_id', 'transaction_note'] as $k) {
            if (isset($data[$k])) {
                $payload[$k] = $data[$k];
            }
        }

        $res = self::request('PUT', '/transactions/' . $remoteId, $payload);
        if (!$res['ok']) {
            return ['ok' => false, 'message' => $res['message']];
        }
        return ['ok' => true, 'message' => $res['message'] ?: 'Đã cập nhật giao dịch.', 'data' => $res['data']];
    }

    /**
     * Giao dịch đã lưu cục bộ.
     */
    public static function localTransactions(int $userId, int $limit = 50): array
    {
        return Database::fetchAll(
            'SELECT t.*, a.bank_type, a.bank_username, a.bank_number
             FROM virtual_bank_transactions t
             LEFT JOIN virtual_bank_accounts a ON a.id = t.account_id
             WHERE t.user_id = ? ORDER BY t.id DESC LIMIT ' . (int)$limit,
            [$userId]
        );
    }

    // =================================================================
    //  Bản sao cục bộ
    // =================================================================

    public static function myAccounts(int $userId, int $limit = 100): array
    {
        return Database::fetchAll(
            'SELECT * FROM virtual_bank_accounts WHERE user_id = ? ORDER BY expired_at IS NULL, expired_at ASC, id DESC LIMIT ' . (int)$limit,
            [$userId]
        );
    }

    public static function findAccount(int $userId, int $localId): ?array
    {
        return Database::fetch(
            'SELECT * FROM virtual_bank_accounts WHERE id = ? AND user_id = ?',
            [$localId, $userId]
        );
    }

    /**
     * Giải mã mật khẩu / OTP đã lưu.
     */
    public static function revealSecret(?string $encrypted): ?string
    {
        if ($encrypted === null || $encrypted === '') {
            return null;
        }
        return Crypto::decrypt($encrypted);
    }

    public static function myOrders(int $userId, int $limit = 30): array
    {
        return Database::fetchAll(
            'SELECT * FROM virtual_bank_orders WHERE user_id = ? ORDER BY id DESC LIMIT ' . (int)$limit,
            [$userId]
        );
    }

    /**
     * Đã đồng bộ tự động gần đây chưa (giới hạn 1 lần / 5 phút).
     */
    public static function shouldAutoSync(int $userId): bool
    {
        $last = (int)self::cacheGet('vbank_sync_' . $userId);
        return (time() - $last) > 300;
    }

    /**
     * Ghi nhận mốc đồng bộ gần nhất.
     */
    public static function markAutoSync(int $userId): void
    {
        self::cacheSet('vbank_sync_' . $userId, (string)time(), 3600);
    }

    /**
     * Đọc cache đơn giản (bảng cache_store).
     */
    public static function cacheGet(string $key): ?string
    {
        $row = Database::fetch(
            'SELECT cache_value FROM cache_store WHERE cache_key = ? AND (expires_at IS NULL OR expires_at > NOW())',
            [$key]
        );
        return $row ? (string)$row['cache_value'] : null;
    }

    /**
     * Ghi cache đơn giản (bảng cache_store).
     */
    public static function cacheSet(string $key, string $value, int $ttl = 3600): void
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

    public static function allAccounts(int $limit = 200): array
    {
        return Database::fetchAll(
            'SELECT a.*, u.username FROM virtual_bank_accounts a
             LEFT JOIN users u ON u.id = a.user_id
             ORDER BY a.id DESC LIMIT ' . (int)$limit
        );
    }

    public static function stats(): array
    {
        return [
            'total'    => (int)Database::fetchColumn('SELECT COUNT(*) FROM virtual_bank_accounts'),
            'active'   => (int)Database::fetchColumn('SELECT COUNT(*) FROM virtual_bank_accounts WHERE status = 1'),
            'expired'  => (int)Database::fetchColumn('SELECT COUNT(*) FROM virtual_bank_accounts WHERE expired_at IS NOT NULL AND expired_at < NOW()'),
            'revenue'  => (float)Database::fetchColumn('SELECT COALESCE(SUM(price),0) FROM virtual_bank_orders WHERE status = "success"'),
            'orders'   => (int)Database::fetchColumn('SELECT COUNT(*) FROM virtual_bank_orders'),
        ];
    }

    // =================================================================
    //  Helper
    // =================================================================

    private static function logOrder(
        int $userId,
        int $accountId,
        string $action,
        string $period,
        float $price,
        string $status,
        string $message
    ): void {
        Database::insert('virtual_bank_orders', [
            'user_id'    => $userId,
            'account_id' => $accountId ?: null,
            'action'     => $action,
            'period'     => $period,
            'price'      => $price,
            'status'     => $status,
            'message'    => mb_substr($message, 0, 255),
        ]);
    }

    /**
     * Giá thuê theo chu kỳ (dùng bảng giá từ API, fallback tính tay khi API lỗi).
     *
     * Đơn giá gốc theo bảng giá HeyBank:
     *   1 ngày = 10.000 · 7 ngày = 50.000 · 1 tháng = 120.000 · 3 tháng = 300.000
     *   1 giờ được API tính theo tỉ lệ 1/24 của 1 ngày.
     */
    public static function priceOfPeriod(string $period): float
    {
        $period = trim($period);
        if ($period === '') {
            return 0.0;
        }

        // 1. Ưu tiên bảng giá chính thức từ API
        foreach (self::rentalPrices() as $p) {
            if ($p['period'] === $period) {
                return (float)$p['price'];
            }
        }

        // 2. Fallback: suy ra giá theo chu kỳ
        if (!preg_match('/^(\d+)(hours?|days?|weeks?|months?|years?)$/', $period, $m)) {
            return 0.0;
        }

        $n    = (int)$m[1];
        $unit = rtrim(strtolower($m[2]), 's');

        // Giá theo "số đơn vị cơ sở"
        $unitPrice = [
            'hour'  => 10000 / 24,   // 1 giờ ≈ 417 đ
            'day'   => 10000,
            'week'  => 50000,
            'month' => 120000,
            'year'  => 1200000,
        ][$unit] ?? 0;

        if ($unitPrice <= 0) {
            return 0.0;
        }

        return (int)ceil($unitPrice * $n);
    }

    /**
     * Đường dẫn avatar tương đối → URL tuyệt đối.
     */
    public static function absoluteAvatar(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        return 'https://heybank.pro/' . ltrim($path, '/');
    }

    /**
     * Chuẩn hoá chuỗi ngày giờ của API về DATETIME.
     */
    public static function toDatetime(string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || $value === '0000-00-00 00:00:00') {
            return null;
        }
        $ts = strtotime($value);
        if ($ts === false) {
            return null;
        }
        return date('Y-m-d H:i:s', $ts);
    }

    /**
     * Gọi API dạng multipart/form-data (upload avatar).
     */
    private static function requestMultipart(string $endpoint, array $fields, array $files): array
    {
        $key = self::accessKey();
        if ($key === '') {
            return ['ok' => false, 'data' => null, 'message' => 'Chưa cấu hình ACCESS_KEY.', 'raw' => []];
        }

        $url = self::baseUrl() . '/' . ltrim($endpoint, '/');
        $payload = $fields;
        foreach ($files as $name => $file) {
            $payload[$name] = new \CURLFile(
                $file['tmp_name'],
                $file['mime'] ?? 'application/octet-stream',
                $file['name'] ?? 'upload'
            );
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $key,
                'Accept: application/json',
            ],
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($body === false || $code < 200 || $code >= 400) {
            return [
                'ok'      => false,
                'data'    => null,
                'message' => 'Upload không thành công (HTTP ' . $code . ($err ? ': ' . $err : '') . ').',
                'raw'     => [],
            ];
        }

        $decoded = json_decode((string)$body, true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'data' => null, 'message' => 'Phản hồi không hợp lệ.', 'raw' => []];
        }

        return [
            'ok'      => (bool)($decoded['ok'] ?? false),
            'data'    => $decoded['data'] ?? null,
            'message' => (string)($decoded['message'] ?? ''),
            'raw'     => $decoded,
        ];
    }
}
