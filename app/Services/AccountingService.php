<?php
namespace App\Services;

use App\Core\Auth;
use App\Core\Database;

/**
 * Phần mềm kế toán nhỏ lẻ (MISA-style).
 *
 * Khi người dùng bấm menu → toàn bộ website bị thay thế bằng giao diện kế toán
 * fullscreen. Thoát bằng nút "Thoát" hoặc phím ESC.
 *
 * Dữ liệu là dữ liệu quan trọng → sao lưu tự động liên tục mỗi 1–2 giờ vào
 * storage driver (mặc định local, có thể chuyển sang S3/Drive). Mỗi bản sao lưu
 * lưu mã số phiếu để khi khách mất dữ liệu có thể tra cứu lại.
 */
class AccountingService
{
    /** Loại tài khoản chuẩn của doanh nghiệp VN */
    public const ACCOUNTS = [
        '111' => 'Tiền mặt',
        '112' => 'Tiền gửi ngân hàng',
        '113' => 'Tiền đang chuyển',
        '131' => 'Phải thu khách hàng',
        '133' => 'Thuế GTGT được khấu trừ',
        '136' => 'Phải thu nội bộ',
        '138' => 'Phải thu về tài sản',
        '140' => 'Hàng tồn kho',
        '141' => 'Chi phí tạo sản phẩm',
        '142' => 'Chi phí dự án',
        '151' => 'Chi phí sửa chữa',
        '152' => 'Chi phí phương tiện vận tải',
        '153' => 'Chi phí công cụ dụng cụ',
        '154' => 'Chi phí khác',
        '155' => 'Chi phí khác',
        '211' => 'Phải trả người bán',
        '214' => 'Phải trả người lao động',
        '215' => 'Phải trả phần thuế',
        '219' => 'Phải trả khác',
        '331' => 'Phải trả dài hạn',
        '401' => 'Vốn góp chủ sở hữu',
        '411' => 'Lợi nhuận chưa phân phối',
        '421' => 'Doanh thu bán hàng',
        '511' => 'Giá vốn bán hàng',
        '632' => 'Chi phí bán hàng',
        '642' => 'Chi phí quản lý doanh nghiệp',
        '711' => 'Thuế TNDN',
        '731' => 'Chi phất sinh khác',
        '811' => 'Chi phí khác',
    ];

    /** Loại phiếu kế toán */
    public const DOC_TYPES = [
        'journal'  => 'Phiếu journal',
        'receipt'  => 'Phiếu thu',
        'payment'  => 'Phiếu chi',
        'invoice'  => 'Hóa đơn bán hàng',
        'bill'     => 'Hóa đơn mua hàng',
    ];

    // ================= Tài khoản =================

    public static function accounts(): array
    {
        return Database::fetchAll(
            'SELECT * FROM accounting_accounts WHERE is_active = 1 ORDER BY code'
        );
    }

    public static function accountLabel(string $code): string
    {
        return self::ACCOUNTS[$code] ?? $code;
    }

    // ================= Phiếu =================

    public static function documents(string $type = '', int $limit = 100): array
    {
        $where  = ['1=1'];
        $params = [];
        if ($type !== '') {
            $where[]  = 'doc_type = ?';
            $params[] = $type;
        }
        return Database::fetchAll(
            'SELECT d.*, u.username FROM accounting_documents d
             LEFT JOIN users u ON u.id = d.user_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY d.id DESC LIMIT ' . (int)$limit,
            $params
        );
    }

    public static function document(int $id): ?array
    {
        $doc = Database::fetch('SELECT * FROM accounting_documents WHERE id = ?', [$id]);
        if (!$doc) {
            return null;
        }
        $doc['lines'] = Database::fetchAll(
            'SELECT * FROM accounting_lines WHERE document_id = ? ORDER BY id',
            [$id]
        );
        return $doc;
    }

