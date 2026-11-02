<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\CSRF;
use App\Core\Database;
use App\Core\Wallet;
use App\Services\CsoService;

/**
 * Trang CSO / đối tác: tạo mã, xem hoa hồng, rút thưởng.
 */
class CsoController
{
    public function index(): void
    {
        $userId  = (int)Auth::id();
        $summary = CsoService::summary($userId);

        $balance = Wallet::balance($userId);

        view('cso/index', compact('summary', 'balance'));
    }

    public function create(): void
    {
        CSRF::verifyOrFail();

        $userId = (int)Auth::id();
        $name   = trim((string)($_POST['name'] ?? ''));

        if ($name === '') {
            flash('error', 'Vui lòng nhập tên hiển thị.');
            redirect(url('/cso'));
        }

        $result = CsoService::create($userId, $name);

        if ($result['success']) {
            flash('success', $result['message']);
        } else {
            flash('error', $result['message']);
        }

        redirect(url('/cso'));
    }

    /**
     * Trang giới thiệu cho người được giới thiệu (dùng mã CSO khi đăng ký).
     */
    public function referrals(): void
    {
        $userId = (int)Auth::id();

        $list = Database::fetchAll(
            'SELECT r.*, u.username, u.full_name, u.created_at AS joined_at,
                    (SELECT COALESCE(SUM(amount),0) FROM wallet_transactions
                     WHERE user_id = u.id AND type = "deposit") AS total_deposit
             FROM referrals r JOIN users u ON u.id = r.referred_id
             WHERE r.referrer_id = ? ORDER BY r.id DESC',
            [$userId]
        );

        $account = CsoService::accountFor($userId);

        view('cso/referrals', compact('list', 'account'));
    }

    /**
     * Rút hoa hồng về ví.
     */
    public function withdrawCommission(): void
    {
        CSRF::verifyOrFail();

        $userId  = (int)Auth::id();
        $account = CsoService::accountFor($userId);

        if (!$account) {
            flash('error', 'Bạn chưa có tài khoản CSO.');
            redirect(url('/cso'));
        }

        $pending = (float)Database::fetchColumn(
            'SELECT COALESCE(SUM(amount),0) FROM cso_commissions WHERE cso_id = ? AND status = "approved"',
            [$account['id']]
        );

        if ($pending <= 0) {
            flash('error', 'Chưa có hoa hồng nào được duyệt để rút.');
            redirect(url('/cso'));
        }

        try {
            Wallet::apply(
                $userId,
                'deposit',
                $pending,
                'Rút hoa hồng CSO',
                'cso_commission',
                (string)$account['id']
            );
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
            redirect(url('/cso'));
        }

        Database::update('cso_commissions', ['status' => 'paid'], 'cso_id = :c AND status = "approved"', ['c' => $account['id']]);

        flash('success', 'Đã rút ' . money($pending) . ' về ví.');
        redirect(url('/cso'));
    }
}
