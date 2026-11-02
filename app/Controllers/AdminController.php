<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\CSRF;
use App\Core\Database;
use App\Core\Wallet;
use App\Services\AdService;
use App\Services\ChildSiteService;
use App\Services\CsoService;
use App\Services\MailerService;
use App\Services\MarketService;
use App\Services\NewsService;
use App\Services\PackageService;
use App\Services\Provider\ProviderFactory;
use App\Services\Provider\PgSoftProvider;

/**
 * Panel quản trị.
 */
class AdminController
{
    public function dashboard(): void
    {
        $stats = Wallet::stats();

        $recentDeposits = Database::fetchAll(
            'SELECT d.*, u.username FROM deposits d JOIN users u ON u.id = d.user_id
             ORDER BY d.id DESC LIMIT 10'
        );

        $recentWithdrawals = Database::fetchAll(
            'SELECT w.*, u.username FROM withdrawals w JOIN users u ON u.id = w.user_id
             ORDER BY w.id DESC LIMIT 10'
        );

        $topPlayers = Database::fetchAll(
            'SELECT id, username, full_name, balance, total_deposit, vip_level
             FROM users WHERE role = "user" ORDER BY total_deposit DESC LIMIT 10'
        );

        $onlineSessions = (int)Database::fetchColumn(
            'SELECT COUNT(*) FROM game_sessions WHERE status = "active" AND started_at > (NOW() - INTERVAL 15 MINUTE)'
        );

        $totalUsers   = (int)Database::fetchColumn('SELECT COUNT(*) FROM users');
        $newToday     = (int)Database::fetchColumn('SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()');
        $totalGames   = (int)Database::fetchColumn('SELECT COUNT(*) FROM games');
        $activePkgs   = (int)Database::fetchColumn('SELECT COUNT(*) FROM packages WHERE is_active = 1');

        view('admin/dashboard', compact(
            'stats', 'recentDeposits', 'recentWithdrawals', 'topPlayers',
            'onlineSessions', 'totalUsers', 'newToday', 'totalGames', 'activePkgs'
        ));
    }

    // ---------------- Nạp tiền ----------------

    public function deposits(): void
    {
        $status = (string)($_GET['status'] ?? '');
        $where  = '1=1';
        $params = [];
        if ($status !== '' && in_array($status, ['pending', 'approved', 'rejected', 'cancelled'], true)) {
            $where = 'd.status = ?';
            $params[] = $status;
        }

        $rows = Database::fetchAll(
            "SELECT d.*, u.username, u.full_name FROM deposits d
             JOIN users u ON u.id = d.user_id WHERE $where ORDER BY d.id DESC LIMIT 200",
            $params
        );

        view('admin/deposits', compact('rows', 'status'));
    }

    public function approveDeposit(): void
    {
        CSRF::verifyOrFail();

        $id  = (int)($_POST['id'] ?? 0);
        $dep = Database::fetch('SELECT * FROM deposits WHERE id = ?', [$id]);

        if (!$dep) {
            flash('error', 'Không tìm thấy yêu cầu nạp tiền.');
            redirect(url('/admin/deposits'));
        }
        if ($dep['status'] !== 'pending') {
            flash('error', 'Yêu cầu này đã được xử lý.');
            redirect(url('/admin/deposits'));
        }

        $userId = (int)$dep['user_id'];

        try {
            Database::transaction(function () use ($dep, $userId, $id) {
                $total = (float)$dep['amount'] + (float)$dep['bonus_amount'];

                Wallet::apply(
                    $userId,
                    'deposit',
                    $total,
                    'Nạp tiền ' . money((float)$dep['amount']) .
                        ($dep['bonus_amount'] > 0 ? ' + khuyến mãi ' . money((float)$dep['bonus_amount']) : ''),
                    'deposit',
                    (string)$id,
                    ['method' => $dep['method'], 'code' => $dep['transaction_code']]
                );

                Database::update('users', [
                    'total_deposit' => (float)Database::fetchColumn('SELECT total_deposit FROM users WHERE id = ?', [$userId]) + (float)$dep['amount'],
                ], 'id = :id', ['id' => $userId]);

                Database::update('deposits', [
                    'status'       => 'approved',
                    'processed_by' => Auth::id(),
                    'processed_at' => now(),
                ], 'id = :id', ['id' => $id]);
            });
        } catch (\Throwable $e) {
            flash('error', 'Không thể duyệt nạp tiền: ' . $e->getMessage());
            redirect(url('/admin/deposits'));
        }

        // Hoa hồng CSO
        CsoService::accrue($userId, (float)$dep['amount'], 'deposit');

        audit('deposit.approve', 'deposits', (string)$id, ['amount' => $dep['amount']]);
        flash('success', 'Đã duyệt nạp tiền ' . money((float)$dep['amount']) . '.');
        redirect(url('/admin/deposits'));
    }

