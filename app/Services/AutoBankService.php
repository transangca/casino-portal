<?php
/**
 * Dịch vụ Auto Bank — cổng thanh toán tự động (https://api.sieuthicode.net/).
 *
 * API cung cấp 2 phiên bản:
 *   - V1: dữ liệu gốc từ ngân hàng (chỉ cần TOKEN)
 *   - V3: dữ liệu chuẩn hoá (cần PASSWORD / STK / TOKEN)
 *
 * Mỗi endpoint có "shape" riêng → hàm normalize() đưa về cấu trúc thống nhất:
 *   transactionID, amount, description, transactionDate, type (IN/OUT), balance.
 *
 * Token / mật khẩu ngân hàng được mã hoá AES-256-GCM trước khi lưu DB.
 */

namespace App\Services;

use App\Core\Crypto;
use App\Core\Database;
use App\Core\Wallet;

class AutoBankService
{
    public const DEFAULT_BASE_URL = 'https://api.sieuthicode.net';

    /**
     * Registry các cổng API theo ngân hàng.
     *
     * Mỗi phiên bản:
     *   path   → đường dẫn endpoint (không kèm dấu /)
     *   params → các tham số bắt buộc theo thứ tự: token | password+stk+token
     *   shape  → bộ tách dữ liệu tương ứng
     *
     * @return array<string, array{label:string, icon:string, versions:array}>
     */
    public const BANKS = [
        'tpbank' => [
            'label' => 'TPBank',
            'icon'  => 'fa-solid fa-building-columns',
            'versions' => [
                'v1' => ['path' => 'historyapitpb',       'params' => ['token'],                    'shape' => 'tpbank_v1'],
                'v3' => ['path' => 'historyapitpbv3',    'params' => ['password', 'stk', 'token'], 'shape' => 'standard'],
            ],
        ],
        'vcb' => [
            'label' => 'Vietcombank',
            'icon'  => 'fa-solid fa-building-columns',
            'versions' => [
                'v1' => ['path' => 'historyapivcb',       'params' => ['token'],                    'shape' => 'vcb_v1'],
                'v3' => ['path' => 'historyapivcbv3',     'params' => ['password', 'stk', 'token'], 'shape' => 'standard'],
            ],
        ],
        'vcbbiz' => [
            'label' => 'Vietcombank Digibiz',
            'icon'  => 'fa-solid fa-building-columns',
            'versions' => [
                'v1' => ['path' => 'historyapivcbbiz',    'params' => ['token'],                    'shape' => 'vcbbiz_v1'],
                'v2' => ['path' => 'historyapivcbbizv2',  'params' => ['token'],                    'shape' => 'standard'],
                'v3' => ['path' => 'historyapivcbbizv3',  'params' => ['password', 'stk', 'token'], 'shape' => 'standard'],
            ],
        ],
        'acb' => [
            'label' => 'ACB',
            'icon'  => 'fa-solid fa-building-columns',
            'versions' => [
                'v1' => ['path' => 'historyapiacb',       'params' => ['token'],                    'shape' => 'acb_v1'],
                'v3' => ['path' => 'historyapiacbv3',     'params' => ['password', 'stk', 'token'], 'shape' => 'standard'],
            ],
        ],
        'acbweb' => [
            'label' => 'ACB (Internet Banking)',
            'icon'  => 'fa-solid fa-building-columns',
            'versions' => [
                'v1' => ['path' => 'historyapiacbweb',    'params' => ['token'],                    'shape' => 'acbweb_v1'],
                'v3' => ['path' => 'historyapiacbwebv3',  'params' => ['password', 'stk', 'token'], 'shape' => 'standard'],
            ],
        ],
        'mbbank' => [
            'label' => 'MB Bank',
            'icon'  => 'fa-solid fa-building-columns',
            'versions' => [
                'v1' => ['path' => 'historyapimbbank',    'params' => ['token'],                    'shape' => 'mbbank_v1'],
                'v3' => ['path' => 'historyapimbv3',      'params' => ['password', 'stk', 'token'], 'shape' => 'standard'],
            ],
        ],
        'bidv' => [
            'label' => 'BIDV',
            'icon'  => 'fa-solid fa-building-columns',
            'versions' => [
                'v1' => ['path' => 'historyapibidv',      'params' => ['token'],                    'shape' => 'bidv_v1'],
                'v3' => ['path' => 'historyapibidvv3',    'params' => ['password', 'stk', 'token'], 'shape' => 'standard'],
            ],
        ],
        'viettin' => [
            'label' => 'VietinBank',
            'icon'  => 'fa-solid fa-building-columns',
            'versions' => [
                'v1' => ['path' => 'historyapiviettin',   'params' => ['token'],                    'shape' => 'viettin_v1'],
                'v3' => ['path' => 'historyapiviettinv3', 'params' => ['password', 'stk', 'token'], 'shape' => 'standard'],
            ],
        ],
        'seabank' => [
            'label' => 'SeABank',
            'icon'  => 'fa-solid fa-building-columns',
            'versions' => [
                'v1' => ['path' => 'historyapiseabank',   'params' => ['token'],                    'shape' => 'seabank_v1'],
                'v3' => ['path' => 'historyapiseabankv3', 'params' => ['password', 'stk', 'token'], 'shape' => 'standard'],
            ],
        ],
        'msb' => [
            'label' => 'MSB',
            'icon'  => 'fa-solid fa-building-columns',
            'versions' => [
                'v1' => ['path' => 'historyapimsb',       'params' => ['token'],                    'shape' => 'msb_v1'],
                'v3' => ['path' => 'historyapimsbv3',     'params' => ['password', 'stk', 'token'], 'shape' => 'standard'],
            ],
        ],
        'techcom' => [
            'label' => 'Techcombank',
            'icon'  => 'fa-solid fa-building-columns',
            'versions' => [
                'v1' => ['path' => 'historyapitcb',       'params' => ['token'],                    'shape' => 'tcb_v1'],
            ],
        ],
        'vpbank' => [
            'label' => 'VPBank',
            'icon'  => 'fa-solid fa-building-columns',
            'versions' => [
                'v1' => ['path' => 'historyapivpbank',    'params' => ['token'],                    'shape' => 'vpbank_v1'],
            ],
        ],
        'vietabank' => [
            'label' => 'VietABank',
            'icon'  => 'fa-solid fa-building-columns',
            'versions' => [
                'v1' => ['path' => 'historyapivietabank', 'params' => ['token'],                    'shape' => 'vietabank_v1'],
            ],
        ],
        'lpbank' => [
            'label' => 'LPBank',
            'icon'  => 'fa-solid fa-building-columns',
            'versions' => [
                'v1' => ['path' => 'historyapilpbank',    'params' => ['token'],                    'shape' => 'lpbank_v1'],
                'v3' => ['path' => 'historyapilpbankv3',  'params' => ['password', 'stk', 'token'], 'shape' => 'standard'],
            ],
        ],
        'timo' => [
            'label' => 'Timo',
            'icon'  => 'fa-solid fa-building-columns',
            'versions' => [
                'v1' => ['path' => 'historyapitimo',      'params' => ['token'],                    'shape' => 'timo_v1'],
                'v3' => ['path' => 'historyapitimov3',    'params' => ['password', 'stk', 'token'], 'shape' => 'standard'],
            ],
        ],
        'ocb' => [
            'label' => 'OCB',
            'icon'  => 'fa-solid fa-building-columns',
            'versions' => [
                'v1' => ['path' => 'historyapiocbv2',     'params' => ['token'],                    'shape' => 'ocb_v1'],
            ],
        ],
        'hdbank' => [
            'label' => 'HDBank',
            'icon'  => 'fa-solid fa-building-columns',
            'versions' => [
                'v1' => ['path' => 'historyapihdbank',    'params' => ['token'],                    'shape' => 'hdbank_v1'],
                'v2' => ['path' => 'historyapihdbankv2',  'params' => ['token'],                    'shape' => 'standard'],
            ],
        ],
        'pgbank' => [
            'label' => 'PGBank',
            'icon'  => 'fa-solid fa-building-columns',
            'versions' => [
                'v1' => ['path' => 'historyapipgbank',    'params' => ['token'],                    'shape' => 'pgbank_v1'],
                'v2' => ['path' => 'historyapipgbankv2',  'params' => ['token'],                    'shape' => 'standard'],
            ],
        ],
        'eximbank' => [
            'label' => 'Eximbank',
            'icon'  => 'fa-solid fa-building-columns',
            'versions' => [
                'v1' => ['path' => 'historyapieximbank',   'params' => ['token'],                    'shape' => 'eximbank_v1'],
                'v2' => ['path' => 'historyapieximbankv2', 'params' => ['token'],                    'shape' => 'standard'],
                'v3' => ['path' => 'historyapieximbankv3', 'params' => ['password', 'stk', 'token'], 'shape' => 'standard'],
            ],
        ],
        'viettelmoney' => [
            'label' => 'Viettel Money',
            'icon'  => 'fa-solid fa-mobile-screen',
            'versions' => [
                'v1' => ['path' => 'historyapiviettel',     'params' => ['token'], 'shape' => 'viettel_money'],
                'v2' => ['path' => 'historyapiviettelnoti', 'params' => ['token'], 'shape' => 'viettel_noti'],
            ],
        ],
        'thesieure' => [
            'label' => 'TheSieuRe',
            'icon'  => 'fa-solid fa-building-columns',
            'versions' => [
                'v1' => ['path' => 'historyapithesieure', 'params' => ['token'], 'shape' => 'thesieure_v1'],
            ],
        ],
        'binance' => [
            'label' => 'Binance Pay',
            'icon'  => 'fa-brands fa-bitcoin',
            'versions' => [
                'v1' => ['path' => 'historyapibinance',   'params' => ['token'], 'shape' => 'binance_v1'],
                'v2' => ['path' => 'historyapibinancev2', 'params' => ['token'], 'shape' => 'standard'],
            ],
        ],
        'vpbankshare' => [
            'label' => 'VPBank Shared',
            'icon'  => 'fa-solid fa-share-nodes',
            'versions' => [
                'v1' => ['path' => 'historyapivpbankshare', 'params' => ['token'], 'shape' => 'standard'],
            ],
        ],
        'acbshare' => [
            'label' => 'ACB Shared',
            'icon'  => 'fa-solid fa-share-nodes',
            'versions' => [
                'v1' => ['path' => 'historyapiacbshare', 'params' => ['token'], 'shape' => 'standard'],
            ],
        ],
        'cake' => [
            'label' => 'Cake by VPBank',
            'icon'  => 'fa-solid fa-cake-candles',
            'versions' => [
                'v1' => ['path' => 'historyapicake', 'params' => ['token'], 'shape' => 'standard'],
            ],
        ],
    ];

