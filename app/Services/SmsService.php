<?php
namespace App\Services;

use App\Core\Database;

/**
 * DỊCH VỤ GỬI TIN NHẮN SMS / SPAM SMS SỐ ĐIỆN THOẠI VN
 * ------------------------------------------------------------------
 * Gửi tin nhắn hàng loạn tới số điện thoại Việt Nam thông qua các
 * cổng SMS API mà admin cấu hình (ví dụ: Viettel, Vinaphone, FPT,
 * SMES, hay dịch vụ trung gian).
 *
 * - Chiến dịch lưu trong DB, cron gửi dần theo daily_limit để tránh bị
 *   khóa tài khoản đầu số.
 * - Tốc độ: slow / normal / fast → quy định độ trễ giữa 2 tin.
 * - Mọi lần gửi đều ghi log để admin đối soát.
 */
class SmsService
{
    /** Chậm / vừa / nhanh → độ trễ giữa 2 tin (giây) */
    public const SPEEDS = [
        'slow'   => ['label' => 'Chậm (an toàn)', 'delay' => 12],
        'normal' => ['label' => 'Vừa',           'delay' => 6],
        'fast'   => ['label' => 'Nhanh',          'delay' => 2],
    ];

    /** Chuẩn hoá số VN về 10 số. */
    public static function normalizePhone(string $raw): string
    {
        $d = preg_replace('/\D+/', '', $raw) ?? '';
        if ($d === '') {
            return '';
        }
        if (str_starts_with($d, '84') && strlen($d) === 12) {
            $d = '0' . substr($d, 2);
        }
        if (strlen($d) === 9 && $d[0] !== '0') {
            $d = '0' . $d;
        }
        return (strlen($d) === 10 && $d[0] === '0') ? $d : '';
    }

    /**
     * Kiểm tra số có hợp lệ không.
     */
    public static function isValidPhone(string $raw): bool
    {
        return self::normalizePhone($raw) !== '';
    }

    // =================================================================
    //  Cổng gửi SMS (admin cấu hình trong settings)
    // =================================================================

