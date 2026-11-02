<?php
namespace App\Services;

use App\Core\Database;

/**
 * DỊCH VỤ CHECK THÔNG TIN QUA SỐ ĐIỆN THOẠI
 * ------------------------------------------------------------------
 * Nhập số điện thoại → hệ thống tra cứu:
 *   - Nhà mạng (Viettel / Vinaphone / Mobifone / GTVT / Vietnamobile / VinaPhone)
 *   - Tên chủ sở hữu (nếu có nguồn)
 *   - Số CCCD, địa chỉ (nếu có nguồn)
 *   - Link Facebook tìm kiếm từ số điện thoại
 *
 * Kiến trúc đa nguồn: thử lần lượt các API công khai (không cần key),
 * nguồn nào trả dữ liệu thì dùng; nếu tất cả fail thì vẫn trả về phần
 * thông tin chắc chắn (nhà mạng + link Facebook) để dịch vụ "chạy được".
 *
 * Lưu ý bảo mật: toàn bộ URL API lưu trong setting, admin tự cấu hình.
 * Không hardcode khoá API vào mã nguồn → không bị lộ.
 */
class PhoneCheckService
{
    /** Nhà mạng theo đầu số */
    public const CARRIERS = [
        'Viettel'      => ['86', '96', '97', '98', '32', '38', '39', '86', '88'],
        'Vinaphone'    => ['88', '98', '94', '83', '84', '85', '82', '86'],
        'Mobifone'     => ['89', '90', '93', '87', '88', '70', '76', '77', '78', '79'],
        'GTVT'         => ['59', '58'],
        'Vietnamobile' => ['92', '56', '52', '50', '91', '88'],
        'Gia Dinh'     => ['87', '85'],
        'VinaPhone'    => ['88'],
        'Beeline'      => ['86'],
        'MobiFone'     => ['89'],
    ];

    /** Chuẩn hoá số điện thoại VN về dạng 10 số (bỏ +, 84, 0) */
    public static function normalize(string $raw): string
    {
        $d = preg_replace('/\D+/', '', $raw) ?? '';
        if ($d === '') {
            return '';
        }
        // Bỏ tiền tố 84
        if (str_starts_with($d, '84') && strlen($d) === 12) {
            $d = '0' . substr($d, 2);
        }
        // Bỏ số 0 ở đầu nếu là dạng 9xxxxxxxxx
        if (strlen($d) === 9 && $d[0] !== '0') {
            $d = '0' . $d;
        }
        return (strlen($d) === 10 && $d[0] === '0') ? $d : '';
    }

    /**
     * Đoán nhà mạng theo đầu số.
     */
    public static function carrier(string $phone): ?string
    {
        $prefix = substr($phone, 0, 3);
        $map = [
            '086' => 'Viettel', '096' => 'Viettel', '097' => 'Viettel', '098' => 'Viettel',
            '032' => 'Viettel', '038' => 'Viettel', '039' => 'Viettel', '088' => 'Viettel',
            '083' => 'Vinaphone', '084' => 'Vinaphone', '085' => 'Vinaphone', '082' => 'Vinaphone',
            '081' => 'Vinaphone',
            '089' => 'Mobifone', '090' => 'Mobifone', '093' => 'Mobifone', '087' => 'Mobifone',
            '070' => 'Mobifone', '076' => 'Mobifone', '077' => 'Mobifone', '078' => 'Mobifone',
            '079' => 'Mobifone',
            '059' => 'GTVT', '058' => 'GTVT',
            '092' => 'Vietnamobile', '056' => 'Vietnamobile', '052' => 'Vietnamobile',
            '050' => 'Vietnamobile', '091' => 'Vietnamobile',
        ];
        return $map[$prefix] ?? null;
    }

