<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\CSRF;
use App\Core\Database;
use App\Services\VirtualBankService;

/**
 * Dịch vụ Bank ảo — thuê tài khoản ngân hàng ảo qua API HeyBank v2.
 *
 * Người dùng:
 *  - /services/virtual-bank              → danh sách + tạo + gia hạn + thông báo biến động
 *  - /services/virtual-bank/account/{id}  → chi tiết 1 tài khoản
 *
 * Admin:
 *  - /admin/virtual-bank                  → cấu hình ACCESS_KEY + thống kê
 */
class VirtualBankController
{
    // ================= Người dùng =================

    public function index(): void
    {
        $userId = (int)Auth::id();
        $tab    = (string)($_GET['tab'] ?? 'accounts');

        // Tự đồng bộ danh sách tài khoản khi admin bật (chỉ 1 lần mỗi 5 phút)
        $synced = 0;
        if (
            VirtualBankService::configured()
            && setting('heybank_auto_sync', '1') === '1'
            && VirtualBankService::shouldAutoSync($userId)
        ) {
            VirtualBankService::syncAccounts($userId);
            VirtualBankService::markAutoSync($userId);
            $synced = 1;
        }

        $accounts     = VirtualBankService::myAccounts($userId);
        $transactions = VirtualBankService::localTransactions($userId);
        $orders       = VirtualBankService::myOrders($userId);
        $prices       = VirtualBankService::rentalPrices();
        $types        = VirtualBankService::types();

        view('services/virtual-bank', [
            'title'        => 'Bank ảo',
            'tab'          => $tab,
            'accounts'     => $accounts,
            'transactions' => $transactions,
            'orders'       => $orders,
            'prices'       => $prices,
            'bankTypes'    => $types['bankTypes'] ?? VirtualBankService::BANK_TYPES,
            'bankNames'    => $types['bankNames'] ?? [],
            'configured'   => VirtualBankService::configured(),
            'enabled'      => VirtualBankService::enabled(),
            'synced'       => $synced,
        ]);
    }

    /**
     * Đồng bộ danh sách tài khoản từ API về hệ thống.
     */
    public function sync(): void
    {
        $userId = (int)Auth::id();
        $search = trim((string)($_GET['q'] ?? ''));

        $res = VirtualBankService::syncAccounts($userId, $search);
        flash($res['ok'] ? 'success' : 'error', $res['message']);

        redirect(url('/services/virtual-bank?tab=accounts'));
    }

    /**
     * Tạo tài khoản ngân hàng ảo mới.
     */
    public function create(): void
    {
        CSRF::verifyOrFail();

        $res = VirtualBankService::createAccount((int)Auth::id(), $_POST);
        flash($res['ok'] ? 'success' : 'error', $res['message']);

        redirect(url('/services/virtual-bank?tab=accounts'));
    }

    /**
     * Gia hạn tài khoản.
     */
    public function extend(): void
    {
        CSRF::verifyOrFail();

        $id     = (int)($_POST['account_id'] ?? 0);
        $period = trim((string)($_POST['period'] ?? '1day'));

        $res = VirtualBankService::extendAccount((int)Auth::id(), $id, $period);
        flash($res['ok'] ? 'success' : 'error', $res['message']);

        redirect(url('/services/virtual-bank?tab=accounts'));
    }

    /**
     * Cập nhật thông tin tài khoản (số dư, tên, số TK, mật khẩu/OTP).
     */
    public function update(): void
    {
        CSRF::verifyOrFail();

        $id   = (int)($_POST['account_id'] ?? 0);
        $data = [];

        foreach (['bank_username', 'bank_number', 'account_balance', 'password_decryption', 'password_level_two'] as $k) {
            if (isset($_POST[$k]) && $_POST[$k] !== '') {
                $data[$k] = $_POST[$k];
            }
        }

        $res = VirtualBankService::updateAccount((int)Auth::id(), $id, $data);
        flash($res['ok'] ? 'success' : 'error', $res['message']);

        redirect(url('/services/virtual-bank?tab=accounts'));
    }

    /**
     * Tạo thông báo biến động số dư.
     */
    public function notify(): void
    {
        CSRF::verifyOrFail();

        $id   = (int)($_POST['account_id'] ?? 0);
        $data = [
            'amount'         => (float)($_POST['amount'] ?? 0),
            'sender_name'    => (string)($_POST['sender_name'] ?? ''),
            'sender_account' => (string)($_POST['sender_account'] ?? ''),
            'note'           => (string)($_POST['note'] ?? ''),
        ];

        $res = VirtualBankService::createNotification((int)Auth::id(), $id, $data);
        flash($res['ok'] ? 'success' : 'error', $res['message']);

        redirect(url('/services/virtual-bank?tab=transactions'));
    }

    /**
     * Xoá tài khoản.
     */
    public function delete(): void
    {
        CSRF::verifyOrFail();

        $res = VirtualBankService::deleteAccount((int)Auth::id(), (int)($_POST['account_id'] ?? 0));
        flash($res['ok'] ? 'success' : 'error', $res['message']);

        redirect(url('/services/virtual-bank?tab=accounts'));
    }

