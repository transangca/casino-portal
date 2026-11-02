<?php
namespace App\Controllers;

use App\Core\CSRF;
use App\Core\Database;
use App\Services\AutoBankService;

/**
 * Dịch vụ Auto Bank — cổng thanh toán tự động (https://api.sieuthicode.net/).
 *
 * Chỉ dành cho quản trị viên:
 *  - /admin/auto-bank              → danh sách tài khoản + giao dịch + đối soát
 *  - /admin/auto-bank/save         → thêm / sửa tài khoản ngân hàng
 *  - /admin/auto-bank/sync         → đồng bộ giao dịch
 *  - /admin/auto-bank/test         → test kết nối
 *  - /admin/auto-bank/reconcile    → đối soát & duyệt nạp tiền tự động
 */
class AutoBankController
{
    // ================= Quản trị =================

    public function index(): void
    {
        $accountId = (int)($_GET['account_id'] ?? 0);
        $tab       = (string)($_GET['tab'] ?? 'accounts');
        $type      = (string)($_GET['type'] ?? '');

        view('admin/auto-bank', [
            'title'        => 'Auto Bank — Cổng thanh toán tự động',
            'tab'          => $tab,
            'accounts'     => AutoBankService::allAccounts(),
            'transactions' => AutoBankService::transactions($accountId, 150, $type),
            'pending'      => AutoBankService::pendingDeposits(50),
            'stats'        => AutoBankService::stats(),
            'baseUrl'      => AutoBankService::baseUrl(),
            'banks'        => AutoBankService::BANKS,
            'versions'     => AutoBankService::VERSIONS,
            'accountId'    => $accountId,
            'txnType'      => $type,
            'enabled'      => AutoBankService::enabled(),
        ], 'layouts/admin');
    }

    /**
     * Thêm / sửa tài khoản ngân hàng.
     */
    public function save(): void
    {
        CSRF::verifyOrFail();

        try {
            $id = AutoBankService::saveAccount([
                'id'            => (int)($_POST['id'] ?? 0),
                'bank_key'      => (string)($_POST['bank_key'] ?? ''),
                'api_version'   => (string)($_POST['api_version'] ?? 'v1'),
                'label'         => (string)($_POST['label'] ?? ''),
                'token'         => (string)($_POST['token'] ?? ''),
                'password'      => (string)($_POST['password'] ?? ''),
                'stk'           => (string)($_POST['stk'] ?? ''),
                'match_content' => (string)($_POST['match_content'] ?? ''),
                'min_amount'    => (float)($_POST['min_amount'] ?? 0),
                'max_amount'    => (float)($_POST['max_amount'] ?? 0),
                'auto_approve'  => isset($_POST['auto_approve']),
                'is_active'     => isset($_POST['is_active']),
                'is_sandbox'    => isset($_POST['is_sandbox']),
                'note'          => (string)($_POST['note'] ?? ''),
            ]);
            flash('success', 'Đã lưu tài khoản #' . $id . '.');
        } catch (\Throwable $e) {
            flash('error', 'Lỗi: ' . $e->getMessage());
        }

        redirect(url('/admin/auto-bank?tab=accounts'));
    }

    /**
     * Xoá tài khoản.
     */
    public function delete(): void
    {
        CSRF::verifyOrFail();

        $id = (int)($_POST['id'] ?? 0);
        AutoBankService::deleteAccount($id);
        flash('success', 'Đã xoá tài khoản #' . $id . '.');

        redirect(url('/admin/auto-bank?tab=accounts'));
    }

    /**
     * Đồng bộ giao dịch từ ngân hàng.
     */
    public function sync(): void
    {
        CSRF::verifyOrFail();

        $id  = (int)($_POST['id'] ?? 0);
        $res = AutoBankService::syncAccount($id, true);

        flash($res['ok'] ? 'success' : 'error', $res['message']);
        redirect(url('/admin/auto-bank?tab=transactions&account_id=' . $id));
    }

    /**
     * Đồng bộ tất cả tài khoản đang bật.
     */
    public function syncAll(): void
    {
        CSRF::verifyOrFail();

        $total = 0;
        $ok    = 0;
        $errs  = [];

        foreach (AutoBankService::allAccounts() as $acc) {
            if ((int)$acc['is_active'] !== 1) {
                continue;
            }
            $res = AutoBankService::syncAccount((int)$acc['id'], true);
            $total++;
            if ($res['ok']) {
                $ok++;
            } else {
                $errs[] = $acc['label'] . ': ' . $res['message'];
            }
        }

        $msg = 'Đã đồng bộ ' . $ok . '/' . $total . ' tài khoản.';
        if ($errs) {
            $msg .= ' Lỗi: ' . implode(' | ', array_slice($errs, 0, 3));
        }

        flash($ok > 0 ? 'success' : 'error', $msg);
        redirect(url('/admin/auto-bank?tab=transactions'));
    }

    /**
     * Đối soát & duyệt nạp tiền tự động cho 1 tài khoản.
     */
    public function reconcile(): void
    {
        CSRF::verifyOrFail();

        $id  = (int)($_POST['id'] ?? 0);
        $n   = AutoBankService::reconcile($id);

        flash('success', 'Đã đối soát — duyệt ' . $n . ' phiếu nạp tiền.');
        redirect(url('/admin/auto-bank?tab=deposits'));
    }