    public function rejectDeposit(): void
    {
        CSRF::verifyOrFail();

        $id  = (int)($_POST['id'] ?? 0);
        $note = trim((string)($_POST['note'] ?? ''));

        Database::update('deposits', [
            'status'       => 'rejected',
            'note'         => $note,
            'processed_by' => Auth::id(),
            'processed_at' => now(),
        ], 'id = :id AND status = "pending"', ['id' => $id]);

        audit('deposit.reject', 'deposits', (string)$id, ['note' => $note]);
        flash('success', 'Đã từ chối yêu cầu nạp tiền.');
        redirect(url('/admin/deposits'));
    }

    // ---------------- Rút tiền ----------------

    public function withdrawals(): void
    {
        $rows = Database::fetchAll(
            'SELECT w.*, u.username, u.full_name FROM withdrawals w
             JOIN users u ON u.id = w.user_id ORDER BY w.id DESC LIMIT 200'
        );

        view('admin/withdrawals', compact('rows'));
    }

    public function approveWithdrawal(): void
    {
        CSRF::verifyOrFail();

        $id = (int)($_POST['id'] ?? 0);
        $wd = Database::fetch('SELECT * FROM withdrawals WHERE id = ?', [$id]);

        if (!$wd || $wd['status'] !== 'pending') {
            flash('error', 'Yêu cầu không hợp lệ.');
            redirect(url('/admin/withdrawals'));
        }

        $userId = (int)$wd['user_id'];

        // Kiểm tra số dư trước khi duyệt
        if (Wallet::balance($userId) < (float)$wd['amount']) {
            flash('error', 'Số dư của người chơi không đủ để rút ' . money((float)$wd['amount']) . '.');
            redirect(url('/admin/withdrawals'));
        }

        try {
            Database::transaction(function () use ($wd, $userId, $id) {
                // Trừ số dư (giữ tiền tạm trước khi chuyển khoản)
                Wallet::apply(
                    $userId,
                    'withdraw',
                    -((float)$wd['amount']),
                    'Rút tiền (phí ' . money((float)$wd['fee']) . ')',
                    'withdrawal',
                    (string)$id
                );

                Database::update('users', [
                    'total_withdraw' => (float)Database::fetchColumn('SELECT total_withdraw FROM users WHERE id = ?', [$userId]) + (float)$wd['amount'],
                ], 'id = :id', ['id' => $userId]);

                Database::update('withdrawals', [
                    'status'       => 'paid',
                    'processed_by' => Auth::id(),
                    'processed_at' => now(),
                ], 'id = :id', ['id' => $id]);
            });
        } catch (\Throwable $e) {
            flash('error', 'Không thể duyệt rút tiền: ' . $e->getMessage());
            redirect(url('/admin/withdrawals'));
        }

        audit('withdraw.approve', 'withdrawals', (string)$id, ['amount' => $wd['amount']]);
        flash('success', 'Đã duyệt rút tiền ' . money((float)$wd['amount']) . '.');
        redirect(url('/admin/withdrawals'));
    }

    public function rejectWithdrawal(): void
    {
        CSRF::verifyOrFail();

        $id   = (int)($_POST['id'] ?? 0);
        $note = trim((string)($_POST['note'] ?? ''));

        Database::update('withdrawals', [
            'status'       => 'rejected',
            'note'         => $note,
            'processed_by' => Auth::id(),
            'processed_at' => now(),
        ], 'id = :id AND status = "pending"', ['id' => $id]);

        flash('success', 'Đã từ chối yêu cầu rút tiền.');
        redirect(url('/admin/withdrawals'));
    }

    // ---------------- Games ----------------

    public function games(): void
    {
        $games = Database::fetchAll(
            'SELECT g.*, p.name AS provider_name FROM games g
             JOIN providers p ON p.id = g.provider_id ORDER BY g.sort_order DESC LIMIT 300'
        );

        $providers = Database::fetchAll('SELECT * FROM providers ORDER BY sort_order, name');

        view('admin/games', compact('games', 'providers'));
    }

