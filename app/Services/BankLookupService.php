<?php
namespace App\Services;

use App\Core\Database;

/**
 * Tra cứu thông tin ngân hàng Việt Nam.
 *
 * Nguồn dữ liệu:
 *  1. Bảng `bank_accounts` — admin tự nhập (tra cứu nội bộ, luôn hoạt động).
 *  2. API VietQR v2 (`/v2/lookup`) — cần x-client-id + x-api-key trong cấu hình admin.
 *  3. Danh sách ngân hàng: `GET https://api.vietqr.io/v2/banks` (có kèm logo).
 */
class BankLookupService
{
    /** Danh sách ngân hàng dự phòng khi API lỗi (mã BIN → tên + mã ngân hàng) */
    public const BANKS = [
        '970436' => 'Vietcombank',      '970407' => 'Techcombank',    '970415' => 'VietinBank',
        '970416' => 'ACB',              '970418' => 'BIDV',           '970422' => 'MB Bank',
        '970423' => 'HDBank',           '970429' => 'Agribank',       '970437' => 'HDBank',
        '970438' => 'Sacombank',        '970439' => 'SCB',            '970424' => 'Nam A Bank',
        '970425' => 'SeABank',          '970426' => 'MSB',            '970428' => 'VIB',
        '970431' => 'Eximbank',         '970432' => 'SHB',            '970433' => 'VietBank',
        '970434' => 'HDBank',           '970440' => 'SeABank',        '970441' => 'VIB',
        '970442' => 'Saigon-Hanoi',     '970444' => 'Kienlongbank',   '970448' => 'PGBank',
        '970454' => 'MB Bank',          '970457' => 'MSB',            '970458' => 'MSB',
        '970459' => 'MICB',             '970460' => 'VietBank',       '970462' => 'Kienlongbank',
        '970466' => 'Bank Bình Dương', '970467' => 'Bảo Việt',      '970468' => 'AnBinh',
        '970469' => 'ACB',              '970470' => 'Vietcombank',    '970471' => 'TPBank',
        '970472' => 'VietBank',         '970473' => 'SeABank',        '970474' => 'CGB',
        '970475' => 'Vietcombank',      '970476' => 'HDBank',         '970477' => 'Vietcombank',
        '970478' => 'BIDV',             '970479' => 'Agribank',       '970480' => 'Agribank',
        '970481' => 'Techcombank',      '970482' => 'ACB',            '970483' => 'MMB',
        '970484' => 'Vietcombank',      '970485' => 'MB Bank',        '970486' => 'LPBank',
        '970487' => 'VPBank',           '970488' => 'Vietcombank',    '970489' => 'Vietcombank',
        '970490' => 'VIB',              '970491' => 'Vietcombank',    '970492' => 'Vietcombank',
        '970493' => 'HDBank',           '970494' => 'Vietcombank',    '970496' => 'PVcombank',
        '970497' => 'MB Bank',          '970498' => 'Vietcombank',    '970499' => 'Vietcombank',
    ];

    private const BANKS_API  = 'https://api.vietqr.io/v2/banks';
    private const LOOKUP_API = 'https://api.vietqr.io/v2/lookup';