    /**
     * Tên bank trong endpoint sandbox (/sandbox/{slug}/...).
     * Khác với key nội bộ ở một số ngân hàng.
     */
    public const SANDBOX_SLUGS = [
        'tpbank'       => 'tpbank',
        'vcb'          => 'vcb',
        'vcbbiz'       => 'vcb',
        'acb'          => 'acb',
        'acbweb'       => 'acb',
        'mbbank'       => 'mb',
        'bidv'         => 'bidv',
        'viettin'      => 'viettin',
        'seabank'      => 'seabank',
        'msb'          => 'msb',
        'techcom'      => 'tcb',
        'vpbank'       => 'vpbank',
        'vietabank'    => 'vietabank',
        'lpbank'       => 'lpbank',
        'timo'         => 'timo',
        'hdbank'       => 'hdbank',
        'pgbank'       => 'pgbank',
        'eximbank'     => 'eximbank',
        'viettelmoney' => 'viettel',
        'thesieure'    => 'thesieure',
    ];

    /**
     * Sandbox hỗ trợ các bank sau (theo tài liệu Playground).
     */
    public const SANDBOX_SUPPORTED = [
        'tpbank', 'vcb', 'vcbbiz', 'acb', 'acbweb', 'mbbank', 'bidv', 'viettin',
        'seabank', 'msb', 'techcom', 'vpbank', 'vietabank', 'lpbank', 'timo',
        'hdbank', 'pgbank', 'viettelmoney', 'thesieure',
    ];

    /** Thông tin các phiên bản hỗ trợ */
    public const VERSIONS = [
        'v1' => 'V1 — Dữ liệu gốc (chỉ cần Token)',
        'v2' => 'V2 — Chuẩn hoá (chỉ cần Token)',
        'v3' => 'V3 — Chuẩn hoá (cần Mật khẩu + STK + Token)',
    ];