    /**
     * Đồng bộ danh sách game từ provider (PGSoft).
     */
    public function syncGames(): void
    {
        CSRF::verifyOrFail();

        $providerCode = (string)($_POST['provider'] ?? 'pgsoft');
        $provider = ProviderFactory::make($providerCode);

        if (!$provider) {
            flash('error', 'Provider không hợp lệ.');
            redirect(url('/admin/games'));
        }

        $list = $provider->fetchGames();
        if (!$list) {
            flash('error', 'Không lấy được danh sách game từ provider.');
            redirect(url('/admin/games'));
        }

        $providerRow = Database::fetch('SELECT * FROM providers WHERE code = ?', [$providerCode]);
        $added = 0;
        $updated = 0;

        foreach ($list as $g) {
            $exists = Database::fetch(
                'SELECT id FROM games WHERE provider_id = ? AND provider_game_id = ?',
                [$providerRow['id'], $g['game_id']]
            );

            $payload = [
                'name'           => $g['name'],
                'icon'           => $g['icon'] ?? null,
                'embed_url'      => $g['embed_url'] ?? null,
                'demo_url'       => $g['demo_url'] ?? null,
                'game_type'      => $g['game_type'] ?? 'slot',
            ];

            if ($exists) {
                Database::update('games', $payload, 'id = :id', ['id' => $exists['id']]);
                $updated++;
            } else {
                $payload['provider_id']      = $providerRow['id'];
                $payload['provider_game_id'] = $g['game_id'];
                $payload['slug']             = slugify($providerCode . '-' . $g['game_id']);
                $payload['is_new']           = 1;
                Database::insert('games', $payload);
                $added++;
            }
        }

        audit('game.sync', 'providers', $providerCode, ['added' => $added, 'updated' => $updated]);
        flash('success', "Đã đồng bộ: {$added} game mới, {$updated} game cập nhật.");
        redirect(url('/admin/games'));
    }

    public function toggleGame(): void
    {
        CSRF::verifyOrFail();

        $id     = (int)($_POST['id'] ?? 0);
        $field  = (string)($_POST['field'] ?? '');
        $allowed = ['is_featured', 'is_hot', 'is_new', 'is_free', 'status'];

        if (!in_array($field, $allowed, true)) {
            flash('error', 'Trường không hợp lệ.');
            redirect(url('/admin/games'));
        }

        $game = Database::fetch('SELECT * FROM games WHERE id = ?', [$id]);
        if (!$game) {
            flash('error', 'Không tìm thấy game.');
            redirect(url('/admin/games'));
        }

        if ($field === 'status') {
            $new = $game['status'] === 'active' ? 'inactive' : 'active';
        } else {
            $new = $game[$field] ? 0 : 1;
        }

        Database::update('games', [$field => $new], 'id = :id', ['id' => $id]);
        flash('success', 'Đã cập nhật game.');
        redirect(url('/admin/games'));
    }

    // ---------------- Packages ----------------

    public function packages(): void
    {
        $packages = Database::fetchAll('SELECT * FROM packages ORDER BY sort_order, price');
        view('admin/packages', compact('packages'));
    }

    public function savePackage(): void
    {
        CSRF::verifyOrFail();

        $id   = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $mins = (int)($_POST['duration_minutes'] ?? 0);
        $price = (float)($_POST['price'] ?? 0);

        if ($name === '' || $mins <= 0) {
            flash('error', 'Vui lòng nhập tên và thời lượng hợp lệ.');
            redirect(url('/admin/packages'));
        }

        $data = [
            'name'             => $name,
            'slug'             => slugify($name),
            'description'      => trim((string)($_POST['description'] ?? '')),
            'duration_minutes' => $mins,
            'price'            => $price,
            'original_price'   => (float)($_POST['original_price'] ?? 0) ?: null,
            'provider_code'    => trim((string)($_POST['provider_code'] ?? '')) ?: null,
            'is_free_trial'    => !empty($_POST['is_free_trial']) ? 1 : 0,
            'is_active'        => !empty($_POST['is_active']) ? 1 : 0,
            'sort_order'       => (int)($_POST['sort_order'] ?? 0),
        ];

        if ($id > 0) {
            Database::update('packages', $data, 'id = :id', ['id' => $id]);
            flash('success', 'Đã cập nhật gói.');
        } else {
            $data['trial_limit_per_user'] = (int)($_POST['trial_limit_per_user'] ?? 1);
            Database::insert('packages', $data);
            flash('success', 'Đã tạo gói mới.');
        }

        redirect(url('/admin/packages'));
    }

    // ---------------- Users ----------------

    public function users(): void
    {
        $q = trim((string)($_GET['q'] ?? ''));
        $where = '1=1';
        $params = [];
        if ($q !== '') {
            $where = '(username LIKE ? OR email LIKE ? OR full_name LIKE ?)';
            $params = ['%' . $q . '%', '%' . $q . '%', '%' . $q . '%'];
        }

        $users = Database::fetchAll(
            "SELECT * FROM users WHERE $where ORDER BY id DESC LIMIT 200",
            $params
        );

        view('admin/users', compact('users', 'q'));
    }

