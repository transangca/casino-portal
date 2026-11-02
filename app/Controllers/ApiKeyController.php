<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\CSRF;
use App\Core\Database;
use App\Services\ApiKeyService;

/**
 * Trang quản lý API cho dev / đối tác.
 *
 * - Mua gói API (thanh toán qua ví)
 * - Tạo API key (hiển thị 1 lần)
 * - Gia hạn thêm thời gian
 * - Xem log sử dụng
 */
class ApiKeyController
{
    public function index(): void
    {
        $userId = (int)Auth::id();

        $clients = Database::fetchAll(
            'SELECT c.*, p.name AS package_name, p.slug AS package_slug
             FROM api_clients c LEFT JOIN api_packages p ON p.id = c.package_id
             WHERE c.user_id = ? ORDER BY c.id DESC',
            [$userId]
        );

        $packages = Database::fetchAll(
            'SELECT * FROM api_packages WHERE is_active = 1 ORDER BY sort_order, price'
        );

        $usage = [];
        foreach ($clients as $c) {
            $usage[$c['id']] = Database::fetchAll(
                'SELECT endpoint, method, status_code, duration_ms, ip, created_at
                 FROM api_client_logs WHERE client_id = ? ORDER BY id DESC LIMIT 20',
                [(int)$c['id']]
            );
        }

        view('api/index', compact('clients', 'packages', 'usage'));
    }

    /**
     * Mua gói API — trừ tiền trong ví, cấp key ngay.
     */
    public function purchase(): void
    {
        CSRF::verifyOrFail();

        $userId = (int)Auth::id();
        $pkgId  = (int)($_POST['package_id'] ?? 0);
        $name   = trim((string)($_POST['name'] ?? 'Ứng dụng của tôi'));

        $pkg = Database::fetch('SELECT * FROM api_packages WHERE id = ? AND is_active = 1', [$pkgId]);
        if (!$pkg) {
            flash('error', 'Gói API không tồn tại hoặc đã ngừng bán.');
            redirect(url('/api-keys'));
        }

        $price = (float)$pkg['price'];

        // Trừ tiền ví (nếu có giá)
        if ($price > 0) {
            $balance = \App\Core\Wallet::balance($userId);
            if ($balance < $price) {
                flash('error', 'Số dư không đủ để mua gói API này. Cần ' . money($price) . '.');
                redirect(url('/api-keys'));
            }

            try {
                \App\Core\Wallet::apply(
                    $userId,
                    'adjustment',
                    -$price,
                    'Mua gói API: ' . $pkg['name'],
                    'api_package',
                    (string)$pkgId,
                    ['package' => $pkg['slug']]
                );
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
                redirect(url('/api-keys'));
            }
        }

        $result = ApiKeyService::create($userId, [
            'name'       => $name,
            'package_id' => $pkgId,
            'email'      => (string)($_POST['email'] ?? ''),
            'website'    => (string)($_POST['website'] ?? ''),
        ]);

        if (!$result['success']) {
            flash('error', $result['message']);
            redirect(url('/api-keys'));
        }

        // Lưu key hiển thị 1 lần vào session
        $_SESSION['api_key_reveal'] = [
            'key'       => $result['key'],
            'client_id' => (int)$result['client']['id'],
            'package'   => $pkg['name'],
        ];

        audit('api_package.purchase', 'api_clients', (string)$result['client']['id'], [
            'package' => $pkg['slug'],
            'price'   => $price,
        ]);

        flash('success', 'Đã mua gói "' . $pkg['name'] . '". Copy API key ngay bên dưới.');
        redirect(url('/api-keys'));
    }

