<?php
namespace App\Services;

use App\Core\Database;

/**
 * Dịch vụ hạ tầng: domain, hosting cPanel, VPS, mail, SSL.
 *
 * Phần "mua domain tự động" và "check domain còn hoạt động" dùng DNS thật
 * (không dựng game nội bộ): tra WHOIS/RDAP để xác định domain còn đăng ký,
 * tra DNS để xác định domain còn phân giải.
 */
class InfraService
{
    /** TLD phổ biến + giá tham khảo (VNĐ) */
    public const TLD_PRICE = [
        'com'  => 250000, 'net' => 280000, 'org' => 260000, 'vn'  => 900000,
        'com.vn' => 850000, 'net.vn' => 800000, 'org.vn' => 780000,
        'io'   => 1800000, 'ai' => 2400000, 'co' => 950000, 'app' => 1200000,
        'dev'  => 1100000, 'shop' => 900000, 'xyz' => 400000, 'me' => 350000,
        'cc'   => 300000, 'tv' => 500000, 'gg' => 450000, 'site' => 350000,
        'live' => 600000, 'online' => 500000, 'store' => 700000, 'tech' => 900000,
        'pro'  => 1900000, 'biz' => 800000, 'info' => 2000000,
    ];

    /**
     * Tách tld từ domain (hỗ trợ .com.vn, .co.uk...).
     */
    public static function splitTld(string $domain): array
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain) ?? $domain;
        $domain = explode('/', $domain)[0];
        $domain = preg_replace('#:\d+$#', '', $domain) ?? $domain;

        $parts = explode('.', $domain);
        if (count($parts) < 2) {
            return [$domain, ''];
        }

        // 2 phần cuối là TLD
        $tld = $parts[count($parts) - 2] . '.' . $parts[count($parts) - 1];
        // 3 phần cuối nếu phần thứ 2 là mã quốc gia 2 ký tự (vn, uk, com.vn...)
        if (count($parts) >= 3) {
            $last3 = $parts[count($parts) - 3] . '.' . $parts[count($parts) - 2] . '.' . $parts[count($parts) - 1];
            if (isset(self::TLD_PRICE[$last3])) {
                $tld = $last3;
            }
        }

        return [$domain, $tld];
    }

    /**
     * Kiểm tra domain còn hoạt động / còn đăng ký không.
     *
     * @return array ['domain','tld','dns_active','registered','expires_at','status','note']
     */
    public static function checkDomain(string $domain): array
    {
        [$domain, $tld] = self::splitTld($domain);
        $result = [
            'domain'      => $domain,
            'tld'         => $tld,
            'dns_active'  => false,
            'registered'  => null,
            'expires_at'  => null,
            'status'      => 'unavailable',
            'note'        => '',
            'price'       => self::TLD_PRICE[$tld] ?? 0,
        ];

        if ($domain === '' || !preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $domain)) {
            $result['note'] = 'Tên miền không hợp lệ.';
            return $result;
        }

        // 1. Tra DNS — domain còn phân giải?
        $records = @dns_get_record($domain, DNS_A | DNS_AAAA | DNS_CNAME);
        if (is_array($records) && $records) {
            $result['dns_active'] = true;
            $result['registered'] = true;
            $result['status'] = 'active';
            $result['note'] = 'Domain đang hoạt động (có bản ghi DNS).';
        } else {
            $result['note'] = 'Không tìm thấy bản ghi DNS.';
        }

        // 2. Tra RDAP (nhiều TLD hỗ trợ RDAP)
        $rdap = self::rdap($domain);
        if ($rdap !== null) {
            $result['registered'] = (bool)$rdap['registered'];
            $result['expires_at'] = $rdap['expires_at'];
            if ($rdap['registered']) {
                $result['status'] = 'unavailable';
                $result['note'] = 'Domain đã được đăng ký, còn hiệu lực đến ' . ($rdap['expires_at'] ?: 'không rõ') . '.';
            } else {
                $result['status'] = 'available';
                $result['note'] = 'Domain chưa được đăng ký — có thể mua.';
            }
        } elseif ($result['dns_active']) {
            $result['status'] = 'active';
        } else {
            $result['status'] = 'available';
            $result['note'] = $result['note'] ?: 'Domain chưa có DNS — có thể đăng ký.';
        }

        return $result;
    }

    /**
     * Tra RDAP để biết domain đã đăng ký chưa.
     */
    private static function rdap(string $domain): ?array
    {
        // RDAP bootstrap: gọi trực tiếp endpoint của các registry phổ biến
        $endpoints = [
            'https://rdap.verisign.com/com/v1/domain/',
            'https://rdap.verisign.com/net/v1/domain/',
            'https://rdap.org/domain/',
        ];

        foreach ($endpoints as $base) {
            $url = $base . rawurlencode($domain);
            $res = HttpClient::getJson($url, [], [], ['timeout' => 10, 'retries' => 1]);
            if ($res === null) {
                continue;
            }
            if (isset($res['errorCode']) || isset($res['title'])) {
                // 404 → domain không tồn tại trong registry này
                continue;
            }
            $expires = null;
            foreach (($res['events'] ?? []) as $ev) {
                if (($ev['eventAction'] ?? '') === 'expiration') {
                    $expires = substr((string)($ev['eventDate'] ?? ''), 0, 10);
                }
            }
            return ['registered' => true, 'expires_at' => $expires];
        }

        return null;
    }

    /**
     * Danh sách gói hạ tầng.
     */
    public static function plans(?string $kind = null): array
    {
        if ($kind !== null && $kind !== '') {
            return Database::fetchAll(
                'SELECT * FROM infra_plans WHERE kind = ? AND is_active = 1 ORDER BY sort_order, price_month',
                [$kind]
            );
        }
        return Database::fetchAll('SELECT * FROM infra_plans WHERE is_active = 1 ORDER BY kind, sort_order');
    }

    /**
     * Tạo đơn domain.
     */
    public static function orderDomain(int $userId, string $domain, int $years = 1, string $registrar = ''): array
    {
        $check = self::checkDomain($domain);
        if ($check['status'] === 'active') {
            return ['ok' => false, 'message' => 'Domain này đang hoạt động, không thể đặt mua.'];
        }

        $price = $check['price'] * max(1, $years);
        $orderNo = 'D' . date('ymd') . strtoupper(bin2hex(random_bytes(4)));

        $id = Database::insert('domain_orders', [
            'user_id'        => $userId,
            'domain'         => $check['domain'],
            'tld'            => $check['tld'],
            'registrar'      => $registrar ?: 'Cổng đăng ký nội bộ',
            'years'          => max(1, $years),
            'price'          => $price,
            'status'         => 'ordered',
            'dns_checked_at' => now(),
        ]);

        ActivityLogService::log(ActivityLogService::SCOPE_SYSTEM, 'infra.domain_order', $userId, $check['domain'], [
            'price' => $price,
        ]);

        return ['ok' => true, 'message' => 'Đã tạo đơn ' . $orderNo . '. Admin sẽ xử lý.', 'id' => $id];
    }

    public static function myDomains(int $userId): array
    {
        return Database::fetchAll(
            'SELECT * FROM domain_orders WHERE user_id = ? ORDER BY id DESC LIMIT 100',
            [$userId]
        );
    }
}