    /**
     * Ánh xạ BIN → mã ngân hàng chuẩn (MB, VCB, TCB...) để tra logo cục bộ.
     * Dùng khi API không trả về logo hoặc bị lỗi mạng.
     */
    private const BIN_TO_CODE = [
        '970422' => 'MB', '970454' => 'MB', '970485' => 'MB', '970497' => 'MB',
        '970470' => 'VCB', '970436' => 'VCB', '970475' => 'VCB', '970477' => 'VCB',
        '970478' => 'BIDV', '970407' => 'TCB', '970481' => 'TCB',
        '970415' => 'ICB', '970416' => 'ACB', '970482' => 'ACB', '970469' => 'ACB',
        '970429' => 'AGRI', '970479' => 'AGRI', '970480' => 'AGRI',
        '970428' => 'VIB', '970441' => 'VIB', '970490' => 'VIB',
        '970423' => 'HDB', '970437' => 'HDB', '970434' => 'HDB', '970476' => 'HDB', '970493' => 'HDB',
        '970438' => 'SCB', '970439' => 'SCB',
        '970425' => 'SEAB', '970440' => 'SEAB', '970473' => 'SEAB',
        '970471' => 'TPB', '970426' => 'MSB', '970457' => 'MSB', '970458' => 'MSB',
        '970432' => 'SHB', '970433' => 'VBB', '970460' => 'VBB', '970472' => 'VBB',
        '970487' => 'VPB', '970496' => 'PVB', '970448' => 'PGB',
        '970431' => 'EIB', '970486' => 'LPB', '970468' => 'ABB',
        '970467' => 'BVG', '970466' => 'BDB', '970459' => 'MICB',
        '970442' => 'SHB', '970444' => 'KLB', '970462' => 'KLB',
        '970452' => 'TCB', '970453' => 'MSB', '970461' => 'VIB',
        '970474' => 'CGB', '970424' => 'NAB', '970451' => 'VAB',
    ];

    /** @var array|null cache danh sách ngân hàng trong request */
    private static ?array $bankCache = null;

    /**
     * Danh sách ngân hàng (mã BIN, tên, mã ngân hàng, logo).
     */
    public static function banks(): array
    {
        if (self::$bankCache !== null) {
            return self::$bankCache;
        }

        $out = [];

        $res = HttpClient::getJson(self::BANKS_API, [], ['Accept: application/json'], ['timeout' => 12, 'retries' => 1]);
        if (is_array($res) && !empty($res['data']) && is_array($res['data'])) {
            foreach ($res['data'] as $b) {
                $bin = (string)($b['bin'] ?? '');
                if ($bin === '') {
                    continue;
                }
                $apiCode  = (string)($b['code'] ?? '');
                $ourCode  = self::BIN_TO_CODE[$bin] ?? ($apiCode !== '' ? $apiCode : '');
                $logo     = trim((string)($b['logo'] ?? ''));
                $out[] = [
                    'code'      => $apiCode !== '' ? $apiCode : $bin,
                    'bin'       => $bin,
                    'bank_code' => $ourCode,
                    'name'      => (string)($b['name'] ?? ($b['shortName'] ?? $bin)),
                    'shortName' => (string)($b['shortName'] ?? ($b['code'] ?? $bin)),
                    'logo'      => $logo !== '' ? $logo : self::localLogo($ourCode),
                ];
            }
        }

        // API lỗi → dùng danh mục dự phòng
        if (!$out) {
            foreach (self::BANKS as $bin => $name) {
                $ourCode = self::BIN_TO_CODE[$bin] ?? '';
                $out[] = [
                    'code'      => $ourCode !== '' ? $ourCode : $bin,
                    'bin'       => $bin,
                    'bank_code' => $ourCode,
                    'name'      => $name,
                    'shortName' => $name,
                    'logo'      => self::localLogo($ourCode),
                ];
            }
        }

        return self::$bankCache = $out;
    }

    /**
     * URL logo ngân hàng từ bộ logo cục bộ (không phụ thuộc mạng).
     */
    public static function localLogo(string $code): string
    {
        if ($code === '') {
            return '';
        }
        $html = bank_logo($code, 'bank-logo');
        if ($html === '') {
            return '';
        }
        // Lấy src từ thẻ <img> đã render
        if (preg_match('/src="([^"]+)"/', $html, $m)) {
            return $m[1];
        }
        return '';
    }

    /**
     * Tìm thông tin một ngân hàng theo mã BIN.
     */
    public static function findBank(string $bin): ?array
    {
        foreach (self::banks() as $b) {
            if ((string)$b['code'] === $bin || (string)($b['bank_code'] ?? '') === $bin) {
                return $b;
            }
        }
        return null;
    }