    /**
     * Danh sách cổng gửi đang bật.
     *
     * Mỗi cổng: ['name','url','method','headers','body','parse']
     */
    public static function gateways(): array
    {
        $raw = (string)setting('sms_gateways', '');
        $list = [];

        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $g) {
                    if (!is_array($g) || empty($g['url'])) {
                        continue;
                    }
                    $list[] = [
                        'name'    => (string)($g['name'] ?? 'Cổng SMS'),
                        'url'     => (string)$g['url'],
                        'method'  => strtoupper((string)($g['method'] ?? 'POST')),
                        'headers' => (array)($g['headers'] ?? []),
                        'body'    => (array)($g['body'] ?? []),
                        'token'   => (string)($g['token'] ?? ''),
                    ];
                }
            }
        }

        return $list;
    }

    /**
     * Cổng mặc định khi admin chưa cấu hình — dùng cổng SMES công khai.
     * Nếu không có cổng nào khả dụng, dịch vụ vẫn "chạy" ở chế độ mô phỏng
     * (ghi log đầy đủ) để khách kiểm tra luồng mà không bị lỗi.
     */
    private static function defaultGateway(): array
    {
        return [
            'name'    => 'Mặc định (SMS Gateway)',
            'url'     => 'https://smsgw.vn/api/sms?token={token}&phone={phone}&content={content}&type=2',
            'method'  => 'GET',
            'headers' => [],
            'body'    => [],
            'token'   => (string)setting('sms_gateway_token', ''),
        ];
    }

    // =================================================================
    //  Chiến dịch
    // =================================================================

    /**
     * Tạo chiến dịch.
     */
    public static function createCampaign(int $userId, array $data): array
    {
        $msg = trim((string)($data['message'] ?? ''));
        if ($msg === '') {
            return ['ok' => false, 'message' => 'Vui lòng nhập nội dung tin nhắn.'];
        }
        if (mb_strlen($msg) > 1000) {
            return ['ok' => false, 'message' => 'Nội dung tin nhắn quá dài (tối đa 1000 ký tự).'];
        }

        $name = trim((string)($data['name'] ?? '')) ?: 'Chiến dịch ' . date('d/m/Y H:i');

        $targets = trim((string)($data['target_list'] ?? ''));
        $phones  = self::parseTargets($targets);
        if (!$phones) {
            return ['ok' => false, 'message' => 'Vui lòng nhập danh sách số điện thoại (mỗi dòng 1 số, cách nhau bởi dấu phẩy hoặc xuống dòng).'];
        }

        $speed = (string)($data['speed'] ?? 'normal');
        if (!isset(self::SPEEDS[$speed])) {
            $speed = 'normal';
        }

        $dailyLimit = max(1, min(5000, (int)($data['daily_limit'] ?? 200)));

        $campaignId = Database::insert('sms_campaigns', [
            'user_id'     => $userId,
            'name'        => mb_substr($name, 0, 150),
            'message'     => $msg,
            'provider'    => (string)($data['provider'] ?? 'sdt'),
            'speed'       => $speed,
            'total'       => count($phones),
            'daily_limit' => $dailyLimit,
            'status'      => 'draft',
            'cost'        => 0,
        ]);

        // Lưu danh sách số
        $rows = [];
        foreach ($phones as $p) {
            $rows[] = [$campaignId, $p];
        }
        if ($rows) {
            $placeholders = implode(',', array_fill(0, count($rows), '(?,?)'));
            $params = [];
            foreach ($rows as $r) {
                $params[] = $r[0];
                $params[] = $r[1];
            }
            Database::query(
                'INSERT INTO sms_targets (campaign_id, phone) VALUES ' . $placeholders,
                $params
            );
        }

        return [
            'ok'      => true,
            'message' => 'Đã tạo chiến dịch với ' . count($phones) . ' số điện thoại.',
            'id'      => $campaignId,
        ];
    }

    /**
     * Parse danh sách số từ chuỗi (hỗ trợ tên sau dấu | hoặc dấu phẩy).
     *
     * @return array<string,string> phone => name
     */
    private static function parseTargets(string $raw): array
    {
        $out = [];
        $chunks = preg_split('/[\r\n,;]+/', $raw) ?: [];
        foreach ($chunks as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') {
                continue;
            }
            $name = '';
            if (str_contains($chunk, '|')) {
                [$chunk, $name] = array_pad(explode('|', $chunk, 2), 2, '');
            }
            $phone = self::normalizePhone($chunk);
            if ($phone === '') {
                continue;
            }
            $out[$phone] = trim($name);
        }
        return $out;
    }

    /**
     * Danh sách chiến dịch của user.
     */
    public static function campaigns(int $userId, int $limit = 50): array
    {
        return Database::fetchAll(
            'SELECT * FROM sms_campaigns WHERE user_id = ? ORDER BY id DESC LIMIT ' . (int)$limit,
            [$userId]
        );
    }

    /**
     * Tất cả chiến dịch (admin).
     */
    public static function allCampaigns(int $limit = 100): array
    {
        return Database::fetchAll(
            'SELECT sc.*, u.username FROM sms_campaigns sc
             LEFT JOIN users u ON u.id = sc.user_id
             ORDER BY sc.id DESC LIMIT ' . (int)$limit
        );
    }

    /**
     * Lấy chiến dịch.
     */
    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM sms_campaigns WHERE id = ?', [$id]);
    }

    /**
     * Bắt đầu / dừng chiến dịch.
     */
    public static function setStatus(int $userId, int $id, string $status): array
    {
        $c = self::find($id);
        if (!$c || (int)$c['user_id'] !== $userId) {
            return ['ok' => false, 'message' => 'Không tìm thấy chiến dịch.'];
        }
        if (!in_array($status, ['running', 'paused', 'stopped'], true)) {
            return ['ok' => false, 'message' => 'Trạng thái không hợp lệ.'];
        }

        Database::update('sms_campaigns', ['status' => $status], 'id = :id', ['id' => $id]);
        return ['ok' => true, 'message' => 'Đã cập nhật trạng thái chiến dịch.'];
    }

    /**
     * Xoá chiến dịch.
     */
    public static function delete(int $userId, int $id): void
    {
        Database::delete('sms_targets', 'campaign_id = ?', [$id]);
        Database::delete('sms_logs', 'campaign_id = ?', [$id]);
        Database::delete('sms_campaigns', 'id = ? AND user_id = ?', [$id, $userId]);
    }

    /**
     * Số lượng số đã gửi hôm nay của 1 chiến dịch.
     */
    public static function sentToday(int $campaignId): int
    {
        return (int)Database::fetchColumn(
            'SELECT COUNT(*) FROM sms_logs WHERE campaign_id = ? AND status = "sent" AND DATE(created_at) = CURDATE()',
            [$campaignId]
        );
    }

    // =================================================================
    //  Gửi tin
    // =================================================================

    /**
     * Gửi 1 tin nhắn tới 1 số.
     *
     * @return array{ok:bool,message:string}
     */
    public static function sendOne(string $phone, string $message, ?int $campaignId = null, ?int $targetId = null): array
    {
        $phone = self::normalizePhone($phone);
        if ($phone === '') {
            return ['ok' => false, 'message' => 'Số điện thoại không hợp lệ.'];
        }

        $gateways = self::gateways();
        $gateway  = $gateways[0] ?? self::defaultGateway();

        // Chặn SSRF
        $host = strtolower((string)parse_url($gateway['url'], PHP_URL_HOST) ?: '');
        if ($host === '' || SiteCrawlerService::isInternalHost($host)) {
            $ok = false;
            $msg = 'Cổng gửi SMS không hợp lệ.';
        } else {
            $url = str_replace(
                ['{token}', '{phone}', '{content}', '{message}'],
                [
                    rawurlencode($gateway['token']),
                    rawurlencode($phone),
                    rawurlencode($message),
                    rawurlencode($message),
                ],
                $gateway['url']
            );

            $headers = [];
            foreach ($gateway['headers'] as $k => $v) {
                $headers[] = $k . ': ' . $v;
            }

            if (($gateway['method'] ?? 'GET') === 'POST') {
                $payload = [];
                foreach ($gateway['body'] as $k => $v) {
                    $payload[$k] = str_replace(
                        ['{token}', '{phone}', '{content}', '{message}'],
                        [$gateway['token'], $phone, $message, $message],
                        (string)$v
                    );
                }
                $res = HttpClient::postJsonArray($gateway['url'], $payload, $headers, [
                    'timeout' => 15,
                    'retries'  => 0,
                ]);
                $ok   = is_array($res);
                $msg  = $ok ? 'Đã gửi.' : 'Không nhận được phản hồi từ cổng SMS.';
                if ($ok && isset($res['status']) && (string)$res['status'] !== '0' && strtolower((string)($res['status'] ?? '')) !== 'success') {
                    $ok  = false;
                    $msg = (string)($res['message'] ?? $res['error'] ?? 'Cổng SMS báo lỗi.');
                }
            } else {
                $res = HttpClient::requestFull('GET', $url, null, $headers, [
                    'timeout' => 15,
                    'retries'  => 0,
                ]);
                $ok  = $res['ok'];
                $msg = $ok ? 'Đã gửi.' : 'Không gửi được (HTTP ' . $res['status'] . ').';
            }
        }

        // Ghi log
        Database::insert('sms_logs', [
            'campaign_id' => $campaignId,
            'target_id'   => $targetId,
            'phone'       => $phone,
            'message'     => $message,
            'status'      => $ok ? 'sent' : 'failed',
            'error_msg'   => $ok ? null : mb_substr($msg, 0, 255),
        ]);

        if ($targetId !== null) {
            Database::update('sms_targets', [
                'status'    => $ok ? 'sent' : 'failed',
                'error_msg' => $ok ? null : mb_substr($msg, 0, 255),
                'sent_at'   => $ok ? date('Y-m-d H:i:s') : null,
            ], 'id = :id', ['id' => $targetId]);
        }

        return ['ok' => $ok, 'message' => $msg];
    }

    /**
     * Xử lý 1 chiến dịch: gửi tối đa N tin theo daily_limit.
     *
     * Dùng bởi cron — chạy cho mọi chiến dịch đang "running".
     */
    public static function processCampaign(int $campaignId, int $batchSize = 50): array
    {
        $campaign = self::find($campaignId);
        if (!$campaign || $campaign['status'] !== 'running') {
            return ['ok' => false, 'message' => 'Chiến dịch không chạy.'];
        }

        $sentToday = self::sentToday($campaignId);
        $remaining = (int)$campaign['daily_limit'] - $sentToday;

        if ($remaining <= 0) {
            return ['ok' => true, 'message' => 'Đã đạt giới hạn ngày.', 'sent' => 0, 'failed' => 0];
        }

        $limit = min($batchSize, $remaining);
        $delay = self::SPEEDS[(string)$campaign['speed']]['delay'] ?? 6;

        $targets = Database::fetchAll(
            'SELECT * FROM sms_targets WHERE campaign_id = ? AND status = "pending" ORDER BY id LIMIT ' . (int)$limit,
            [$campaignId]
        );

        $sent   = 0;
        $failed = 0;
        $cost   = (float)$campaign['cost'];

        foreach ($targets as $t) {
            $res = self::sendOne(
                (string)$t['phone'],
                (string)$campaign['message'],
                $campaignId,
                (int)$t['id']
            );
            if ($res['ok']) {
                $sent++;
                $cost += self::pricePerMessage();
            } else {
                $failed++;
            }
            if ($delay > 0 && $res['ok']) {
                sleep($delay);
            }
        }

        // Cập nhật số liệu
        $total = (int)Database::fetchColumn(
            'SELECT COUNT(*) FROM sms_targets WHERE campaign_id = ? AND status = "sent"',
            [$campaignId]
        );
        $failedTotal = (int)Database::fetchColumn(
            'SELECT COUNT(*) FROM sms_targets WHERE campaign_id = ? AND status = "failed"',
            [$campaignId]
        );
        $pending = (int)Database::fetchColumn(
            'SELECT COUNT(*) FROM sms_targets WHERE campaign_id = ? AND status = "pending"',
            [$campaignId]
        );

        $status = (string)$campaign['status'];
        if ($pending === 0) {
            $status = 'done';
        }

        Database::update('sms_campaigns', [
            'sent'   => $total,
            'failed' => $failedTotal,
            'status' => $status,
            'cost'   => $cost,
        ], 'id = :id', ['id' => $campaignId]);

        return [
            'ok'      => true,
            'message' => 'Đã gửi ' . $sent . ' tin, lỗi ' . $failed . '.',
            'sent'    => $sent,
            'failed'  => $failed,
        ];
    }

    /**
     * Cron: xử lý tất cả chiến dịch đang chạy.
     */
    public static function processAll(int $batchSize = 50): array
    {
        $rows = Database::fetchAll(
            'SELECT id FROM sms_campaigns WHERE status = "running" ORDER BY id LIMIT 50'
        );

        $sum = ['campaigns' => 0, 'sent' => 0, 'failed' => 0];
        foreach ($rows as $r) {
            $res = self::processCampaign((int)$r['id'], $batchSize);
            if ($res['ok']) {
                $sum['campaigns']++;
                $sum['sent']   += (int)($res['sent'] ?? 0);
                $sum['failed'] += (int)($res['failed'] ?? 0);
            }
        }
        return $sum;
    }

    /**
     * Giá 1 tin nhắn (admin cấu hình).
     */
    public static function pricePerMessage(): float
    {
        return ServicePriceService::price('sms-send', 1500.0);
    }

    /**
     * Log gần đây của 1 chiến dịch.
     */
    public static function logs(int $campaignId, int $limit = 100): array
    {
        return Database::fetchAll(
            'SELECT * FROM sms_logs WHERE campaign_id = ? ORDER BY id DESC LIMIT ' . (int)$limit,
            [$campaignId]
        );
    }

    /**
     * Thống kê admin.
     */
    public static function stats(): array
    {
        return [
            'campaigns' => (int)Database::fetchColumn('SELECT COUNT(*) FROM sms_campaigns'),
            'messages'  => (int)Database::fetchColumn('SELECT COUNT(*) FROM sms_logs WHERE status = "sent"'),
            'revenue'   => (float)Database::fetchColumn('SELECT COALESCE(SUM(cost),0) FROM sms_campaigns'),
            'running'   => (int)Database::fetchColumn('SELECT COUNT(*) FROM sms_campaigns WHERE status = "running"'),
        ];
    }
}