    /**
     * Test kết nối tới API (không lưu gì).
     */
    public function test(): void
    {
        if (!is_ajax()) {
            redirect(url('/admin/auto-bank?tab=accounts'));
        }

        $bankKey = (string)($_GET['bank_key'] ?? '');
        $version = (string)($_GET['api_version'] ?? 'v1');
        $token   = trim((string)($_GET['token'] ?? ''));
        $password= trim((string)($_GET['password'] ?? ''));
        $stk     = trim((string)($_GET['stk'] ?? ''));
        $sandbox = ($_GET['sandbox'] ?? '') === '1';

        if (!$sandbox && $token === '') {
            json_error('Vui lòng nhập Token API.');
        }

        $res = AutoBankService::fetch($bankKey, $version, [
            'token'    => $token ?: 'sandbox_token_stcpay',
            'password' => $password ?: 'sandbox_password',
            'stk'      => $stk ?: '0123456789',
            'sandbox'  => $sandbox,
        ]);

        if (!$res['ok']) {
            json_error($res['message']);
        }

        json_success([
            'count' => count($res['rows']),
            'rows'  => array_slice($res['rows'], 0, 10),
        ], 'Kết nối thành công — ' . count($res['rows']) . ' giao dịch.');
    }

    /**
     * Lưu cấu hình chung.
     */
    public function saveSettings(): void
    {
        CSRF::verifyOrFail();

        $baseUrl = trim((string)($_POST['base_url'] ?? ''));
        if ($baseUrl !== '' && !preg_match('#^https?://#i', $baseUrl)) {
            flash('error', 'Base URL không hợp lệ.');
            redirect(url('/admin/auto-bank?tab=settings'));
        }
        if ($baseUrl !== '') {
            setting_set('autobank_base_url', rtrim($baseUrl, '/'), 'Auto Bank');
        }

        setting_set('autobank_service_on', isset($_POST['service_on']) ? '1' : '0', 'Auto Bank');
        setting_set('autobank_auto_approve', isset($_POST['auto_approve']) ? '1' : '0', 'Auto Bank');

        $cronToken = trim((string)($_POST['cron_token'] ?? ''));
        if ($cronToken !== '') {
            setting_set('autobank_cron_token', $cronToken, 'Auto Bank', 'password');
        }

        $fee = (float)($_POST['service_fee'] ?? 0);
        if ($fee < 0) {
            $fee = 0;
        }
        setting_set('autobank_service_fee', (string)(int)$fee, 'Auto Bank');

        $interval = max(5, min(600, (int)($_POST['poll_interval'] ?? 30)));
        setting_set('autobank_poll_interval', (string)$interval, 'Auto Bank');

        flash('success', 'Đã lưu cấu hình Auto Bank.');
        redirect(url('/admin/auto-bank?tab=settings'));
    }

    /**
     * Duyệt 1 phiếu nạp tiền thủ công (từ trang Auto Bank).
     */
    public function approveDeposit(): void
    {
        CSRF::verifyOrFail();

        $id  = (int)($_POST['id'] ?? 0);
        $ok  = AutoBankService::approveDeposit($id);

        flash($ok ? 'success' : 'error', $ok ? 'Đã duyệt phiếu nạp tiền #' . $id . '.' : 'Không thể duyệt phiếu nạp tiền #' . $id . '.');
        redirect(url('/admin/auto-bank?tab=deposits'));
    }

    // ================= Cron (không cần đăng nhập) =================

    /**
     * Endpoint cho cron job quét tự động.
     *
     * GET /api/auto-bank/cron?token=xxx
     *
     * Token lấy từ setting `autobank_cron_token` (hoặc env AUTOBANK_CRON_TOKEN).
     */
    public function cron(): void
    {
        $secret = (string)setting('autobank_cron_token', '');
        if ($secret === '') {
            $secret = (string)config('autobank.cron_token', '');
        }

        $given = (string)($_GET['token'] ?? ($_SERVER['HTTP_X_CRON_TOKEN'] ?? ''));
        if ($secret === '' || !hash_equals($secret, $given)) {
            json_error('Token cron không hợp lệ.', 403);
        }

        $result = self::runAll();
        json_success($result, 'Đã quét ' . $result['accounts'] . ' tài khoản.');
    }

    /**
     * Quét tất cả tài khoản đang bật + đối soát nạp tiền.
     *
     * @return array thống kê
     */
    public static function runAll(): array
    {
        $stats = [
            'accounts'  => 0,
            'ok'        => 0,
            'failed'    => 0,
            'new'       => 0,
            'approved'  => 0,
            'errors'    => [],
        ];

        foreach (AutoBankService::allAccounts() as $acc) {
            if ((int)$acc['is_active'] !== 1) {
                continue;
            }

            $stats['accounts']++;
            $res = AutoBankService::syncAccount((int)$acc['id'], true);

            if ($res['ok']) {
                $stats['ok']++;
                $stats['new']      += (int)($res['new'] ?? 0);
                $stats['approved'] += (int)($res['matched'] ?? 0);
            } else {
                $stats['failed']++;
                $stats['errors'][] = $acc['label'] . ': ' . $res['message'];
            }
        }

        return $stats;
    }
}
