<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\CSRF;
use App\Core\Database;
use App\Services\OtpRentalService;

/**
 * Dịch vụ thuê số nhận OTP — tích hợp API ViOTP v1.
 *
 * Người dùng:
 *  - /services/otp-rental              → danh sách dịch vụ & thuê số
 *  - /services/otp-rental/rental/{id}   → chi tiết lượt thuê (poll mã OTP)
 *
 * Admin:
 *  - /admin/otp-rental                 → cấu hình token, số dư, thống kê
 */
class OtpRentalController
{
    // ================= Người dùng =================

    public function index(): void
    {
        $userId = (int)Auth::id();

        // Đồng bộ danh mục nếu DB trống hoặc cache đã cũ
        if (OtpRentalService::configured() && count(OtpRentalService::localServices()) === 0) {
            OtpRentalService::services(true);
        }

        $keyword = trim((string)($_GET['q'] ?? ''));
        $page    = max(1, (int)($_GET['page'] ?? 1));

        $catalog  = OtpRentalService::catalog($keyword, $page);
        $popular  = $keyword === '' ? OtpRentalService::popularServices(24) : [];
        $rentals  = OtpRentalService::myRentals($userId);

        view('services/otp-rental', [
            'title'      => 'Thuê số nhận OTP',
            'catalog'    => $catalog,
            'popular'    => $popular,
            'rentals'    => $rentals,
            'carriers'   => OtpRentalService::carriers(),
            'configured' => OtpRentalService::configured(),
            'enabled'    => OtpRentalService::enabled(),
        ]);
    }

    /**
     * Thuê số mới.
     */
    public function rent(): void
    {
        CSRF::verifyOrFail();

        if (!OtpRentalService::enabled()) {
            flash('error', 'Dịch vụ thuê số OTP hiện đang tạm dừng.');
            redirect(url('/services/otp-rental'));
        }

        $serviceId = (int)($_POST['service_id'] ?? 0);
        $carriers  = array_values(array_filter((array)($_POST['carriers'] ?? []), 'strlen'));
        $include   = array_values(array_filter((array)($_POST['include_prefixes'] ?? []), 'strlen'));
        $exclude   = array_values(array_filter((array)($_POST['exclude_prefixes'] ?? []), 'strlen'));

        // Tìm giá dịch vụ
        $service = null;
        foreach (OtpRentalService::services(true) as $s) {
            if ((int)$s['id'] === $serviceId) {
                $service = $s;
                break;
            }
        }
        if (!$service) {
            foreach (OtpRentalService::localServices() as $s) {
                if ((int)$s['id'] === $serviceId) {
                    $service = $s;
                    break;
                }
            }
        }

        if (!$service) {
            flash('error', 'Vui lòng chọn dịch vụ cần thuê.');
            redirect(url('/services/otp-rental'));
        }

        // Giá bán cho khách = giá đối tác + markup %
        $markup    = (float)setting('viotp_markup', '0');
        $unitCost  = (float)$service['price'];
        $sellPrice = $markup > 0 ? (int)ceil($unitCost * (1 + $markup / 100)) : (int)$unitCost;

        $userId  = (int)Auth::id();
        $balance = (float)Database::fetchColumn('SELECT balance FROM users WHERE id = ?', [$userId]);
        if ($balance < $sellPrice) {
            flash('error', 'Số dư không đủ. Cần ' . money($sellPrice) . ', hiện có ' . money($balance) . '.');
            redirect(url('/services/otp-rental'));
        }

        // Trừ tiền ví nội bộ TRƯỚC khi gọi API đối tác.
        // Nếu API lỗi (hết hàng, hết tiền...) → hoàn lại ngay.
        \App\Core\Wallet::apply(
            $userId,
            'adjustment',
            -$sellPrice,
            'Thuê số OTP ' . $service['name'],
            'otp_rental',
            (string)$serviceId
        );

        // Gọi API thuê số (tiền đối tác bị trừ ngay)
        $res = OtpRentalService::rent($serviceId, $carriers, $include, $exclude);

        if (!$res['ok']) {
            // Hoàn tiền khi API đối tác lỗi
            \App\Core\Wallet::apply(
                $userId,
                'adjustment',
                $sellPrice,
                'Hoàn tiền thuê số OTP thất bại',
                'otp_rental_refund',
                (string)$serviceId
            );

            flash('error', '[' . $res['code'] . '] ' . $res['message']);
            redirect(url('/services/otp-rental'));
        }

        $rentalId = OtpRentalService::createRental($userId, [
            'rental_id'     => (int)$res['id'],
            'service_id'    => $serviceId,
            'service_name'  => (string)$service['name'],
            'phone'         => (string)$res['phone'],
            'carriers'      => $carriers,
            'price'         => $sellPrice,
            'status'        => 0,
            'balance_after' => (float)$res['balance'],
        ]);

        flash('success', 'Đã thuê số ' . $res['phone'] . ' cho dịch vụ ' . $service['name'] . '. Đang chờ mã OTP...');

        redirect(url('/services/otp-rental/rental/' . $rentalId));
    }