    /**
     * Tạo tài khoản mới (user / operator / admin).
     */
    public function createUser(): void
    {
        CSRF::verifyOrFail();

        $username = trim((string)($_POST['username'] ?? ''));
        $email    = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $confirm  = (string)($_POST['password_confirm'] ?? '');
        $fullName = trim((string)($_POST['full_name'] ?? ''));
        $role     = (string)($_POST['role'] ?? 'user');
        $vip      = max(0, min(60, (int)($_POST['vip_level'] ?? 0)));
        $balance  = (float)($_POST['balance'] ?? 0);

        if (!in_array($role, ['user', 'operator', 'admin'], true)) {
            $role = 'user';
        }

        $errors = [];
        if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
            $errors[] = 'Username 3-50 ký tự, chỉ gồm chữ, số và dấu gạch dưới.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email không hợp lệ.';
        }
        if (strlen($password) < (int)config('security.password_min', 8)) {
            $errors[] = 'Mật khẩu phải có ít nhất ' . config('security.password_min', 8) . ' ký tự.';
        }
        if ($password !== $confirm) {
            $errors[] = 'Xác nhận mật khẩu không khớp.';
        }
        if (Database::fetchColumn('SELECT id FROM users WHERE username = ?', [$username])) {
            $errors[] = 'Username đã tồn tại.';
        }
        if (Database::fetchColumn('SELECT id FROM users WHERE email = ?', [$email])) {
            $errors[] = 'Email đã được sử dụng.';
        }

        if ($errors) {
            foreach ($errors as $err) {
                flash('error', $err);
            }
            redirect(url('/admin/users'));
        }

        $userId = Database::insert('users', [
            'username'      => $username,
            'email'         => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'full_name'     => $fullName !== '' ? $fullName : $username,
            'role'          => $role,
            'status'        => 'active',
            'balance'       => 0,
            'vip_level'     => $vip,
        ]);

        if ($balance != 0.0) {
            Wallet::apply($userId, 'adjustment', $balance, 'Admin tạo tài khoản');
        }