    /**
     * Tra cứu tài khoản ngân hàng theo số tài khoản.
     *
     * @param string $accountNo Số tài khoản
     * @param string $bankCode  Mã BIN ngân hàng
     */
    public static function lookup(string $accountNo, string $bankCode = ''): array
    {
        $accountNo = preg_replace('/\D+/', '', $accountNo) ?? $accountNo;
        if ($accountNo === '') {
            return ['ok' => false, 'message' => 'Vui lòng nhập số tài khoản cần tra cứu.'];
        }

        // 1. Bảng nội bộ (admin tự nhập) — luôn hoạt động
        $where  = ['account_no = ?'];
        $params = [$accountNo];
        if ($bankCode !== '') {
            $where[]  = 'bank_code = ?';
            $params[] = $bankCode;
        }
        $local = Database::fetch(
            'SELECT * FROM bank_accounts WHERE ' . implode(' AND ', $where) . ' AND is_active = 1 LIMIT 1',
            $params
        );
        if ($local) {
            return [
                'ok'           => true,
                'source'       => 'internal',
                'account_no'   => (string)$local['account_no'],
                'account_name' => (string)$local['account_name'],
                'bank_code'    => (string)$local['bank_code'],
                'bank_name'    => (string)$local['bank_name'],
                'branch'       => (string)($local['branch'] ?? ''),
            ];
        }

        // 2. API VietQR — cần client id + api key trong cấu hình
        if ($bankCode === '') {
            return ['ok' => false, 'message' => 'Vui lòng chọn ngân hàng để tra cứu.'];
        }

        $bank = self::findBank($bankCode);
        $bin  = (string)($bank['bin'] ?? $bankCode);
        if (!preg_match('/^\d{6}$/', $bin)) {
            return ['ok' => false, 'message' => 'Ngân hàng này chưa hỗ trợ tra cứu tự động. Vui lòng liên hệ hỗ trợ.'];
        }

        $clientId = (string)setting('vietqr_client_id', '');
        $apiKey   = (string)setting('vietqr_api_key', '');
        if ($clientId === '' || $apiKey === '') {
            return [
                'ok'      => false,
                'message' => 'Hệ thống chưa cấu hình khoá tra cứu ngân hàng. Admin cấu hình tại Quản trị → Cài đặt (vietqr_client_id / vietqr_api_key).',
            ];
        }

        $res = HttpClient::postJsonArray(self::LOOKUP_API, [
            'bin'           => (int)$bin,
            'accountNumber' => $accountNo,
        ], [
            'Accept: application/json',
            'x-client-id: ' . $clientId,
            'x-api-key: ' . $apiKey,
        ], ['timeout' => 15, 'retries' => 1]);

        if (!is_array($res)) {
            return ['ok' => false, 'message' => 'Không kết nối được máy chủ tra cứu. Vui lòng thử lại sau.'];
        }

        $code = (string)($res['code'] ?? '');
        if ($code !== '00') {
            return ['ok' => false, 'message' => (string)($res['desc'] ?? 'Không tìm thấy tài khoản này.')];
        }

        $data = $res['data'] ?? [];
        return [
            'ok'           => true,
            'source'       => 'vietqr',
            'account_no'   => $accountNo,
            'account_name' => (string)($data['accountName'] ?? ''),
            'bank_code'    => $bin,
            'bank_name'    => (string)($bank['shortName'] ?? $bin),
            'branch'       => (string)($data['bankBranch'] ?? ''),
        ];
    }

    /**
     * Tài khoản nội bộ (dùng cho bảng quản trị).
     */
    public static function internalAccounts(string $q = '', int $limit = 50): array
    {
        if ($q !== '') {
            return Database::fetchAll(
                'SELECT * FROM bank_accounts WHERE is_active = 1 AND (account_no LIKE ? OR account_name LIKE ? OR bank_name LIKE ?)
                 ORDER BY bank_name LIMIT ' . (int)$limit,
                ['%' . $q . '%', '%' . $q . '%', '%' . $q . '%']
            );
        }
        return Database::fetchAll(
            'SELECT * FROM bank_accounts WHERE is_active = 1 ORDER BY bank_name LIMIT ' . (int)$limit
        );
    }
}