    /**
     * Upload avatar.
     */
    public function uploadAvatar(): void
    {
        CSRF::verifyOrFail();

        $id   = (int)($_POST['account_id'] ?? 0);
        $file = $_FILES['avatar'] ?? [];

        $res = VirtualBankService::uploadAvatar((int)Auth::id(), $id, $file);
        flash($res['ok'] ? 'success' : 'error', $res['message']);

        redirect(url('/services/virtual-bank?tab=accounts'));
    }

    /**
     * Xoá avatar.
     */
    public function removeAvatar(): void
    {
        CSRF::verifyOrFail();

        $res = VirtualBankService::removeAvatar((int)Auth::id(), (int)($_POST['account_id'] ?? 0));
        flash($res['ok'] ? 'success' : 'error', $res['message']);

        redirect(url('/services/virtual-bank?tab=accounts'));
    }

    /**
     * Đồng bộ giao dịch từ API.
     */
    public function syncTransactions(): void
    {
        $res = VirtualBankService::syncTransactions((int)Auth::id());
        flash($res['ok'] ? 'success' : 'error', $res['message']);

        redirect(url('/services/virtual-bank?tab=transactions'));
    }

    /**
     * Chi tiết tài khoản (dùng modal, trả về HTML).
     */
    public function detail(): void
    {
        $id     = (int)($_GET['id'] ?? 0);
        $userId = (int)Auth::id();

        $acc = VirtualBankService::findAccount($userId, $id);
        if (!$acc) {
            abort(404, 'Không tìm thấy tài khoản');
        }

        $remote = VirtualBankService::accountDetail((int)$acc['remote_id'], $userId);

        view('services/virtual-bank-detail', [
            'title'   => 'Chi tiết tài khoản',
            'account' => $acc,
            'remote'  => $remote,
            'secret'  => VirtualBankService::revealSecret($acc['password_enc']),
            'otp'     => VirtualBankService::revealSecret($acc['otp_enc']),
        ]);
    }

    // ================= Admin =================

    /**
     * Trang quản trị: cấu hình ACCESS_KEY + thống kê.
     */
    public function admin(): void
    {
        $accounts = VirtualBankService::allAccounts();
        $stats    = VirtualBankService::stats();

        view('admin/virtual-bank', [
            'title'    => 'Dịch vụ Bank ảo',
            'accounts' => $accounts,
            'stats'    => $stats,
            'baseUrl'  => VirtualBankService::baseUrl(),
            'hasKey'   => VirtualBankService::configured(),
        ], 'layouts/admin');
    }

    /**
     * Lưu cấu hình.
     */
    public function adminSave(): void
    {
        CSRF::verifyOrFail();

        $baseUrl = trim((string)($_POST['base_url'] ?? ''));
        if ($baseUrl !== '' && !preg_match('#^https?://#i', $baseUrl)) {
            flash('error', 'Base URL không hợp lệ.');
            redirect(url('/admin/virtual-bank'));
        }
        if ($baseUrl !== '') {
            setting_set('heybank_base_url', rtrim($baseUrl, '/'), 'Bank ảo');
        }

        $key = trim((string)($_POST['access_key'] ?? ''));
        if ($key !== '') {
            VirtualBankService::saveAccessKey($key);
        }

        setting_set('heybank_service_on', isset($_POST['service_on']) ? '1' : '0', 'Bank ảo');

        flash('success', 'Đã lưu cấu hình dịch vụ Bank ảo.');
        redirect(url('/admin/virtual-bank'));
    }

    /**
     * Thêm file / link tải xuống (ứng dụng ngân hàng).
     */
    public function adminSaveApp(): void
    {
        CSRF::verifyOrFail();

        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') {
            flash('error', 'Vui lòng nhập tên ứng dụng.');
            redirect(url('/admin/virtual-bank'));
        }

        $apps = VirtualBankService::downloadApps();
        $apps[] = [
            'name'      => $name,
            'bank_code' => trim((string)($_POST['bank_code'] ?? '')),
            'type'      => trim((string)($_POST['type'] ?? 'apk')) ?: 'apk',
            'url'       => trim((string)($_POST['url'] ?? '')),
            'note'      => trim((string)($_POST['note'] ?? '')),
        ];

        VirtualBankService::saveDownloadApps($apps);

        flash('success', 'Đã thêm mục tải xuống.');
        redirect(url('/admin/virtual-bank'));
    }

    /**
     * Xoá mục tải xuống.
     */
    public function adminDeleteApp(): void
    {
        CSRF::verifyOrFail();

        $index = (int)($_POST['index'] ?? -1);
        $apps  = VirtualBankService::downloadApps();

        if (isset($apps[$index])) {
            unset($apps[$index]);
            VirtualBankService::saveDownloadApps(array_values($apps));
            flash('success', 'Đã xoá mục tải xuống.');
        } else {
            flash('error', 'Không tìm thấy mục cần xoá.');
        }

        redirect(url('/admin/virtual-bank'));
    }
}
