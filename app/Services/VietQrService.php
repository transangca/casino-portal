<?php
namespace App\Services;

use App\Core\Database;

/**
 * Dịch vụ tạo mã QR thanh toán VietQR (napas 906).
 *
 * API: POST https://api.vietqr.io/v2/generate
 * Body: { acqId, accountNo, accountName, bank, template, amount, note }
 *
 * - acqId  : mã ngân hàng (bin) của tài khoản nhận, VD "970436" (MB)
 * - bank   : mã ngân hàng, VD "MB"
 * - template: compact2 | qr_image | qr_deep | qr_web
 *
 * Trả về: qrCode (chuỗi QR), qrDataURL (ảnh PNG base64), imgURL, webLink
 */
class VietQrService
{
    private const API_URL = 'https://api.vietqr.io/v2/generate';
    private const BANKS_URL = 'https://api.vietqr.io/v2/banks';

    /** @var array|null Danh sách ngân hàng (cache) */
    private static ?array $banks = null;

    /**
     * Tạo mã QR thanh toán.
     *
     * @param string $accountNo   Số tài khoản nhận tiền
     * @param string $accountName Tên chủ tài khoản
     * @param string $acqId       Mã ngân hàng (bin), VD 970436
     * @param string $bank        Mã ngân hàng, VD MB
     * @param float  $amount      Số tiền (0 = không gắn số tiền)
     * @param string $note        Nội dung chuyển khoản
     * @param string $template    compact2 | qr_image | qr_deep | qr_web
     */
    public static function create(
        string $accountNo,
        string $accountName,
        string $acqId,
        string $bank,
        float $amount = 0.0,
        string $note = '',
        string $template = 'compact2'
    ): ?array {
        $payload = [
            'acqId'       => $acqId,
            'accountNo'   => $accountNo,
            'accountName' => $accountName,
            'bank'        => $bank,
            'template'    => $template,
        ];

        if ($amount > 0) {
            $payload['amount'] = (int)round($amount);
        }
        if ($note !== '') {
            $payload['note'] = mb_substr($note, 0, 50);
        }

        $res = HttpClient::postJson(self::API_URL, $payload, [
            'Accept: application/json',
        ], ['timeout' => 20]);

        if (!$res) {
            return null;
        }

        $json = json_decode($res, true);
        if (!is_array($json) || (string)($json['code'] ?? '') !== '00') {
            logger('VietQR error: ' . $res, 'warning');
            return null;
        }

        $data = $json['data'] ?? [];
        return [
            'qrCode'    => (string)($data['qrCode'] ?? ''),
            'qrDataURL' => (string)($data['qrDataURL'] ?? ''),
            'imgURL'    => (string)($data['imgURL'] ?? ''),
            'webLink'   => (string)($data['webLink'] ?? ''),
            'bank'      => $bank,
            'account'   => $accountNo,
        ];
    }

    /**
     * Danh sách ngân hàng hỗ trợ (cache trong DB để không gọi mỗi lần).
     */
    public static function banks(): array
    {
        if (self::$banks !== null) {
            return self::$banks;
        }

        $cached = Database::fetch('SELECT value FROM settings WHERE name = "vietqr_banks"');
        if ($cached && (time() - (int)($cached['updated_at'] ?? 0)) < 86400) {
            $decoded = json_decode((string)$cached['value'], true);
            if (is_array($decoded)) {
                return self::$banks = $decoded;
            }
        }

        $res = HttpClient::get(self::BANKS_URL, ['Accept: application/json'], ['timeout' => 20]);
        $list = [];
        if ($res) {
            $json = json_decode($res, true);
            foreach (($json['data'] ?? []) as $b) {
                if (empty($b['code'])) {
                    continue;
                }
                $list[] = [
                    'code'       => (string)$b['code'],
                    'name'       => (string)($b['name'] ?? $b['shortName'] ?? $b['code']),
                    'short_name' => (string)($b['shortName'] ?? $b['code']),
                    'bin'        => (string)($b['bin'] ?? ''),
                    'logo'       => (string)($b['logo'] ?? ''),
                    'swift'      => (string)($b['swift_code'] ?? ''),
                ];
            }
        }

        if ($list) {
            Database::query(
                'INSERT INTO settings (name, value, updated_at) VALUES ("vietqr_banks", ?, NOW())
                 ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW()',
                [json_encode($list, JSON_UNESCAPED_UNICODE)]
            );
        }

        return self::$banks = $list;
    }

    /**
     * Tìm ngân hàng theo mã hoặc tên.
     */
    public static function findBank(string $code): ?array
    {
        foreach (self::banks() as $b) {
            if (strcasecmp($b['code'], $code) === 0) {
                return $b;
            }
        }
        return null;
    }
}
