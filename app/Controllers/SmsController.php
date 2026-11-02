<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\CSRF;
use App\Core\Wallet;
use App\Services\ServicePriceService;
use App\Services\SmsService;

/**
 * Dịch vụ gửi tin nhắn SMS / spam SMS số điện thoại Việt Nam.
 *
 * Chiến dịch được xử lý bằng cron (database/cron_sms.php).
 */
class SmsController
{
    public function index(): void
    {
        view('services/sms', [
            'title'     => 'Gửi tin nhắn SMS',
            'campaigns' => SmsService::campaigns((int)Auth::id()),
            'speeds'    => SmsService::SPEEDS,
            'price'     => SmsService::pricePerMessage(),
            'balance'   => Wallet::balance((int)Auth::id()),
            'gateways'  => count(SmsService::gateways()),
        ]);
    }

    /**
     * Tạo chiến dịch.
     */
    public function create(): void
    {
        CSRF::verifyOrFail();

        $userId = (int)Auth::id();
        $res    = SmsService::createCampaign($userId, $_POST);

        if (!$res['ok']) {
            flash('error', $res['message']);
            redirect(url('/services/sms'));
        }

        // Tính phí theo số tin gửi
        $price = SmsService::pricePerMessage() * (int)$res['total'];
        if (Wallet::balance($userId) < $price) {
            flash('error', 'Số dư không đủ. Cần ' . money($price) . ' cho ' . $res['total'] . ' tin nhắn.');
            redirect(url('/services/sms'));
        }

        try {
            Wallet::apply(
                $userId,
                'adjustment',
                -$price,
                'Gói gửi tin nhắn ' . $res['total'] . ' tin',
                'sms_campaign',
                (string)$res['id']
            );
        } catch (\Throwable $e) {
            flash('error', 'Lỗi trừ phí: ' . $e->getMessage());
            redirect(url('/services/sms'));
        }

        // Tự động bắt đầu chạy (cron sẽ xử lý tiếp)
        SmsService::setStatus($userId, (int)$res['id'], 'running');

        flash('success', $res['message'] . ' Hệ thống sẽ gửi dần theo giới hạn hằng ngày.');
        redirect(url('/services/sms'));
    }

    /**
     * Bắt đầu / tạm dừng / dừng chiến dịch.
     */
    public function action(): void
    {
        CSRF::verifyOrFail();

        $id     = (int)($_POST['id'] ?? 0);
        $action = (string)($_POST['action'] ?? '');

        $status = match ($action) {
            'start'  => 'running',
            'pause'  => 'paused',
            'stop'   => 'stopped',
            default  => '',
        };

        if ($status === '') {
            flash('error', 'Hành động không hợp lệ.');
            redirect(url('/services/sms'));
        }

        $res = SmsService::setStatus((int)Auth::id(), $id, $status);
        flash($res['ok'] ? 'success' : 'error', $res['message']);
        redirect(url('/services/sms'));
    }

    /**
     * Xoá chiến dịch.
     */
    public function delete(): void
    {
        CSRF::verifyOrFail();
        SmsService::delete((int)Auth::id(), (int)($_POST['id'] ?? 0));
        flash('success', 'Đã xoá chiến dịch.');
        redirect(url('/services/sms'));
    }

    /**
     * Chi tiết chiến dịch + log.
     */
    public function detail(int $id): void
    {
        $campaign = SmsService::find($id);
        if (!$campaign || ((int)$campaign['user_id'] !== (int)Auth::id() && !Auth::isAdmin())) {
            abort(404, 'Không tìm thấy chiến dịch');
        }

        view('services/sms-detail', [
            'title'    => (string)$campaign['name'],
            'campaign' => $campaign,
            'logs'     => SmsService::logs($id, 200),
            'sentToday'=> SmsService::sentToday($id),
        ]);
    }
}