    // =================================================================
    //  Cấu hình
    // =================================================================

    public static function baseUrl(): string
    {
        $url = rtrim(trim((string)setting('autobank_base_url', '')), '/');
        if ($url === '') {
            $url = (string)config('autobank.base_url', self::DEFAULT_BASE_URL);
        }
        $url = rtrim(trim($url), '/');

        return $url !== '' ? $url : self::DEFAULT_BASE_URL;
    }

    public static function enabled(): bool
    {
        $v = (string)setting('autobank_service_on', '');
        if ($v === '') {
            return (bool)config('autobank.service_on', true);
        }
        return $v === '1';
    }

    /**
     * Cấu hình chung: có bật tự duyệt nạp tiền không.
     */
    public static function autoApproveEnabled(): bool
    {
        return setting('autobank_auto_approve', '0') === '1';
    }

    /**
     * Bảng giá / mức phí dịch vụ (hiển thị cho admin, không trừ ví khách).
     */
    public static function serviceFee(): float
    {
        return (float)setting('autobank_service_fee', '0');
    }

    // =================================================================
    //  Gọi API
    // =================================================================

    /**
     * Gọi endpoint lịch sử giao dịch.
     *
     * @param string $bankKey   key trong self::BANKS
     * @param string $version   v1|v2|v3
     * @param array  $creds     ['token'=>..,'password'=>..,'stk'=>..]
     * @param array  $query     tham số phụ (limit, toDate, msgType...)
     *
     * @return array ['ok'=>bool,'rows'=>array,'raw'=>array,'message'=>string]
     */
    public static function fetch(string $bankKey, string $version, array $creds, array $query = []): array
    {
        $bank = self::BANKS[$bankKey] ?? null;
        if (!$bank) {
            return ['ok' => false, 'rows' => [], 'raw' => [], 'message' => 'Ngân hàng không hợp lệ.'];
        }

        // Chế độ sandbox: dùng endpoint playground, dữ liệu mẫu
        if (!empty($creds['sandbox'])) {
            return self::sandboxFetch($bankKey, $creds);
        }

        $ver = $bank['versions'][$version] ?? null;
        if (!$ver) {
            return ['ok' => false, 'rows' => [], 'raw' => [], 'message' => 'Phiên bản API không được hỗ trợ cho ngân hàng này.'];
        }

        $token = trim((string)($creds['token'] ?? ''));
        if ($token === '') {
            return ['ok' => false, 'rows' => [], 'raw' => [], 'message' => 'Chưa cấu hình token API.'];
        }

        // Dựng đường dẫn theo tham số bắt buộc
        $segments = [];
        foreach ($ver['params'] as $p) {
            $value = trim((string)($creds[$p] ?? ''));
            if ($value === '') {
                return [
                    'ok' => false, 'rows' => [], 'raw' => [],
                    'message' => 'Thiếu tham số "' . strtoupper($p) . '" cho endpoint ' . $version . '.',
                ];
            }
            $segments[] = rawurlencode($value);
        }
        $segments[] = rawurlencode($token);

        $url = self::baseUrl() . '/' . $ver['path'] . '/' . implode('/', $segments);
        if ($query) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        $res = HttpClient::requestFull('GET', $url, null, ['Accept: application/json'], [
            'timeout' => 25, 'connect_timeout' => 10,
        ]);

        if ($res['body'] === null) {
            return ['ok' => false, 'rows' => [], 'raw' => [], 'message' => 'Không kết nối được tới API Auto Bank.'];
        }

        $decoded = json_decode($res['body'], true);
        if (!is_array($decoded)) {
            // Một số endpoint V1 trả về text thuần (ví dụ "bao tri") khi bảo trì
            $raw = trim((string)$res['body']);
            return [
                'ok' => false, 'rows' => [], 'raw' => [],
                'message' => $raw !== ''
                    ? 'API trả về nội dung không chuẩn: "' . mb_substr($raw, 0, 120) . '"'
                    : 'API trả về dữ liệu không hợp lệ (HTTP ' . $res['status'] . ').',
            ];
        }

        // Lỗi có thể trả về HTTP 200 kèm body báo lỗi
        // (ví dụ {"status":"error","msg":"Token không hợp lệ"}).
        $apiError = self::detectError($decoded);
        if ($apiError !== null) {
            return ['ok' => false, 'rows' => [], 'raw' => $decoded, 'message' => $apiError];
        }

        if (!$res['ok']) {
            $msg = (string)($decoded['message'] ?? ($decoded['msg'] ?? ($decoded['des'] ?? '')));
            return [
                'ok' => false, 'rows' => [], 'raw' => $decoded,
                'message' => ($msg !== '' ? $msg : 'API lỗi (HTTP ' . $res['status'] . ').'),
            ];
        }

        $rows = self::extractRows($decoded, $ver['shape']);
        $out  = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $norm = self::normalize($row, $ver['shape']);
            if ($norm !== null) {
                $out[] = $norm;
            }
        }