        audit('user.create', 'users', (string)$userId, ['username' => $username, 'role' => $role]);
        flash('success', 'Đã tạo tài khoản "' . $username . '".');
        redirect(url('/admin/users'));
    }

    public function updateUser(): void
    {
        CSRF::verifyOrFail();

        $id   = (int)($_POST['id'] ?? 0);
        $user = Database::fetch('SELECT * FROM users WHERE id = ?', [$id]);
        if (!$user) {
            flash('error', 'Không tìm thấy user.');
            redirect(url('/admin/users'));
        }

        $data = [];

        if (isset($_POST['status'])) {
            $data['status'] = $_POST['status'] === 'active' ? 'active' : 'banned';
        }
        if (isset($_POST['role']) && in_array($_POST['role'], ['user', 'admin', 'operator'], true)) {
            $data['role'] = $_POST['role'];
        }
        if (isset($_POST['vip_level'])) {
            $data['vip_level'] = (int)$_POST['vip_level'];
        }
        if (isset($_POST['balance'])) {
            $newBalance = (float)$_POST['balance'];
            $diff = $newBalance - (float)$user['balance'];
            if ($diff != 0.0) {
                Wallet::apply($id, 'adjustment', $diff, 'Admin điều chỉnh số dư');
            }
        }

        if ($data) {
            Database::update('users', $data, 'id = :id', ['id' => $id]);
            audit('user.update', 'users', (string)$id, $data);
            flash('success', 'Đã cập nhật user.');
        }

        redirect(url('/admin/users'));
    }

    // ---------------- Giftcode ----------------

    public function giftcodes(): void
    {
        $codes = Database::fetchAll('SELECT * FROM giftcodes ORDER BY id DESC LIMIT 200');
        view('admin/giftcodes', compact('codes'));
    }

    public function createGiftcode(): void
    {
        CSRF::verifyOrFail();

        $code   = trim((string)($_POST['code'] ?? ''));
        $amount = (float)($_POST['amount'] ?? 0);

        if ($code === '' || $amount <= 0) {
            flash('error', 'Vui lòng nhập mã và số tiền hợp lệ.');
            redirect(url('/admin/giftcodes'));
        }

        if (Database::fetchColumn('SELECT id FROM giftcodes WHERE code = ?', [$code])) {
            flash('error', 'Mã đã tồn tại.');
            redirect(url('/admin/giftcodes'));
        }

        Database::insert('giftcodes', [
            'code'          => $code,
            'amount'        => $amount,
            'bonus_amount'  => (float)($_POST['bonus_amount'] ?? 0),
            'min_deposit'   => (float)($_POST['min_deposit'] ?? 0),
            'max_uses'      => (int)($_POST['max_uses'] ?? 1),
            'expires_at'    => !empty($_POST['expires_at']) ? $_POST['expires_at'] : null,
            'is_active'     => 1,
        ]);

        flash('success', 'Đã tạo giftcode.');
        redirect(url('/admin/giftcodes'));
    }

    // ---------------- Settings ----------------

    public function settings(): void
    {
        $settings = Database::fetchAll('SELECT * FROM settings ORDER BY name');
        view('admin/settings', compact('settings'));
    }

    public function saveSettings(): void
    {
        CSRF::verifyOrFail();

        foreach ($_POST['settings'] ?? [] as $name => $value) {
            if (!preg_match('/^[a-z0-9_]+$/i', (string)$name)) {
                continue;
            }
            $exists = Database::fetchColumn('SELECT name FROM settings WHERE name = ?', [$name]);
            if ($exists) {
                Database::update('settings', ['value' => (string)$value], 'name = :n', ['n' => $name]);
            } else {
                Database::insert('settings', ['name' => $name, 'value' => (string)$value]);
            }
        }

        flash('success', 'Đã lưu cấu hình.');
        redirect(url('/admin/settings'));
    }

    // ---------------- CSO ----------------

    public function csoList(): void
    {
        $list = Database::fetchAll(
            'SELECT c.*, u.username, u.email FROM cso_accounts c
             JOIN users u ON u.id = c.user_id ORDER BY c.total_revenue DESC LIMIT 200'
        );

        view('admin/cso', compact('list'));
    }

    public function payCommission(): void
    {
        CSRF::verifyOrFail();

        $id = (int)($_POST['id'] ?? 0);
        CsoService::pay($id);
        flash('success', 'Đã thanh toán hoa hồng.');
        redirect(url('/admin/cso'));
    }

    /**
     * Đặt số dư cho user (nạp trực tiếp từ admin).
     */
    public function adjustBalance(): void
    {
        CSRF::verifyOrFail();

        $userId = (int)($_POST['user_id'] ?? 0);
        $amount = (float)($_POST['amount'] ?? 0);

        if ($userId <= 0 || $amount == 0.0) {
            flash('error', 'Thiếu thông tin.');
            redirect(url('/admin/users'));
        }

        try {
            Wallet::apply($userId, 'adjustment', $amount, 'Admin điều chỉnh số dư');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
            redirect(url('/admin/users'));
        }

        flash('success', 'Đã cập nhật số dư.');
        redirect(url('/admin/users'));
    }

    // ==============================================================
    //                QUẢNG CÁO / BANNER
    // ==============================================================

    public function ads(): void
    {
        $ads = Database::fetchAll('SELECT * FROM ads_banners ORDER BY slot, sort_order, id DESC');
        view('admin/ads', compact('ads'));
    }

    public function saveAd(): void
    {
        CSRF::verifyOrFail();

        $id   = (int)($_POST['id'] ?? 0);
        $slot = (string)($_POST['slot'] ?? 'home_top');

        if (!isset(AdService::SLOTS[$slot])) {
            flash('error', 'Vị trí banner không hợp lệ.');
            redirect(url('/admin/ads'));
        }

        $data = [
            'title'       => mb_substr(trim((string)($_POST['title'] ?? '')), 0, 150),
            'slot'        => $slot,
            'image_url'   => trim((string)($_POST['image_url'] ?? '')) ?: null,
            'video_url'   => trim((string)($_POST['video_url'] ?? '')) ?: null,
            'link_url'    => trim((string)($_POST['link_url'] ?? '')) ?: null,
            'position'    => trim((string)($_POST['position'] ?? '')) ?: null,
            'description' => trim((string)($_POST['description'] ?? '')) ?: null,
            'is_active'   => !empty($_POST['is_active']) ? 1 : 0,
            'sort_order'  => (int)($_POST['sort_order'] ?? 0),
            'start_at'    => !empty($_POST['start_at']) ? $_POST['start_at'] : null,
            'end_at'      => !empty($_POST['end_at']) ? $_POST['end_at'] : null,
        ];

        if ($data['image_url'] === null && $data['video_url'] === null) {
            flash('error', 'Cần ít nhất 1 ảnh hoặc video cho banner.');
            redirect(url('/admin/ads'));
        }

        if ($id > 0) {
            Database::update('ads_banners', $data, 'id = :id', ['id' => $id]);
            flash('success', 'Đã cập nhật banner.');
        } else {
            Database::insert('ads_banners', $data);
            flash('success', 'Đã thêm banner mới.');
        }

        redirect(url('/admin/ads'));
    }

    public function deleteAd(): void
    {
        CSRF::verifyOrFail();

        $id = (int)($_POST['id'] ?? 0);
        Database::delete('ads_banners', 'id = ?', [$id]);

        audit('ad.delete', 'ads_banners', (string)$id);
        flash('success', 'Đã xoá banner.');
        redirect(url('/admin/ads'));
    }

    // ==============================================================
    //                WEBSITE CON
    // ==============================================================

    public function childSites(): void
    {
        $sites = Database::fetchAll(
            'SELECT c.*, u.username FROM child_sites c
             JOIN users u ON u.id = c.user_id ORDER BY c.id DESC'
        );

        view('admin/child-sites', compact('sites'));
    }

    public function updateChildSite(): void
    {
        CSRF::verifyOrFail();

        $id   = (int)($_POST['id'] ?? 0);
        $site = ChildSiteService::find($id);
        if (!$site) {
            flash('error', 'Không tìm thấy website con.');
            redirect(url('/admin/child-sites'));
        }

        $status = (string)($_POST['status'] ?? $site['status']);
        if (!in_array($status, ['pending', 'active', 'suspended'], true)) {
            $status = $site['status'];
        }

        $data = [
            'status'          => $status,
            'rev_share'       => (float)($_POST['rev_share'] ?? $site['rev_share']),
            'require_deposit' => !empty($_POST['require_deposit']) ? 1 : 0,
            'min_deposit'     => max(0.0, (float)($_POST['min_deposit'] ?? 0)),
        ];

        if (trim((string)($_POST['domain'] ?? '')) !== '') {
            $data['domain'] = trim((string)$_POST['domain']);
        }

        Database::update('child_sites', $data, 'id = :id', ['id' => $id]);

        audit('child_site.update', 'child_sites', (string)$id, $data);
        flash('success', 'Đã cập nhật website con.');
        redirect(url('/admin/child-sites'));
    }

    // ==============================================================
    //                TIN TỨC / SỰ KIỆN
    // ==============================================================

    public function news(): void
    {
        $posts = Database::fetchAll('SELECT * FROM news_posts ORDER BY is_pinned DESC, published_at DESC LIMIT 200');
        view('admin/news', compact('posts'));
    }

    public function saveNews(): void
    {
        CSRF::verifyOrFail();

        $id    = (int)($_POST['id'] ?? 0);
        $title = trim((string)($_POST['title'] ?? ''));

        if ($title === '') {
            flash('error', 'Vui lòng nhập tiêu đề bài viết.');
            redirect(url('/admin/news'));
        }

        $type = (string)($_POST['type'] ?? 'news');
        if (!isset(NewsService::TYPES[$type])) {
            $type = 'news';
        }

        $slug = slugify((string)($_POST['slug'] ?? $title));
        $i = 1;
        $base = $slug;
        while (Database::fetchColumn('SELECT id FROM news_posts WHERE slug = ? AND id <> ?', [$slug, $id])) {
            $slug = $base . '-' . $i;
            $i++;
        }

        $data = [
            'title'       => mb_substr($title, 0, 250),
            'slug'        => $slug,
            'excerpt'     => trim((string)($_POST['excerpt'] ?? '')) ?: null,
            'content'     => (string)($_POST['content'] ?? ''),
            'banner'      => trim((string)($_POST['banner'] ?? '')) ?: null,
            'type'        => $type,
            'is_pinned'   => !empty($_POST['is_pinned']) ? 1 : 0,
            'is_active'   => !empty($_POST['is_active']) ? 1 : 0,
            'published_at' => trim((string)($_POST['published_at'] ?? '')) ?: now(),
        ];

        if ($id > 0) {
            Database::update('news_posts', $data, 'id = :id', ['id' => $id]);
            flash('success', 'Đã cập nhật bài viết.');
        } else {
            Database::insert('news_posts', $data);
            flash('success', 'Đã đăng bài viết mới.');
        }

        redirect(url('/admin/news'));
    }

    public function deleteNews(): void
    {
        CSRF::verifyOrFail();

        $id = (int)($_POST['id'] ?? 0);
        Database::delete('news_posts', 'id = ?', [$id]);

        flash('success', 'Đã xoá bài viết.');
        redirect(url('/admin/news'));
    }

    // ==============================================================
    //                CHIẾN DỊCH EMAIL (BROADCAST)
    // ==============================================================

    public function campaigns(): void
    {
        $campaigns = Database::fetchAll('SELECT * FROM mail_campaigns ORDER BY id DESC LIMIT 100');
        $logs      = Database::fetchAll('SELECT * FROM mail_logs ORDER BY id DESC LIMIT 100');

        view('admin/campaigns', compact('campaigns', 'logs'));
    }

    public function saveCampaign(): void
    {
        CSRF::verifyOrFail();

        $id      = (int)($_POST['id'] ?? 0);
        $title   = trim((string)($_POST['title'] ?? ''));
        $subject = trim((string)($_POST['subject'] ?? ''));

        if ($title === '' || $subject === '') {
            flash('error', 'Vui lòng nhập tiêu đề và tiêu đề email.');
            redirect(url('/admin/campaigns'));
        }

        $target = (string)($_POST['target'] ?? 'all');
        if (!in_array($target, ['all', 'vip', 'active', 'deposited'], true)) {
            $target = 'all';
        }

        $data = [
            'title'   => mb_substr($title, 0, 200),
            'subject' => mb_substr($subject, 0, 255),
            'body'    => (string)($_POST['body'] ?? ''),
            'banner'  => trim((string)($_POST['banner'] ?? '')) ?: null,
            'target'  => $target,
            'status'  => 'draft',
        ];

        if ($id > 0) {
            Database::update('mail_campaigns', $data, 'id = :id', ['id' => $id]);
            flash('success', 'Đã lưu chiến dịch.');
        } else {
            Database::insert('mail_campaigns', $data);
            flash('success', 'Đã tạo chiến dịch mới.');
        }

        redirect(url('/admin/campaigns'));
    }

    public function sendCampaign(): void
    {
        CSRF::verifyOrFail();

        $id       = (int)($_POST['id'] ?? 0);
        $campaign = Database::fetch('SELECT * FROM mail_campaigns WHERE id = ?', [$id]);
        if (!$campaign) {
            flash('error', 'Không tìm thấy chiến dịch.');
            redirect(url('/admin/campaigns'));
        }
        if ($campaign['status'] === 'sent') {
            flash('error', 'Chiến dịch đã được gửi trước đó.');
            redirect(url('/admin/campaigns'));
        }

        $users = $this->campaignAudience((string)$campaign['target']);

        Database::update('mail_campaigns', [
            'status'     => 'sending',
            'total'      => count($users),
            'sent_count' => 0,
        ], 'id = :id', ['id' => $id]);

        $sent = 0;
        foreach ($users as $u) {
            $html = MailerService::template('campaign', [
                'heading'    => (string)$campaign['title'],
                'subheading' => (string)$campaign['subject'],
                'message'    => (string)$campaign['body'],
                'username'   => (string)$u['username'],
                'banner'     => (string)($campaign['banner'] ?? ''),
            ]);

            if (MailerService::send((string)$u['email'], (string)$campaign['subject'], $html)) {
                $sent++;
                Database::update('mail_campaigns', ['sent_count' => $sent], 'id = :id', ['id' => $id]);
            }
        }

        Database::update('mail_campaigns', [
            'status'     => 'sent',
            'sent_count' => $sent,
        ], 'id = :id', ['id' => $id]);

        audit('campaign.send', 'mail_campaigns', (string)$id, ['sent' => $sent, 'total' => count($users)]);
        flash('success', "Đã gửi chiến dịch cho {$sent}/" . count($users) . ' thành viên.');
        redirect(url('/admin/campaigns'));
    }

    /**
     * Lấy danh sách người nhận theo nhóm mục tiêu.
     */
    private function campaignAudience(string $target): array
    {
        $sql = match ($target) {
            'vip'       => 'SELECT id, username, email FROM users WHERE vip_level > 0 AND status = "active"',
            'active'    => 'SELECT id, username, email FROM users WHERE status = "active"',
            'deposited' => 'SELECT id, username, email FROM users WHERE total_deposit > 0 AND status = "active"',
            default     => 'SELECT id, username, email FROM users WHERE status = "active"',
        };

        return Database::fetchAll($sql);
    }

    public function deleteCampaign(): void
    {
        CSRF::verifyOrFail();

        $id = (int)($_POST['id'] ?? 0);
        Database::delete('mail_campaigns', 'id = ?', [$id]);

        flash('success', 'Đã xoá chiến dịch.');
        redirect(url('/admin/campaigns'));
    }

    // ==============================================================
    //                NGÂN HÀNG NHẬN TIỀN (VIETQR)
    // ==============================================================

    public function banks(): void
    {
        $banks = Database::fetchAll('SELECT * FROM payment_banks ORDER BY sort_order, bank_name');
        view('admin/banks', compact('banks'));
    }

    public function saveBank(): void
    {
        CSRF::verifyOrFail();

        $id   = (int)($_POST['id'] ?? 0);
        $code = trim((string)($_POST['bank_code'] ?? ''));
        $no   = trim((string)($_POST['account_no'] ?? ''));
        $name = trim((string)($_POST['account_name'] ?? ''));
        $acq  = trim((string)($_POST['acq_id'] ?? ''));

        if ($code === '' || $no === '' || $name === '' || $acq === '') {
            flash('error', 'Vui lòng nhập đầy đủ mã ngân hàng, số tài khoản, chủ tài khoản và mã BIN.');
            redirect(url('/admin/banks'));
        }

        $data = [
            'bank_code'    => mb_substr($code, 0, 20),
            'bank_name'    => trim((string)($_POST['bank_name'] ?? $code)),
            'account_no'   => mb_substr($no, 0, 40),
            'account_name' => mb_substr($name, 0, 150),
            'acq_id'       => mb_substr($acq, 0, 20),
            'logo'         => trim((string)($_POST['logo'] ?? '')) ?: null,
            'is_active'    => !empty($_POST['is_active']) ? 1 : 0,
            'sort_order'   => (int)($_POST['sort_order'] ?? 0),
        ];

        if ($id > 0) {
            Database::update('payment_banks', $data, 'id = :id', ['id' => $id]);
            flash('success', 'Đã cập nhật tài khoản ngân hàng.');
        } else {
            Database::insert('payment_banks', $data);
            flash('success', 'Đã thêm tài khoản ngân hàng.');
        }

        redirect(url('/admin/banks'));
    }

    public function deleteBank(): void
    {
        CSRF::verifyOrFail();

        $id = (int)($_POST['id'] ?? 0);
        Database::delete('payment_banks', 'id = ?', [$id]);

        flash('success', 'Đã xoá tài khoản ngân hàng.');
        redirect(url('/admin/banks'));
    }

    // ==============================================================
    //                CHỢ TÀI NGUYÊN (DUYỆT SÀN)
    // ==============================================================

    public function market(): void
    {
        $products = Database::fetchAll(
            'SELECT mp.*, u.username FROM market_products mp
             JOIN users u ON u.id = mp.seller_id ORDER BY mp.id DESC LIMIT 200'
        );
        $orders = Database::fetchAll(
            'SELECT mo.*, mp.title, uf.username AS buyer, ut.username AS seller
             FROM market_orders mo
             JOIN market_products mp ON mp.id = mo.product_id
             JOIN users uf ON uf.id = mo.buyer_id
             JOIN users ut ON ut.id = mo.seller_id
             ORDER BY mo.id DESC LIMIT 200'
        );

        view('admin/market', compact('products', 'orders'));
    }

    public function toggleMarketProduct(): void
    {
        CSRF::verifyOrFail();

        $id  = (int)($_POST['id'] ?? 0);
        $row = Database::fetch('SELECT * FROM market_products WHERE id = ?', [$id]);
        if (!$row) {
            flash('error', 'Không tìm thấy sản phẩm.');
            redirect(url('/admin/market'));
        }

        Database::update('market_products', [
            'is_active' => $row['is_active'] ? 0 : 1,
        ], 'id = :id', ['id' => $id]);

        flash('success', 'Đã cập nhật trạng thái sản phẩm.');
        redirect(url('/admin/market'));
    }

    // ==============================================================
    //                TICK XANH (XÁC MINH TÀI KHOẢN)
    // ==============================================================

    public function toggleVerified(): void
    {
        CSRF::verifyOrFail();

        $id   = (int)($_POST['id'] ?? 0);
        $user = Database::fetch('SELECT id, is_verified FROM users WHERE id = ?', [$id]);
        if (!$user) {
            flash('error', 'Không tìm thấy tài khoản.');
            redirect(url('/admin/users'));
        }

        $new = $user['is_verified'] ? 0 : 1;
        Database::update('users', ['is_verified' => $new], 'id = :id', ['id' => $id]);

        audit('user.verify_toggle', 'users', (string)$id, ['is_verified' => $new]);
        flash('success', $new ? 'Đã cấp tick xanh cho tài khoản.' : 'Đã thu hồi tick xanh.');
        redirect(url('/admin/users'));
    }

    // ==============================================================
    //                CHỐNG DEVTOOLS / BẢO MẬT
    // ==============================================================

    public function security(): void
    {
        $logs = Database::fetchAll(
            'SELECT * FROM activity_logs ORDER BY id DESC LIMIT 150'
        );

        view('admin/security', compact('logs'));
    }
}