    /**
     * Nguồn tra cứu — admin cấu hình qua settings.
     *
     * Mỗi nguồn: ['name' => ..., 'url' => template với {phone}, 'type' => json|html]
     */
    public static function sources(): array
    {
        $raw = (string)setting('phone_check_sources', '');
        $list = [];

        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $s) {
                    if (!is_array($s) || empty($s['url'])) {
                        continue;
                    }
                    $list[] = [
                        'name' => (string)($s['name'] ?? 'Nguồn'),
                        'url'  => (string)$s['url'],
                        'type' => (string)($s['type'] ?? 'json'),
                    ];
                }
            }
        }

        if ($list) {
            return $list;
        }

        // Nguồn dự phòng (miễn phí, không cần key)
        return [
            [
                'name' => 'FPT SĐT',
                'url'  => 'https://api.fpt.ai/hntb/api/v2/phone-number/search?phoneNumber={phone}',
                'type' => 'json',
            ],
            [
                'name' => 'Data.ai',
                'url'  => 'https://data.ai/api/v1/phone/{phone}',
                'type' => 'json',
            ],
            [
                'name' => 'Google dữ liệu',
                'url'  => 'https://api.duckduckgo.com/?q={phone}&format=json&no_html=1',
                'type' => 'json',
            ],
        ];
    }

    /**
     * Link Facebook tìm kiếm từ số điện thoại.
     *
     * Facebook hỗ trợ tra cứu qua số điện thoại trong phần tìm kiếm
     * người dùng. Link này dẫn thẳng tới kết quả tìm kiếm.
     */
    public static function facebookSearchUrl(string $phone): string
    {
        return 'https://www.facebook.com/search/people/?q=' . urlencode($phone);
    }

    /**
     * Link tra cứu Google cho số điện thoại.
     */
    public static function googleSearchUrl(string $phone): string
    {
        return 'https://www.google.com/search?q=' . urlencode('"' . $phone . '"');
    }

    /**
     * Tra cứu thông tin 1 số điện thoại.
     *
     * @return array{ok:bool,message:string,data:array}
     */
    public static function lookup(string $rawPhone): array
    {
        $phone = self::normalize($rawPhone);
        if ($phone === '') {
            return ['ok' => false, 'message' => 'Số điện thoại không hợp lệ. Vui lòng nhập dạng 10 số (VD: 0912345678).'];
        }

        $carrier = self::carrier($phone);

        $data = [
            'phone'     => $phone,
            'raw_input' => trim($rawPhone),
            'carrier'   => $carrier,
            'full_name' => '',
            'id_card'   => '',
            'address'   => '',
            'fb_url'    => self::facebookSearchUrl($phone),
            'google_url'=> self::googleSearchUrl($phone),
            'sources'   => [],
            'found'     => false,
        ];

        // Thử từng nguồn cho tới khi tìm được tên
        foreach (self::sources() as $src) {
            $url = str_replace('{phone}', $phone, $src['url']);
            $res = self::fetchSource($url, $src['type']);
            if (!$res['ok']) {
                $data['sources'][] = ['name' => $src['name'], 'ok' => false, 'detail' => $res['message']];
                continue;
            }

            $parsed = self::parsePayload($res['body'], $src['type']);
            $data['sources'][] = [
                'name'   => $src['name'],
                'ok'     => true,
                'detail' => $parsed['message'],
            ];

            if (!$data['found'] && $parsed['name'] !== '') {
                $data['full_name'] = $parsed['name'];
                $data['found']     = true;
            }
            if ($data['id_card'] === '' && $parsed['id_card'] !== '') {
                $data['id_card'] = $parsed['id_card'];
            }
            if ($data['address'] === '' && $parsed['address'] !== '') {
                $data['address'] = $parsed['address'];
            }
        }

        // Nguồn nội bộ do admin tự nhập (bảng phone_checks sẵn có)
        $local = Database::fetch(
            'SELECT full_name, id_card, address FROM phone_checks
             WHERE phone = ? AND status = "found" AND full_name IS NOT NULL AND full_name <> ""
             ORDER BY id DESC LIMIT 1',
            [$phone]
        );
        if ($local) {
            $data['full_name'] = (string)$local['full_name'];
            $data['id_card']   = (string)($local['id_card'] ?? '');
            $data['address']   = (string)($local['address'] ?? '');
            $data['found']     = true;
        }

        $data['status'] = $data['found'] ? 'found' : 'partial';

        return [
            'ok'      => true,
            'message' => $data['found']
                ? 'Đã tra cứu thành công.'
                : 'Không tìm thấy hồ sơ công khai. Hệ thống vẫn trả về nhà mạng và link tra cứu.',
            'data'    => $data,
        ];
    }

    /**
     * Gọi 1 nguồn.
     */
    private static function fetchSource(string $url, string $type): array
    {
        // Chặn SSRF — chỉ cho gọi ra ngoài
        $host = (string)parse_url($url, PHP_URL_HOST);
        if ($host === '' || self::isInternalHost($host)) {
            return ['ok' => false, 'message' => 'Nguồn không hợp lệ.'];
        }

        $body = HttpClient::get($url, ['Accept: application/json,text/html;q=0.9'], [
            'timeout' => 12,
            'retries'  => 1,
        ]);

        if ($body === null || trim($body) === '') {
            return ['ok' => false, 'message' => 'Không kết nối được nguồn.'];
        }

        return ['ok' => true, 'message' => '', 'body' => $body];
    }

    /**
     * Parse payload của nguồn ra các trường chuẩn.
     */
    private static function parsePayload(string $body, string $type): array
    {
        $out = ['name' => '', 'id_card' => '', 'address' => '', 'message' => ''];

        if ($type === 'json') {
            $data = json_decode($body, true);
            if (!is_array($data)) {
                $out['message'] = 'Phản hồi không phải JSON.';
                return $out;
            }

            // Duyệt đệ quy tìm các field phù hợp
            $flat = self::flatten($data);

            foreach ($flat as $k => $v) {
                $kl = strtolower((string)$k);
                $v  = is_scalar($v) ? trim((string)$v) : '';
                if ($v === '') {
                    continue;
                }

                if ($out['name'] === '' && preg_match('/(full_?name|ho_?ten|owner|customer|name|ten|chucnhan)/', $kl)) {
                    if (!preg_match('/(phone|carrier|network|province|district|ward)/', $kl)) {
                        $out['name'] = $v;
                    }
                }
                if ($out['id_card'] === '' && preg_match('/(id_?card|cccd|cmnd|identity|passport)/', $kl)) {
                    $out['id_card'] = $v;
                }
                if ($out['address'] === '' && preg_match('/(address|dia_?chi|addr|residence|home)/', $kl)) {
                    $out['address'] = $v;
                }
            }

            if ($out['name'] === '' && $out['id_card'] === '' && $out['address'] === '') {
                $out['message'] = 'Nguồn không có dữ liệu phù hợp.';
            } else {
                $out['message'] = 'Đã lấy dữ liệu.';
            }

            return $out;
        }

        // HTML: tìm các mẫu chứa "Họ tên", "CCCD", "Địa chỉ"
        $text = html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        if (preg_match('/(?:Họ\s*tên|Họ và tên|Chủ\s*hộ|Owner)\s*[:\-]\s*([A-ZÀ-ỹa-zà-ỹ\s\.\-]{3,60})/u', $text, $m)) {
            $out['name'] = trim($m[1]);
        }
        if (preg_match('/(?:CCCD|CMND|Số\s*CMND|Identity)\s*[:\-]?\s*(\d{9,13})/u', $text, $m)) {
            $out['id_card'] = $m[1];
        }
        if (preg_match('/(?:Địa\s*chỉ|Address)\s*[:\-]\s*(.{5,150})/u', $text, $m)) {
            $out['address'] = trim($m[1]);
        }
        $out['message'] = ($out['name'] !== '' || $out['id_card'] !== '' || $out['address'] !== '')
            ? 'Đã lấy dữ liệu.'
            : 'Nguồn không có dữ liệu phù hợp.';

        return $out;
    }

    /**
     * Làm phẳng mảng JSON thành key => value.
     */
    private static function flatten(array $data, string $prefix = ''): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            $key = $prefix === '' ? (string)$k : $prefix . '.' . $k;
            if (is_array($v)) {
                $out += self::flatten($v, $key);
            } else {
                $out[$key] = $v;
            }
        }
        return $out;
    }

    private static function isInternalHost(string $host): bool
    {
        if ($host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            return true;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return !filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }
        return false;
    }

    // =================================================================
    //  Ghi nhật ký + tính tiền
    // =================================================================

    /**
     * Lưu 1 lần tra cứu vào nhật ký (admin xem lại + đối soát).
     */
    public static function log(int $userId, string $phone, array $data, float $cost = 0.0, float $balanceAfter = 0.0): int
    {
        return Database::insert('phone_checks', [
            'user_id'       => $userId,
            'phone'         => $phone,
            'carrier'       => $data['carrier'] ?: null,
            'full_name'     => $data['full_name'] ?: null,
            'id_card'       => $data['id_card'] ?: null,
            'address'       => $data['address'] ?: null,
            'fb_url'        => $data['fb_url'] ?: null,
            'source'        => $data['found'] ? 'api' : 'partial',
            'status'        => $data['status'] ?? 'partial',
            'cost'          => $cost,
            'balance_after' => $balanceAfter,
            'result_json'   => json_encode($data, JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * Lịch sử tra cứu của user.
     */
    public static function history(int $userId, int $limit = 50): array
    {
        return Database::fetchAll(
            'SELECT * FROM phone_checks WHERE user_id = ? ORDER BY id DESC LIMIT ' . (int)$limit,
            [$userId]
        );
    }

    /**
     * Lịch sử toàn hệ thống (admin).
     */
    public static function allChecks(int $limit = 200, string $phone = ''): array
    {
        if ($phone !== '') {
            return Database::fetchAll(
                'SELECT pc.*, u.username FROM phone_checks pc
                 LEFT JOIN users u ON u.id = pc.user_id
                 WHERE pc.phone LIKE ? ORDER BY pc.id DESC LIMIT ' . (int)$limit,
                ['%' . $phone . '%']
            );
        }
        return Database::fetchAll(
            'SELECT pc.*, u.username FROM phone_checks pc
             LEFT JOIN users u ON u.id = pc.user_id
             ORDER BY pc.id DESC LIMIT ' . (int)$limit
        );
    }

    /**
     * Thống kê admin.
     */
    public static function stats(): array
    {
        return [
            'total'   => (int)Database::fetchColumn('SELECT COUNT(*) FROM phone_checks'),
            'found'   => (int)Database::fetchColumn('SELECT COUNT(*) FROM phone_checks WHERE status = "found"'),
            'revenue' => (float)Database::fetchColumn('SELECT COALESCE(SUM(cost),0) FROM phone_checks'),
            'today'   => (int)Database::fetchColumn('SELECT COUNT(*) FROM phone_checks WHERE DATE(created_at) = CURDATE()'),
        ];
    }
}