        return ['ok' => true, 'rows' => $out, 'raw' => $decoded, 'message' => ''];
    }

    /**
     * Phát hiện lỗi trong body (API trả HTTP 200 nhưng status = "error").
     *
     * @return string|null thông báo lỗi, null nếu không có lỗi
     */
    private static function detectError(array $decoded): ?string
    {
        // Bản chuẩn hoá: {"status":"error","msg":"..."}
        $status = strtolower((string)($decoded['status'] ?? ''));
        if ($status === 'error' || $status === 'fail' || $status === 'failed') {
            return (string)($decoded['msg'] ?? ($decoded['message'] ?? 'API trả về lỗi.'));
        }

        // Bản gốc: {"code":"00","des":"success"} — code khác "00" là lỗi
        if (isset($decoded['code']) && is_string($decoded['code']) && $decoded['code'] !== '00') {
            $msg = (string)($decoded['message'] ?? ($decoded['des'] ?? ($decoded['msg'] ?? '')));
            return $msg !== '' ? $msg : ('Mã lỗi từ đối tác: ' . $decoded['code']);
        }

        // Body dạng text (ví dụ "bao tri" / "maintenance")
        if (isset($decoded[0]) === false && count($decoded) === 0) {
            return 'API không trả về dữ liệu.';
        }

        return null;
    }

    /**
     * Chế độ sandbox (dữ liệu mẫu, không cần tài khoản thật).
     */
    public static function sandboxEnabled(): bool
    {
        return setting('autobank_sandbox', '0') === '1';
    }

    /**
     * Gọi sandbox playground — endpoint: /sandbox/{bank}/{password}/{stk}/{token}
     *
     * @return array ['ok'=>bool,'rows'=>array,'raw'=>array,'message'=>string]
     */
    public static function sandboxFetch(string $bank, array $creds = []): array
    {
        $bank    = strtolower(trim($bank));
        $slug    = self::SANDBOX_SLUGS[$bank] ?? $bank;
        if (!in_array($bank, self::SANDBOX_SUPPORTED, true)) {
            return [
                'ok' => false, 'rows' => [], 'raw' => [],
                'message' => 'Ngân hàng "' . $bank . '" không có trong Sandbox Playground. ' .
                    'Các bank hỗ trợ: ' . implode(', ', self::SANDBOX_SUPPORTED) . '.',
            ];
        }
        $password= (string)($creds['password'] ?? 'sandbox_password');
        $stk     = (string)($creds['stk'] ?? '0123456789');
        $token   = (string)($creds['token'] ?? 'sandbox_token_stcpay');

        $url = self::baseUrl() . '/sandbox/' . rawurlencode($slug) . '/'
             . rawurlencode($password) . '/' . rawurlencode($stk) . '/' . rawurlencode($token);

        $res = HttpClient::requestFull('GET', $url, null, ['Accept: application/json'], [
            'timeout' => 25, 'connect_timeout' => 10,
        ]);

        if ($res['body'] === null) {
            return ['ok' => false, 'rows' => [], 'raw' => [], 'message' => 'Không kết nối được sandbox.'];
        }

        $decoded = json_decode($res['body'], true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'rows' => [], 'raw' => [], 'message' => 'Sandbox trả về dữ liệu không hợp lệ.'];
        }

        $err = self::detectError($decoded);
        if ($err !== null || !$res['ok']) {
            $msg = (string)($decoded['msg'] ?? ($decoded['message'] ?? ('HTTP ' . $res['status'])));
            return ['ok' => false, 'rows' => [], 'raw' => $decoded, 'message' => $msg];
        }

        $rows = [];
        foreach (self::extractRows($decoded, 'standard') as $row) {
            if (!is_array($row)) {
                continue;
            }
            $norm = self::normalize($row, 'standard');
            if ($norm !== null) {
                $rows[] = $norm;
            }
        }

        return ['ok' => true, 'rows' => $rows, 'raw' => $decoded, 'message' => ''];
    }

    /**
     * Lấy mảng giao dịch thô từ response (tuỳ shape).
     */
    private static function extractRows(array $data, string $shape): array
    {
        $pick = static function (array $d, array $keys): array {
            foreach ($keys as $k) {
                if (isset($d[$k]) && is_array($d[$k])) {
                    return $d[$k];
                }
            }
            return [];
        };

        return match ($shape) {
            'tpbank_v1'     => $pick($data, ['transactionInfos', 'transactions', 'data']),
            'vcb_v1'        => $pick($data, ['transactions', 'data']),
            'vcbbiz_v1'     => $pick($data, ['listHistory', 'transactions']),
            'acb_v1'        => $pick($data, ['data', 'transactions']),
            'acbweb_v1'     => $pick($data, ['transactions', 'data']),
            'mbbank_v1'     => $pick($data, ['TranList', 'transactions', 'data']),
            'bidv_v1'       => $pick($data, ['txnList', 'transactions', 'data']),
            'viettin_v1'    => $pick($data, ['transactions', 'data']),
            'seabank_v1'    => $pick($data, ['data', 'transactions']),
            'msb_v1'        => $pick($data, ['data', 'transactions']),
            'tcb_v1'        => $pick($data, ['transactions', 'data']),
            'vpbank_v1'     => $pick($data, ['data', 'transactions']),
            'vietabank_v1'  => $pick($data, ['data', 'transactions']),
            'lpbank_v1'     => $pick($data, ['data', 'transactions']),
            'timo_v1'       => $pick($data, ['data', 'transactions']),
            'ocb_v1'        => $pick($data, ['transactions', 'data']),
            'hdbank_v1'     => $pick($data, ['transactions', 'data']),
            'pgbank_v1'     => $pick($data, ['tranList', 'transactions', 'data']),
            'eximbank_v1'   => $pick($data, ['transactions', 'data']),
            'thesieure_v1'  => $pick($data, ['data', 'transactions']),
            'viettel_money' => $pick($data, ['content', 'transactions']),
            'viettel_noti'  => $pick($data, ['content', 'transactions']),
            'binance_v1'    => $pick($data, ['data', 'transactions']),
            'standard'      => $pick($data, ['transactions', 'data']),
            default         => $pick($data, ['transactions', 'data']),
        };
    }

    /**
     * Chuẩn hoá 1 giao dịch về cấu trúc thống nhất.
     */
    public static function normalize(array $row, string $shape): ?array
    {
        $get = static function (array $r, array $keys, mixed $default = '') use ($shape) {
            foreach ($keys as $k) {
                if (array_key_exists($k, $r) && $r[$k] !== null && $r[$k] !== '') {
                    return $r[$k];
                }
            }
            return $default;
        };

        $id = '';
        $amount = 0.0;
        $desc = '';
        $date = '';
        $type = 'IN';
        $balance = null;
        $currency = 'VND';

        switch ($shape) {
            case 'tpbank_v1':
                $id      = (string)$get($row, ['id', 'transactionID'], '');
                $amount  = self::parseAmount($get($row, ['amount'], 0));
                $desc    = (string)$get($row, ['description'], '');
                $date    = (string)$get($row, ['bookingDate', 'transactionDate'], '');
                $cdi     = strtoupper((string)$get($row, ['creditDebitIndicator'], 'CREDIT'));
                $type    = in_array($cdi, ['DBIT', 'D', '-'], true) ? 'OUT' : 'IN';
                $balance = self::parseAmountNullable($get($row, ['runningBalance'], null));
                break;

            case 'vcb_v1':
                $id     = (string)$get($row, ['Reference', 'transactionID', 'SeqNo'], '');
                $amount = self::parseAmount($get($row, ['Amount', 'amount'], 0));
                $desc   = (string)$get($row, ['Description', 'Remark', 'description'], '');
                $date   = (string)$get($row, ['TransactionDate', 'tranDate', 'EffDate'], '');
                $cd     = trim((string)$get($row, ['CD', 'cd'], '+'));
                $type   = ($cd === '-' || strtoupper($cd) === 'DBIT') ? 'OUT' : 'IN';
                break;

            case 'vcbbiz_v1':
                $id     = (string)$get($row, ['reference', 'transactionID'], '');
                $amount = self::parseAmount($get($row, ['amount', 'Amount'], 0));
                $desc   = (string)$get($row, ['description', 'Description'], '');
                $date   = (string)$get($row, ['transactionDate', 'tranDate'], '');
                $cd     = strtoupper(trim((string)$get($row, ['cd', 'CD'], 'C')));
                $type   = in_array($cd, ['D', 'DBIT', '-'], true) ? 'OUT' : 'IN';
                break;

            case 'acb_v1':
                $id     = (string)$get($row, ['transactionNumber', 'transactionID'], '');
                $amount = self::parseAmount($get($row, ['amount'], 0));
                $desc   = (string)$get($row, ['description', 'senderName'], '');
                $date   = self::formatTimestamp($get($row, ['postingDate', 'effectiveDate', 'activeDatetime'], ''));
                $type   = strtoupper((string)$get($row, ['type'], 'IN')) === 'OUT' ? 'OUT' : 'IN';
                break;

            case 'acbweb_v1':
                $id      = (string)$get($row, ['transactionNumber', 'transactionID'], '');
                $credit  = (string)$get($row, ['credit'], '');
                $debit   = (string)$get($row, ['debit'], '');
                $amount  = self::parseAmount($credit !== '' ? $credit : $debit);
                $type    = $credit !== '' ? 'IN' : 'OUT';
                $desc    = (string)$get($row, ['description'], '');
                $date    = (string)$get($row, ['effectiveDate', 'transDate'], '');
                $balance = self::parseAmountNullable($get($row, ['balance'], null));
                break;

            case 'mbbank_v1':
                $id      = (string)$get($row, ['refNo', 'tranId', 'transactionID'], '');
                $credit  = self::parseAmount($get($row, ['creditAmount'], 0));
                $debit   = self::parseAmount($get($row, ['debitAmount'], 0));
                $amount  = $credit > 0 ? $credit : $debit;
                $type    = $credit > 0 ? 'IN' : 'OUT';
                $desc    = (string)$get($row, ['description', 'addDescription'], '');
                $date    = (string)$get($row, ['transactionDate', 'postingDate'], '');
                $balance = self::parseAmountNullable($get($row, ['availableBalance'], null));
                $currency= (string)$get($row, ['currency'], 'VND');
                break;

            case 'bidv_v1':
                $id     = (string)$get($row, ['transactionID', 'txnID'], '');
                $amount = self::parseAmount($get($row, ['amount'], 0));
                $desc   = (string)$get($row, ['txnRemark', 'description'], '');
                $date   = (string)$get($row, ['txnDate', 'transactionDate'], '');
                $balance= self::parseAmountNullable($get($row, ['balance'], null));
                break;

            case 'viettin_v1':
            case 'msb_v1':
            case 'tcb_v1':
            case 'vpbank_v1':
            case 'vietabank_v1':
            case 'lpbank_v1':
            case 'timo_v1':
            case 'thesieure_v1':
                $id     = (string)$get($row, ['transactionID', 'transactionId', 'transId', 'transID'], '');
                $amount = self::parseAmount($get($row, ['amount'], 0));
                $desc   = (string)$get($row, ['description', 'transactionNote'], '');
                $date   = (string)$get($row, ['date', 'transactionDate'], '');
                break;

            case 'seabank_v1':
                $id     = (string)$get($row, ['transID', 'transactionID'], '');
                $amount = self::parseAmount($get($row, ['totalAmount', 'amount'], 0));
                $desc   = (string)$get($row, ['description'], '');
                $date   = (string)$get($row, ['dateTime', 'transactionDate'], '');
                break;

            case 'ocb_v1':
            case 'hdbank_v1':
                $id      = (string)$get($row, ['transactionID', 'transactionId'], '');
                $amount  = self::parseAmount($get($row, ['amount'], 0));
                $desc    = (string)$get($row, ['description'], '');
                $date    = (string)$get($row, ['transactionDate', 'tranDate'], '');
                $type    = strtoupper((string)$get($row, ['type'], 'IN')) === 'OUT' ? 'OUT' : 'IN';
                $balance = self::parseAmountNullable($get($row, ['runningBalance'], null));
                break;

            case 'pgbank_v1':
                $id      = (string)$get($row, ['MaGD', 'transactionID', 'StmtID'], '');
                $amount  = self::parseAmount($get($row, ['AmountLCY', 'amount'], 0));
                $desc    = (string)$get($row, ['Narrative', 'description'], '');
                $date    = self::pgDate((string)$get($row, ['Datetime'], ''));
                $balance = self::parseAmountNullable($get($row, ['Endbalacne', 'Endbalance'], null));
                break;

            case 'eximbank_v1':
                $id     = (string)$get($row, ['tranRef', 'transactionID'], '');
                $amount = self::parseAmount($get($row, ['tranAmount', 'amount'], 0));
                $desc   = (string)$get($row, ['tranRemark', 'description'], '');
                $date   = (string)$get($row, ['tranTime', 'transactionDate'], '');
                $tt     = trim((string)$get($row, ['tranType'], '+'));
                $type   = ($tt === '-' || strtoupper($tt) === 'DBIT') ? 'OUT' : 'IN';
                break;

            case 'viettel_money':
                $id     = (string)$get($row, ['bankTransId', 'transactionID'], '');
                $amount = self::parseAmount($get($row, ['amount'], 0));
                $desc   = (string)$get($row, ['description'], '');
                $date   = (string)$get($row, ['transDate', 'transactionDate'], '');
                break;

            case 'viettel_noti':
                $id      = (string)$get($row, ['bankTransId', 'orderId', 'transactionID'], '');
                $amount  = self::parseAmount($get($row, ['amount'], 0));
                $desc    = (string)$get($row, ['description', 'msgContent'], '');
                $date    = (string)$get($row, ['transDate', 'timestamp'], '');
                $balance = self::parseAmountNullable($get($row, ['balance'], null));
                $type    = strtoupper((string)$get($row, ['paymentType'], 'CREDIT')) === 'DEBIT' ? 'OUT' : 'IN';
                break;

            case 'binance_v1':
                $id      = (string)$get($row, ['transactionId', 'orderId'], '');
                $amount  = self::parseAmount($get($row, ['amount'], 0));
                $desc    = (string)$get($row, ['note'], '');
                $payer   = $row['payerInfo'] ?? null;
                if (is_array($payer) && !empty($payer['name'])) {
                    $desc = trim((string)$payer['name'] . ' ' . $desc);
                }
                $date    = self::formatTimestamp($get($row, ['transactionTime'], ''));
                $currency= (string)$get($row, ['currency'], 'USDT');
                $type    = 'IN';
                break;

            default: // standard (V2/V3/Shared)
                $id      = (string)$get($row, ['transactionID', 'transactionId', 'transId'], '');
                $amount  = self::parseAmount($get($row, ['amount', 'Amount'], 0));
                $desc    = (string)$get($row, ['description', 'Description'], '');
                $date    = (string)$get($row, ['transactionDate', 'transaction_date'], '');
                $type    = strtoupper((string)$get($row, ['type'], 'IN')) === 'OUT' ? 'OUT' : 'IN';
                $balance = self::parseAmountNullable($get($row, ['balance', 'runningBalance'], null));
                break;
        }

        if ($id === '' && $amount <= 0 && $desc === '') {
            return null;
        }

        return [
            'transactionID'   => $id,
            'amount'          => $amount,
            'description'     => $desc,
            'transactionDate' => $date,
            'type'            => $type,
            'balance'         => $balance,
            'currency'        => $currency,
        ];
    }

    /**
     * Chuẩn hoá số tiền (chấp nhận "150,000", "1.500.000", "150000", "5.07").
     */
    public static function parseAmount(mixed $value): float
    {
        $raw = trim((string)$value);
        if ($raw === '') {
            return 0.0;
        }

        $negative = str_starts_with($raw, '-');
        $raw = str_replace([' ', "\xC2\xA0", 'đ', '₫', 'VND', 'vnd'], '', $raw);

        $hasComma = str_contains($raw, ',');
        $hasDot   = str_contains($raw, '.');

        if ($hasComma && $hasDot) {
            // Dấu phân cách nghìn là dấu xuất hiện sau cùng
            if (strrpos($raw, ',') > strrpos($raw, '.')) {
                $raw = str_replace('.', '', $raw);
                $raw = str_replace(',', '.', $raw);
            } else {
                $raw = str_replace(',', '', $raw);
            }
        } elseif ($hasComma) {
            if (preg_match('/^\d{1,3}(,\d{3})+$/', $raw)) {
                $raw = str_replace(',', '', $raw);
            } else {
                $raw = str_replace(',', '.', $raw);
            }
        } elseif ($hasDot) {
            if (preg_match('/^\d{1,3}(\.\d{3})+$/', $raw)) {
                $raw = str_replace('.', '', $raw);
            }
        }

        $raw = preg_replace('/[^0-9.]/', '', $raw) ?? '';
        if ($raw === '' || $raw === '.') {
            return 0.0;
        }

        $result = (float)$raw;

        return $negative ? -$result : $result;
    }

    private static function parseAmountNullable(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        return self::parseAmount($value);
    }

    /**
     * Timestamp (ms) → 'Y-m-d H:i:s'.
     */
    private static function formatTimestamp(mixed $value): string
    {
        $raw = trim((string)$value);
        if ($raw === '') {
            return '';
        }
        if (preg_match('/^\d{13}$/', $raw)) {
            return date('Y-m-d H:i:s', (int)floor((int)$raw / 1000));
        }
        if (preg_match('/^\d{10}$/', $raw)) {
            return date('Y-m-d H:i:s', (int)$raw);
        }
        $ts = strtotime($raw);
        return $ts === false ? $raw : date('Y-m-d H:i:s', $ts);
    }

    /**
     * PGBank định dạng "26-06-09 19:17:59" (yy-MM-dd).
     */
    private static function pgDate(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        if (preg_match('/^(\d{2})-(\d{2})-(\d{2})\s+(\d{2}:\d{2}:\d{2})$/', $raw, $m)) {
            $yy = (int)$m[1];
            $year = $yy >= 70 ? 1900 + $yy : 2000 + $yy;
            return $year . '-' . $m[2] . '-' . $m[3] . ' ' . $m[4];
        }
        $ts = strtotime($raw);
        return $ts === false ? $raw : date('Y-m-d H:i:s', $ts);
    }

    // =================================================================
    //  Tài khoản ngân hàng (bản sao cục bộ)
    // =================================================================

    /**
     * Lưu tài khoản (tạo mới hoặc cập nhật).
     */
    public static function saveAccount(array $data): int
    {
        $id       = (int)($data['id'] ?? 0);
        $bankKey  = trim((string)($data['bank_key'] ?? ''));
        $version  = trim((string)($data['api_version'] ?? 'v1'));
        $label    = trim((string)($data['label'] ?? ''));

        if (!isset(self::BANKS[$bankKey])) {
            throw new \RuntimeException('Ngân hàng không hợp lệ.');
        }
        if (!isset(self::BANKS[$bankKey]['versions'][$version])) {
            throw new \RuntimeException('Phiên bản API không được hỗ trợ cho ngân hàng này.');
        }
        if ($label === '') {
            $label = self::BANKS[$bankKey]['label'];
        }

        $row = [
            'label'         => $label,
            'bank_key'      => $bankKey,
            'bank_name'     => self::BANKS[$bankKey]['label'],
            'api_version'   => $version,
            'stk'           => trim((string)($data['stk'] ?? '')) ?: null,
            'match_content' => trim((string)($data['match_content'] ?? '')) ?: null,
            'min_amount'    => (float)($data['min_amount'] ?? 0),
            'max_amount'    => (float)($data['max_amount'] ?? 0),
            'auto_approve'  => !empty($data['auto_approve']) ? 1 : 0,
            'is_active'     => isset($data['is_active']) ? 1 : 0,
            'is_sandbox'    => !empty($data['is_sandbox']) ? 1 : 0,
            'note'          => mb_substr(trim((string)($data['note'] ?? '')), 0, 255) ?: null,
            'updated_at'    => now(),
        ];

        // Token / mật khẩu: chỉ ghi đè khi admin nhập giá trị mới
        $token = trim((string)($data['token'] ?? ''));
        if ($token !== '') {
            $row['token_enc'] = Crypto::encrypt($token);
        }
        $password = trim((string)($data['password'] ?? ''));
        if ($password !== '') {
            $row['password_enc'] = Crypto::encrypt($password);
        }

        if ($id > 0) {
            $exists = Database::fetch('SELECT id FROM auto_bank_accounts WHERE id = ?', [$id]);
            if ($exists) {
                Database::update('auto_bank_accounts', $row, 'id = :id', ['id' => $id]);
                return $id;
            }
        }

        $row['created_at'] = now();

        return Database::insert('auto_bank_accounts', $row);
    }

    /**
     * Credentials đã giải mã cho 1 tài khoản.
     */
    public static function credentials(array $account): array
    {
        return [
            'token'    => Crypto::decrypt((string)($account['token_enc'] ?? '')) ?? '',
            'password' => Crypto::decrypt((string)($account['password_enc'] ?? '')) ?? '',
            'stk'      => (string)($account['stk'] ?? ''),
            'sandbox'  => (int)($account['is_sandbox'] ?? 0) === 1,
        ];
    }

    public static function allAccounts(): array
    {
        return Database::fetchAll(
            'SELECT * FROM auto_bank_accounts
             ORDER BY is_active DESC, id DESC'
        );
    }

    public static function findAccount(int $id): ?array
    {
        return Database::fetch('SELECT * FROM auto_bank_accounts WHERE id = ?', [$id]);
    }

    public static function deleteAccount(int $id): void
    {
        Database::query('DELETE FROM auto_bank_transactions WHERE account_id = ?', [$id]);
        Database::query('DELETE FROM auto_bank_accounts WHERE id = ?', [$id]);
    }

    // =================================================================
    //  Đồng bộ giao dịch
    // =================================================================

    /**
     * Đồng bộ giao dịch cho 1 tài khoản và lưu vào DB (bỏ qua giao dịch đã có).
     */
    public static function syncAccount(int $accountId, bool $autoApprove = false): array
    {
        $acc = self::findAccount($accountId);
        if (!$acc) {
            return ['ok' => false, 'new' => 0, 'message' => 'Không tìm thấy tài khoản.'];
        }

        $creds = self::credentials($acc);
        $res   = self::fetch(
            (string)$acc['bank_key'],
            (string)$acc['api_version'],
            $creds
        );

        if (!$res['ok']) {
            return ['ok' => false, 'new' => 0, 'message' => $res['message']];
        }

        $newCount = 0;
        $inserted = [];

        foreach ($res['rows'] as $row) {
            $fingerprint = self::fingerprint($accountId, $row);
            $exists = Database::fetchColumn(
                'SELECT id FROM auto_bank_transactions WHERE account_id = ? AND fingerprint = ?',
                [$accountId, $fingerprint]
            );
            if ($exists) {
                continue;
            }

            $txnId = Database::insert('auto_bank_transactions', [
                'account_id'      => $accountId,
                'fingerprint'      => $fingerprint,
                'transaction_id'   => mb_substr((string)$row['transactionID'], 0, 120),
                'amount'           => (float)$row['amount'],
                'description'      => mb_substr((string)$row['description'], 0, 500),
                'transaction_date' => self::toDatetime((string)$row['transactionDate']),
                'type'             => (string)$row['type'],
                'balance'          => $row['balance'],
                'currency'         => (string)$row['currency'],
                'raw'              => json_encode($row, JSON_UNESCAPED_UNICODE),
                'matched_deposit'  => null,
                'created_at'       => now(),
            ]);

            $newCount++;
            $inserted[] = $txnId;
        }

        Database::update('auto_bank_accounts', [
            'last_sync_at'   => now(),
            'last_sync_count' => count($res['rows']),
            'last_sync_msg'   => mb_substr('OK — ' . count($res['rows']) . ' giao dịch', 0, 255),
            'updated_at'      => now(),
        ], 'id = :id', ['id' => $accountId]);

        // Đối soát & duyệt nạp tiền tự động
        $matched = 0;
        if ($autoApprove && self::autoApproveEnabled()) {
            $matched = self::reconcile($accountId);
        }

        return [
            'ok'      => true,
            'new'     => $newCount,
            'total'   => count($res['rows']),
            'matched' => $matched,
            'message' => 'Đã đồng bộ ' . count($res['rows']) . ' giao dịch (' . $newCount . ' giao dịch mới).',
        ];
    }

    /**
     * Khoá duy nhất cho 1 giao dịch (tránh đồng bộ trùng).
     *
     * Ưu tiên transactionID vì đây là mã ổn định của ngân hàng.
     * Nếu ngân hàng không trả mã → dùng hash từ (amount + ngày + loại + nội dung).
     */
    private static function fingerprint(int $accountId, array $row): string
    {
        $txnId = trim((string)($row['transactionID'] ?? ''));

        if ($txnId !== '') {
            return hash('sha256', $accountId . '|id|' . $txnId);
        }

        return hash('sha256', implode('|', [
            $accountId,
            'hash',
            (string)$row['amount'],
            (string)$row['transactionDate'],
            (string)$row['type'],
            (string)$row['description'],
        ]));
    }

    /**
     * Đối soát giao dịch "tiền vào" với các phiếu nạp tiền đang chờ.
     *
     * Quy tắc khớp:
     *   1. Ưu tiên khớp theo mã giao dịch (transaction_code) nằm trong nội dung.
     *   2. Nếu không có mã → khớp theo số tiền + từ khoá nội dung (nếu cấu hình).
     *
     * @return int số phiếu đã duyệt
     */
    public static function reconcile(int $accountId): int
    {
        $acc = self::findAccount($accountId);
        if (!$acc) {
            return 0;
        }

        $keyword = trim((string)($acc['match_content'] ?? ''));
        $min     = (float)($acc['min_amount'] ?? 0);
        $max     = (float)($acc['max_amount'] ?? 0);

        $txns = Database::fetchAll(
            'SELECT * FROM auto_bank_transactions
             WHERE account_id = ? AND type = "IN" AND matched_deposit IS NULL
             ORDER BY id ASC LIMIT 100',
            [$accountId]
        );

        $approved = 0;

        foreach ($txns as $txn) {
            $amount = (float)$txn['amount'];
            if ($amount <= 0) {
                continue;
            }
            if ($min > 0 && $amount < $min) {
                continue;
            }
            if ($max > 0 && $amount > $max) {
                continue;
            }

            $desc = (string)$txn['description'];

            // 1. Khớp theo mã giao dịch trong nội dung
            $deposit = null;
            if (preg_match_all('/\b[A-Z0-9]{6,40}\b/', strtoupper($desc), $m)) {
                foreach ($m[0] as $code) {
                    $row = Database::fetch(
                        'SELECT * FROM deposits WHERE transaction_code = ? AND status = "pending"',
                        [$code]
                    );
                    if ($row) {
                        $deposit = $row;
                        break;
                    }
                }
            }

            // 2. Khớp theo số tiền + từ khoá
            if (!$deposit) {
                if ($keyword !== '' && stripos($desc, $keyword) === false) {
                    continue;
                }
                $deposit = Database::fetch(
                    'SELECT * FROM deposits WHERE status = "pending" AND amount = ? ORDER BY id ASC LIMIT 1',
                    [$amount]
                );
            }

            if (!$deposit) {
                continue;
            }

            // 3. Duyệt phiếu
            if (self::approveDeposit((int)$deposit['id'], (int)$txn['id'])) {
                $approved++;
            }
        }

        return $approved;
    }

    /**
     * Duyệt phiếu nạp tiền + cộng tiền vào ví (tương tự AdminController::approveDeposit).
     */
    public static function approveDeposit(int $depositId, int $txnId = 0): bool
    {
        $dep = Database::fetch('SELECT * FROM deposits WHERE id = ?', [$depositId]);
        if (!$dep || $dep['status'] !== 'pending') {
            return false;
        }

        $userId = (int)$dep['user_id'];

        try {
            Database::transaction(function () use ($dep, $userId, $depositId, $txnId) {
                $total = (float)$dep['amount'] + (float)$dep['bonus_amount'];

                Wallet::apply(
                    $userId,
                    'deposit',
                    $total,
                    'Nạp tiền tự động ' . money((float)$dep['amount']) .
                        ($dep['bonus_amount'] > 0 ? ' + khuyến mãi ' . money((float)$dep['bonus_amount']) : ''),
                    'deposit',
                    (string)$depositId,
                    ['method' => 'auto_bank', 'code' => $dep['transaction_code']]
                );

                Database::update('users', [
                    'total_deposit' => (float)Database::fetchColumn(
                        'SELECT total_deposit FROM users WHERE id = ?',
                        [$userId]
                    ) + (float)$dep['amount'],
                ], 'id = :id', ['id' => $userId]);

                Database::update('deposits', [
                    'status'         => 'approved',
                    'method'         => 'auto_bank',
                    'processed_by'   => null,
                    'processed_at'   => now(),
                    'auto_txn_id'    => $txnId ?: null,
                ], 'id = :id', ['id' => $depositId]);

                if ($txnId > 0) {
                    Database::update('auto_bank_transactions', [
                        'matched_deposit' => $depositId,
                    ], 'id = :id', ['id' => $txnId]);
                }
            });
        } catch (\Throwable $e) {
            logger('AutoBank approve deposit #' . $depositId . ' failed: ' . $e->getMessage(), 'error');
            return false;
        }

        audit('autobank.deposit.approve', 'deposits', (string)$depositId, [
            'txn_id' => $txnId,
            'amount' => (float)$dep['amount'],
        ]);

        return true;
    }

    // =================================================================
    //  Truy vấn
    // =================================================================

    public static function transactions(int $accountId = 0, int $limit = 100, string $type = ''): array
    {
        $where  = '1=1';
        $params = [];

        if ($accountId > 0) {
            $where .= ' AND t.account_id = ?';
            $params[] = $accountId;
        }
        if ($type === 'IN' || $type === 'OUT') {
            $where .= ' AND t.type = ?';
            $params[] = $type;
        }

        return Database::fetchAll(
            "SELECT t.*, a.label, a.bank_name, d.id AS dep_id, d.status AS dep_status
             FROM auto_bank_transactions t
             LEFT JOIN auto_bank_accounts a ON a.id = t.account_id
             LEFT JOIN deposits d ON d.id = t.matched_deposit
             WHERE {$where}
             ORDER BY t.id DESC LIMIT " . (int)$limit,
            $params
        );
    }

    public static function pendingDeposits(int $limit = 100): array
    {
        return Database::fetchAll(
            'SELECT d.*, u.username FROM deposits d
             JOIN users u ON u.id = d.user_id
             WHERE d.status = "pending" ORDER BY d.id DESC LIMIT ' . (int)$limit
        );
    }

    public static function stats(): array
    {
        return [
            'accounts'    => (int)Database::fetchColumn('SELECT COUNT(*) FROM auto_bank_accounts'),
            'active'      => (int)Database::fetchColumn('SELECT COUNT(*) FROM auto_bank_accounts WHERE is_active = 1'),
            'transactions'=> (int)Database::fetchColumn('SELECT COUNT(*) FROM auto_bank_transactions'),
            'incoming'    => (int)Database::fetchColumn('SELECT COUNT(*) FROM auto_bank_transactions WHERE type = "IN"'),
            'outgoing'    => (int)Database::fetchColumn('SELECT COUNT(*) FROM auto_bank_transactions WHERE type = "OUT"'),
            'volume_in'   => (float)Database::fetchColumn('SELECT COALESCE(SUM(amount),0) FROM auto_bank_transactions WHERE type = "IN"'),
            'matched'     => (int)Database::fetchColumn('SELECT COUNT(*) FROM auto_bank_transactions WHERE matched_deposit IS NOT NULL'),
            'auto_approved'=> (int)Database::fetchColumn('SELECT COUNT(*) FROM deposits WHERE method = "auto_bank" AND status = "approved"'),
            'pending'     => (int)Database::fetchColumn('SELECT COUNT(*) FROM deposits WHERE status = "pending"'),
        ];
    }

    /**
     * Chuẩn hoá ngày giờ về DATETIME.
     */
    public static function toDatetime(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^\d{13}$/', $value)) {
            return date('Y-m-d H:i:s', (int)floor((int)$value / 1000));
        }
        $ts = strtotime($value);
        if ($ts === false) {
            return null;
        }
        return date('Y-m-d H:i:s', $ts);
    }
}