    /**
     * Tạo phiếu kế toán. Mã số phiếu tự sinh, dùng để tra cứu khi khách mất dữ liệu.
     */
    public static function createDocument(array $data): array
    {
        $type = (string)($data['doc_type'] ?? 'journal');
        if (!isset(self::DOC_TYPES[$type])) {
            $type = 'journal';
        }

        $lines = $data['lines'] ?? [];
        if (!is_array($lines) || count($lines) < 2) {
            return ['ok' => false, 'message' => 'Phiếu kế toán cần ít nhất 2 dòng (Nợ và Có).'];
        }

        $debit  = 0.0;
        $credit = 0.0;
        $clean  = [];
        foreach ($lines as $l) {
            $acc = trim((string)($l['account_code'] ?? ''));
            $dv  = (float)($l['debit'] ?? 0);
            $cv  = (float)($l['credit'] ?? 0);
            if ($acc === '' || ($dv <= 0 && $cv <= 0)) {
                continue;
            }
            $debit  += $dv;
            $credit += $cv;
            $clean[] = [
                'account_code' => $acc,
                'account_name' => self::accountLabel($acc),
                'debit'        => $dv,
                'credit'       => $cv,
                'note'         => trim((string)($l['note'] ?? '')),
            ];
        }

        if (count($clean) < 2) {
            return ['ok' => false, 'message' => 'Phiếu kế toán không hợp lệ.'];
        }
        if (abs($debit - $credit) > 1.0) {
            return ['ok' => false, 'message' => 'Tổng Nợ (' . money($debit) . ') phải bằng tổng Có (' . money($credit) . ').'];
        }

        $docNo = 'KT' . date('ymd') . strtoupper(bin2hex(random_bytes(3)));

        $id = Database::transaction(function () use ($type, $docNo, $data, $clean, $debit, $credit) {
            $id = Database::insert('accounting_documents', [
                'doc_no'    => $docNo,
                'doc_type'  => $type,
                'doc_date'  => (string)($data['doc_date'] ?? date('Y-m-d')),
                'partner'   => trim((string)($data['partner'] ?? '')) ?: null,
                'note'      => trim((string)($data['note'] ?? '')) ?: null,
                'total_debit'  => $debit,
                'total_credit' => $credit,
                'user_id'   => Auth::id(),
                'status'    => 'posted',
            ]);

            foreach ($clean as $l) {
                Database::insert('accounting_lines', [
                    'document_id'  => $id,
                    'account_code' => $l['account_code'],
                    'account_name' => $l['account_name'],
                    'debit'        => $l['debit'],
                    'credit'       => $l['credit'],
                    'note'         => $l['note'] ?: null,
                ]);
            }
            return $id;
        });

        return ['ok' => true, 'message' => 'Đã lưu phiếu ' . $docNo, 'id' => $id, 'doc_no' => $docNo];
    }

    /**
     * Sổ cái — bảng tổng hợp theo tài khoản.
     */
    public static function ledger(string $accountCode = '', string $from = '', string $to = ''): array
    {
        $where  = ['1=1'];
        $params = [];
        if ($accountCode !== '') {
            $where[]  = 'l.account_code = ?';
            $params[] = $accountCode;
        }
        if ($from !== '') {
            $where[]  = 'd.doc_date >= ?';
            $params[] = $from;
        }
        if ($to !== '') {
            $where[]  = 'd.doc_date <= ?';
            $params[] = $to;
        }
        return Database::fetchAll(
            'SELECT l.*, d.doc_no, d.doc_type, d.doc_date, d.partner
             FROM accounting_lines l
             JOIN accounting_documents d ON d.id = l.document_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY d.doc_date, d.id, l.id LIMIT 500',
            $params
        );
    }

    /**
     * Báo cáo bảng cân đối (tài sản = nợ – dư nợ; nguồn vốn = dư có).
     */
    public static function balanceSheet(string $date = ''): array
    {
        $date = $date !== '' ? $date : date('Y-m-d');

        $rows = Database::fetchAll(
            'SELECT l.account_code,
                    SUM(l.debit) AS total_debit,
                    SUM(l.credit) AS total_credit
             FROM accounting_lines l
             JOIN accounting_documents d ON d.id = l.document_id
             WHERE d.doc_date <= ?
             GROUP BY l.account_code',
            [$date]
        );

        $assets = [];
        $liab   = [];
        $equity = [];
        $revenue = [];
        $expense = [];

        foreach ($rows as $r) {
            $code = (string)$r['account_code'];
            $d = (float)$r['total_debit'];
            $c = (float)$r['total_credit'];
            $balance = $d - $c;

            $first = substr($code, 0, 1);
            $entry = ['code' => $code, 'name' => self::accountLabel($code), 'debit' => $d, 'credit' => $c, 'balance' => $balance];

            if (in_array($first, ['1'], true)) {
                $assets[] = $entry;
            } elseif (in_array($first, ['2'], true)) {
                $liab[] = $entry;
            } elseif (in_array($first, ['4'], true)) {
                if ($code === '421') {
                    $revenue[] = $entry;
                } else {
                    $equity[] = $entry;
                }
            } elseif (in_array($first, ['5', '6', '7', '8'], true)) {
                $expense[] = $entry;
            }
        }

        $sum = static function (array $rows, string $key): float {
            $t = 0.0;
            foreach ($rows as $r) { $t += (float)$r[$key]; }
            return $t;
        };

        return [
            'date'     => $date,
            'assets'   => $assets,
            'liab'     => $liab,
            'equity'   => $equity,
            'revenue'  => $revenue,
            'expense'  => $expense,
            'total_asset'    => $sum($assets, 'debit') - $sum($assets, 'credit'),
            'total_liab'      => $sum($liab, 'credit') - $sum($liab, 'debit'),
            'total_equity'    => $sum($equity, 'credit') - $sum($equity, 'debit'),
            'total_revenue'   => $sum($revenue, 'credit') - $sum($revenue, 'debit'),
            'total_expense'   => $sum($expense, 'debit') - $sum($expense, 'credit'),
        ];
    }