    /**
     * Tạo thêm API key miễn phí (dùng key hiện có, không tốn tiền).
     */
    public function create(): void
    {
        CSRF::verifyOrFail();

        $userId = (int)Auth::id();
        $name   = trim((string)($_POST['name'] ?? ''));

        // Lấy gói API đang active của user (để kế thừa quota)
        $pkgId = (int)Database::fetchColumn(
            'SELECT c.package_id FROM api_clients c
             WHERE c.user_id = ? AND c.package_id IS NOT NULL AND c.status = "active"
             ORDER BY c.id DESC LIMIT 1',
            [$userId]
        );

        $result = ApiKeyService::create($userId, [
            'name'       => $name,
            'package_id' => $pkgId,
            'email'      => (string)($_POST['email'] ?? ''),
            'website'    => (string)($_POST['website'] ?? ''),
        ]);

        if (!$result['success']) {
            flash('error', $result['message']);
            redirect(url('/api-keys'));
        }

        $_SESSION['api_key_reveal'] = [
            'key'       => $result['key'],
            'client_id' => (int)$result['client']['id'],
            'package'   => $pkgId ? 'Gói đang dùng' : 'Miễn phí',
        ];

        flash('success', 'Đã tạo API key mới.');
        redirect(url('/api-keys'));
    }

    /**
     * Gia hạn thêm thời gian (trừ tiền ví theo số ngày).
     */
    public function renew(): void
    {
        CSRF::verifyOrFail();

        $userId   = (int)Auth::id();
        $clientId = (int)($_POST['client_id'] ?? 0);
        $days     = max(1, min(365, (int)($_POST['days'] ?? 30)));

        $client = Database::fetch(
            'SELECT c.*, p.price AS package_price, p.name AS package_name, p.duration_days
             FROM api_clients c LEFT JOIN api_packages p ON p.id = c.package_id
             WHERE c.id = ? AND c.user_id = ?',
            [$clientId, $userId]
        );

        if (!$client) {
            flash('error', 'Không tìm thấy API key.');
            redirect(url('/api-keys'));
        }

        // Tính giá gia hạn theo tỷ lệ ngày
        $pricePerDay = 0.0;
        if ($client['package_price'] && (int)$client['duration_days'] > 0) {
            $pricePerDay = (float)$client['package_price'] / (int)$client['duration_days'];
        }
        $price = round($pricePerDay * $days, 0);

        if ($price > 0) {
            try {
                \App\Core\Wallet::apply(
                    $userId,
                    'adjustment',
                    -$price,
                    'Gia hạn API key #' . $clientId . ' thêm ' . $days . ' ngày',
                    'api_client',
                    (string)$clientId,
                    ['days' => $days]
                );
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
                redirect(url('/api-keys'));
            }
        }

        $result = ApiKeyService::renew($clientId, $userId, $days);

        flash($result['success'] ? 'success' : 'error', $result['message']);
        redirect(url('/api-keys'));
    }

    /**
     * Xem lại API key (giải mã từ kho mã hóa).
     */
    public function reveal(): void
    {
        $userId   = (int)Auth::id();
        $clientId = (int)($_GET['id'] ?? 0);

        $key = ApiKeyService::revealKey($clientId, $userId);
        if (!$key) {
            flash('error', 'Không tìm thấy API key.');
            redirect(url('/api-keys'));
        }

        $_SESSION['api_key_reveal'] = [
            'key'       => $key,
            'client_id' => $clientId,
            'package'   => 'Xem lại',
        ];

        flash('success', 'API key của bạn (lưu lại an toàn).');
        redirect(url('/api-keys'));
    }

    /**
     * Tạm khóa / mở khóa.
     */
    public function toggle(): void
    {
        CSRF::verifyOrFail();

        $userId   = (int)Auth::id();
        $clientId = (int)($_POST['client_id'] ?? 0);
        $status   = (string)($_POST['status'] ?? 'active');

        ApiKeyService::setStatus($clientId, $userId, $status === 'suspended' ? 'suspended' : 'active');

        flash('success', $status === 'suspended' ? 'Đã tạm khóa API key.' : 'Đã mở khóa API key.');
        redirect(url('/api-keys'));
    }

    /**
     * Xóa API key.
     */
    public function delete(): void
    {
        CSRF::verifyOrFail();

        $userId   = (int)Auth::id();
        $clientId = (int)($_POST['client_id'] ?? 0);

        ApiKeyService::delete($clientId, $userId);

        flash('success', 'Đã xóa API key.');
        redirect(url('/api-keys'));
    }
}