    /**
     * Chi tiết lượt thuê — trang hiển thị & poll mã OTP.
     */
    public function rental(int $id): void
    {
        $userId = (int)Auth::id();
        $rental = Database::fetch(
            'SELECT * FROM otp_rentals WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );
        if (!$rental) {
            abort(404, 'Không tìm thấy lượt thuê');
        }

        // Nếu vẫn đang chờ, thử lấy mã ngay 1 lần
        if ((int)$rental['status'] === 0) {
            $st = OtpRentalService::status((int)$rental['rental_id']);
            if ($st['ok']) {
                OtpRentalService::updateRental($userId, [
                    'rental_id' => (int)$rental['rental_id'],
                    'status'    => (int)$st['status'],
                    'otp_code'  => (string)$st['code'],
                    'content'   => (string)$st['content'],
                    'is_audio'  => (bool)$st['isAudio'],
                    'message'   => '',
                ]);
                $rental = Database::fetch(
                    'SELECT * FROM otp_rentals WHERE id = ? AND user_id = ?',
                    [$id, $userId]
                );
            }
        }

        view('services/otp-rental-detail', [
            'title'  => 'Lượt thuê #' . $rental['rental_id'],
            'rental' => $rental,
        ]);
    }

    /**
     * API poll lấy mã OTP (dùng bởi JS trên trang chi tiết).
     */
    public function poll(): void
    {
        if (!is_ajax() && !str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')) {
            json_error('Yêu cầu không hợp lệ', 400);
        }

        $userId = (int)Auth::id();
        $id     = (int)($_GET['id'] ?? 0);

        $rental = Database::fetch(
            'SELECT * FROM otp_rentals WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );
        if (!$rental) {
            json_error('Không tìm thấy lượt thuê', 404);
        }

        if ((int)$rental['status'] !== 0) {
            json_success([
                'status'    => (int)$rental['status'],
                'code'      => (string)$rental['otp_code'],
                'content'   => (string)$rental['content'],
                'isAudio'   => (int)$rental['is_audio'] === 1,
                'finished'  => true,
            ]);
        }

        $st = OtpRentalService::status((int)$rental['rental_id']);
        if (!$st['ok']) {
            json_error($st['message'], 200, ['finished' => false, 'retry' => true]);
        }

        OtpRentalService::updateRental($userId, [
            'rental_id' => (int)$rental['rental_id'],
            'status'    => (int)$st['status'],
            'otp_code'  => (string)$st['code'],
            'content'   => (string)$st['content'],
            'is_audio'  => (bool)$st['isAudio'],
            'message'   => '',
        ]);

        json_success([
            'status'   => (int)$st['status'],
            'code'     => (string)$st['code'],
            'content'  => (string)$st['content'],
            'isAudio'  => (bool)$st['isAudio'],
            'finished' => (int)$st['status'] !== 0,
        ]);
    }

    /**
     * Thuê lại số cũ.
     */
    public function rerent(): void
    {
        CSRF::verifyOrFail();

        $rentalId = (int)($_POST['rental_id'] ?? 0);
        $userId   = (int)Auth::id();

        $rental = Database::fetch(
            'SELECT * FROM otp_rentals WHERE id = ? AND user_id = ?',
            [$rentalId, $userId]
        );
        if (!$rental) {
            flash('error', 'Không tìm thấy lượt thuê.');
            redirect(url('/services/otp-rental'));
        }

        $serviceId = (int)$rental['service_id'];
        $phone     = (string)$rental['phone'];

        $res = OtpRentalService::rerent($serviceId, $phone);
        if (!$res['ok']) {
            flash('error', '[' . $res['code'] . '] ' . $res['message']);
            redirect(url('/services/otp-rental/rental/' . $rentalId));
        }

        $markup    = (float)setting('viotp_markup', '0');
        $unitCost  = (float)$rental['price'];
        $sellPrice = $markup > 0 ? (int)ceil($unitCost * (1 + $markup / 100)) : (int)$unitCost;

        $balance = (float)Database::fetchColumn('SELECT balance FROM users WHERE id = ?', [$userId]);
        if ($balance < $sellPrice) {
            flash('error', 'Số dư không đủ để thuê lại. Cần ' . money($sellPrice) . '.');
            redirect(url('/services/otp-rental/rental/' . $rentalId));
        }

        \App\Core\Wallet::apply(
            $userId,
            'adjustment',
            -$sellPrice,
            'Thuê lại số OTP ' . $phone,
            'otp_rental',
            (string)$res['id']
        );

        $newId = OtpRentalService::createRental($userId, [
            'rental_id'     => (int)$res['id'],
            'service_id'    => $serviceId,
            'service_name'  => (string)$rental['service_name'],
            'phone'         => (string)$res['phone'],
            'carriers'      => (string)$rental['carriers'] ? explode(',', (string)$rental['carriers']) : [],
            'price'         => $sellPrice,
            'status'        => 0,
            'balance_after' => (float)$res['balance'],
        ]);

        flash('success', 'Đã thuê lại số ' . $res['phone'] . '. Đang chờ mã OTP...');
        redirect(url('/services/otp-rental/rental/' . $newId));
    }

    /**
     * Xem nhanh số dư ví ViOTP.
     */
    public function partnerBalance(): void
    {
        if (!is_ajax() && !str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')) {
            redirect(url('/services/otp-rental'));
        }

        $res = OtpRentalService::balance();
        if (!$res['ok']) {
            json_error($res['message']);
        }

        json_success([
            'balance' => $res['balance'],
            'text'    => money($res['balance']),
        ]);
    }

    // ================= Admin =================

    public function admin(): void
    {
        $services = OtpRentalService::localServices();
        $rentals  = OtpRentalService::allRentals();
        $stats    = OtpRentalService::stats();
        $balance  = OtpRentalService::balance();

        view('admin/otp-rental', [
            'title'    => 'Dịch vụ thuê số OTP (ViOTP API)',
            'services' => $services,
            'rentals'  => $rentals,
            'stats'    => $stats,
            'balance'  => $balance,
            'baseUrl'  => OtpRentalService::baseUrl(),
            'hasToken' => OtpRentalService::configured(),
        ], 'layouts/admin');
    }

    public function adminSave(): void
    {
        CSRF::verifyOrFail();

        $baseUrl = trim((string)($_POST['base_url'] ?? ''));
        if ($baseUrl !== '' && !preg_match('#^https://#i', $baseUrl)) {
            flash('error', 'Base URL phải dùng HTTPS. Cấu hình khác vẫn đã được lưu.');
        } elseif ($baseUrl !== '') {
            setting_set('viotp_base_url', rtrim($baseUrl, '/'), 'Thuê số OTP');
        }

        $token = trim((string)($_POST['token'] ?? ''));
        if ($token !== '') {
            OtpRentalService::saveToken($token);
        }

        setting_set('viotp_service_on', isset($_POST['service_on']) ? '1' : '0', 'Thuê số OTP');

        $markup = (float)($_POST['markup'] ?? 0);
        if ($markup < 0 || $markup > 500) {
            $markup = 0;
        }
        setting_set('viotp_markup', (string)(int)$markup, 'Thuê số OTP');

        $wait = max(1, min(30, (int)($_POST['wait_minutes'] ?? 7)));
        setting_set('viotp_wait_minutes', (string)$wait, 'Thuê số OTP');

        flash('success', 'Đã lưu cấu hình dịch vụ thuê số OTP.');
        redirect(url('/admin/otp-rental'));
    }

    public function adminSync(): void
    {
        CSRF::verifyOrFail();

        $services = OtpRentalService::services(true);
        flash('success', 'Đã đồng bộ ' . count($services) . ' dịch vụ từ API ViOTP.');

        redirect(url('/admin/otp-rental'));
    }
}