    /**
     * Lợi nhuận tháng.
     */
    public static function profitLoss(string $from, string $to): array
    {
        $rows = Database::fetchAll(
            'SELECT l.account_code, SUM(l.debit) d, SUM(l.credit) c
             FROM accounting_lines l JOIN accounting_documents d ON d.id = l.document_id
             WHERE d.doc_date BETWEEN ? AND ? GROUP BY l.account_code',
            [$from, $to]
        );
        $revenue = 0.0;
        $expense = 0.0;
        foreach ($rows as $r) {
            $code = (string)$r['account_code'];
            $net  = (float)$r['c'] - (float)$r['d'];
            if ($code === '421') {
                $revenue += $net;
            } elseif (substr($code, 0, 1) >= '5') {
                $expense += -$net;
            }
        }
        return [
            'from' => $from, 'to' => $to,
            'revenue' => $revenue,
            'expense' => $expense,
            'profit'  => $revenue - $expense,
        ];
    }

    // ================= Sao lưu =================

    /**
     * Tạo bản sao lưu dữ liệu kế toán ra storage driver.
     *
     * @return array ['ok'=>bool, 'message'=>string, 'file'=>string]
     */
    public static function backup(): array
    {
        if (setting('backup_enabled', '1') !== '1') {
            return ['ok' => false, 'message' => 'Tính năng sao lưu đang tắt.'];
        }

        $docs  = self::documents('', 100000);
        $lines = Database::fetchAll('SELECT * FROM accounting_lines ORDER BY id LIMIT 200000');

        $payload = [
            'generated_at' => date('c'),
            'app'          => setting('site_name', 'Casino Portal'),
            'documents'    => $docs,
            'lines'        => $lines,
            'checksum'     => substr(md5(json_encode($lines) . json_encode($docs)), 0, 16),
        ];

        $driver = (string)setting('storage_driver', 'local');
        $stamp  = date('Ymd-His');
        $name   = "accounting-backup-{$stamp}.json";

        $path = self::writeToDriver($driver, $name, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        if ($path === '') {
            return ['ok' => false, 'message' => 'Không ghi được bản sao lưu.'];
        }

        Database::insert('system_backups', [
            'scope'      => 'accounting',
            'label'      => 'Kế toán ' . date('d/m/Y H:i'),
            'driver'     => $driver,
            'path'       => $path,
            'size_bytes' => strlen((string)json_encode($payload)),
            'checksum'   => $payload['checksum'],
            'status'     => 'ok',
        ]);

        return [
            'ok'      => true,
            'message' => 'Đã sao lưu ' . count($docs) . ' phiếu kế toán.',
            'file'    => $path,
        ];
    }

    /**
     * Ghi file lên storage driver đang cấu hình.
     */
    private static function writeToDriver(string $driver, string $name, string $content): string
    {
        if ($driver === 'local' || $driver === '') {
            $dir = BASE_PATH . '/storage/backups';
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $path = $dir . '/' . $name;
            if (@file_put_contents($path, $content) !== false) {
                return $path;
            }
            return '';
        }

        // Driver ngoài (S3 / Google Drive) — cấu hình endpoint + secret trong settings
        $endpoint = trim((string)setting('driver_endpoint', ''));
        $secret   = trim((string)setting('driver_secret', ''));
        if ($endpoint === '' || $secret === '') {
            return '';
        }

        try {
            $res = HttpClient::postJson(rtrim($endpoint, '/') . '/upload', [
                'file'   => $name,
                'driver' => $driver,
                'secret' => $secret,
                'body'   => base64_encode($content),
            ]);
            if ($res === null) {
                return '';
            }
            $data = json_decode((string)$res, true);
            return (string)($data['path'] ?? '');
        } catch (\Throwable $e) {
            logger('backup driver failed: ' . $e->getMessage(), 'error');
            return '';
        }
    }

    /**
     * Danh sách bản sao lưu.
     */
    public static function backups(int $limit = 50): array
    {
        return Database::fetchAll(
            'SELECT * FROM system_backups ORDER BY id DESC LIMIT ' . (int)$limit
        );
    }

    /**
     * Tra cứu bản sao lưu theo mã số phiếu (dùng khi khách mất dữ liệu).
     */
    public static function restoreSearch(string $docNo): array
    {
        $rows = Database::fetchAll(
            'SELECT id, doc_no, doc_type, doc_date, partner, total_debit, total_credit, status
             FROM accounting_documents WHERE doc_no LIKE ? ORDER BY id DESC LIMIT 100',
            ['%' . $docNo . '%']
        );
        return $rows;
    }
}
