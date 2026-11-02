<?php /** @var string $content */ $user = \App\Core\Auth::user(); ?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title>Admin - <?= e(setting('site_name', 'Casino Portal')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="<?= asset('css/style.css?v=6') ?>" rel="stylesheet">
    <link href="<?= asset('css/ui.css?v=1') ?>" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-dark main-navbar sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="<?= url('/admin') ?>">
            <i class="fas fa-shield-halved fs-4 text-gold"></i><span>QUẢN TRỊ</span>
        </a>
        <ul class="navbar-nav ms-auto align-items-center gap-3">
            <li><a class="nav-link" href="<?= url('/') ?>" target="_blank"><i class="fas fa-external-link-alt me-1"></i>Xem trang</a></li>
            <li><span class="nav-link"><i class="fas fa-user-circle me-1"></i><?= e($user['username']) ?></span></li>
            <li><a class="nav-link text-danger" href="<?= url('/logout') ?>"><i class="fas fa-right-from-bracket me-1"></i>Thoát</a></li>
        </ul>
    </div>
</nav>

<?php if (!empty($__flash)): ?>
<div class="container mt-3">
    <?php foreach ($__flash as $msg): ?>
        <div class="alert alert-<?= e($msg['type'] === 'error' ? 'danger' : 'success') ?> alert-dismissible fade show">
            <?= e($msg['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="container py-4">
    <div class="row g-4">
        <div class="col-lg-2">
            <nav class="admin-sidebar">
                <div class="side-title">Tổng quan</div>
                <a href="<?= url('/admin') ?>" class="<?= ($adminPage ?? '') === 'dashboard' ? 'active' : '' ?>">
                    <i class="fas fa-chart-pie"></i>Dashboard</a>
                <div class="side-title">Tài chính</div>
                <a href="<?= url('/admin/deposits') ?>" class="<?= ($adminPage ?? '') === 'deposits' ? 'active' : '' ?>">
                    <i class="fas fa-money-bill-wave"></i>Nạp tiền</a>
                <a href="<?= url('/admin/withdrawals') ?>" class="<?= ($adminPage ?? '') === 'withdrawals' ? 'active' : '' ?>">
                    <i class="fas fa-money-bill-transfer"></i>Rút tiền</a>
                <div class="side-title">Nội dừng</div>
                <a href="<?= url('/admin/games') ?>" class="<?= ($adminPage ?? '') === 'games' ? 'active' : '' ?>">
                    <i class="fas fa-dice"></i>Quản lý game</a>
                <a href="<?= url('/admin/packages') ?>" class="<?= ($adminPage ?? '') === 'packages' ? 'active' : '' ?>">
                    <i class="fas fa-box-open"></i>Gói chơi</a>
                <a href="<?= url('/admin/market') ?>" class="<?= ($adminPage ?? '') === 'market' ? 'active' : '' ?>">
                    <i class="fas fa-store"></i>Chợ tài nguyên</a>
                <div class="side-title">Khách hàng</div>
                <a href="<?= url('/admin/users') ?>" class="<?= ($adminPage ?? '') === 'users' ? 'active' : '' ?>">
                    <i class="fas fa-users"></i>Người dùng</a>
                <a href="<?= url('/admin/tickets') ?>" class="<?= ($adminPage ?? '') === 'tickets' ? 'active' : '' ?>">
                    <i class="fas fa-headset"></i>Hỗ trợ</a>
                <a href="<?= url('/admin/cso') ?>" class="<?= ($adminPage ?? '') === 'cso' ? 'active' : '' ?>">
                    <i class="fas fa-handshake"></i>CSO</a>
                <a href="<?= url('/admin/giftcodes') ?>" class="<?= ($adminPage ?? '') === 'giftcodes' ? 'active' : '' ?>">
                    <i class="fas fa-gift"></i>Giftcode</a>
                <div class="side-title">Nội dung</div>
                <a href="<?= url('/admin/ads') ?>" class="<?= ($adminPage ?? '') === 'ads' ? 'active' : '' ?>">
                    <i class="fas fa-image"></i>Banner quảng cáo</a>
                <a href="<?= url('/admin/news') ?>" class="<?= ($adminPage ?? '') === 'news' ? 'active' : '' ?>">
                    <i class="fas fa-newspaper"></i>Tin tức / Sự kiện</a>
                <a href="<?= url('/admin/campaigns') ?>" class="<?= ($adminPage ?? '') === 'campaigns' ? 'active' : '' ?>">
                    <i class="fas fa-envelope-open-text"></i>Gửi email hàng loạt</a>
                <a href="<?= url('/admin/child-sites') ?>" class="<?= ($adminPage ?? '') === 'child-sites' ? 'active' : '' ?>">
                    <i class="fas fa-sitemap"></i>Website con</a>
                <div class="side-title">Hệ thống</div>
                <a href="<?= url('/admin/banks') ?>" class="<?= ($adminPage ?? '') === 'banks' ? 'active' : '' ?>">
                    <i class="fas fa-building-columns"></i>Ngân hàng (VietQR)</a>
                <a href="<?= url('/admin/virtual-bank') ?>" class="<?= ($adminPage ?? '') === 'virtual-bank' ? 'active' : '' ?>">
                    <i class="fas fa-vault"></i>Bank ảo live livegame</a>
                <a href="<?= url('/admin/ai-accounts') ?>" class="<?= ($adminPage ?? '') === 'ai-accounts' ? 'active' : '' ?>">
                    <i class="fas fa-robot"></i>Tài khoản AI (GenzShop)</a>
                <a href="<?= url('/admin/vuavia-accounts') ?>" class="<?= ($adminPage ?? '') === 'vuavia-accounts' ? 'active' : '' ?>">
                    <i class="fas fa-user-secret"></i>Tài khoản &amp; Nick (Vuavia)</a>
                <a href="<?= url('/admin/otp-rental') ?>" class="<?= ($adminPage ?? '') === 'otp-rental' ? 'active' : '' ?>">
                    <i class="fas fa-comment-sms"></i>Thuê số OTP (ViOTP)</a>
                <a href="<?= url('/admin/auto-bank') ?>" class="<?= ($adminPage ?? '') === 'auto-bank' ? 'active' : '' ?>">
                    <i class="fas fa-building-columns"></i>Auto Bank (thanh toán)</a>
                <a href="<?= url('/admin/automxh') ?>" class="<?= ($adminPage ?? '') === 'automxh' ? 'active' : '' ?>">
                    <i class="fas fa-unlock-keyhole"></i>Auto MXH (mở khóa)</a>
                <a href="<?= url('/admin/smm') ?>" class="<?= ($adminPage ?? '') === 'smm' ? 'active' : '' ?>">
                    <i class="fas fa-thumbs-up"></i>SMM (like, view, comment)</a>
                <a href="<?= url('/admin/vps') ?>" class="<?= ($adminPage ?? '') === 'vps' ? 'active' : '' ?>">
                    <i class="fas fa-server"></i>VPS VMware (XVPS)</a>
                <a href="<?= url('/admin/vps-storevps') ?>" class="<?= ($adminPage ?? '') === 'vps-storevps' ? 'active' : '' ?>">
                    <i class="fas fa-layer-group"></i>VPS StoreVPS (Virtualizor)</a>
                <a href="<?= url('/admin/security') ?>" class="<?= ($adminPage ?? '') === 'security' ? 'active' : '' ?>">
                    <i class="fas fa-shield-halved"></i>Nhật ký bảo mật</a>
                <div class="side-title">Dịch vụ V16</div>
                <a href="<?= url('/admin/service-overview') ?>" class="<?= ($adminPage ?? '') === 'service-overview' ? 'active' : '' ?>">
                    <i class="fas fa-chart-pie"></i>Tổng quan dịch vụ</a>
                <a href="<?= url('/admin/service-prices') ?>" class="<?= ($adminPage ?? '') === 'service-prices' ? 'active' : '' ?>">
                    <i class="fas fa-tags"></i>Bảng giá dịch vụ</a>
                <a href="<?= url('/admin/phone-checks') ?>" class="<?= ($adminPage ?? '') === 'phone-checks' ? 'active' : '' ?>">
                    <i class="fas fa-mobile-screen-button"></i>Nhật ký check SĐT</a>
                <a href="<?= url('/admin/site-jobs') ?>" class="<?= ($adminPage ?? '') === 'site-jobs' ? 'active' : '' ?>">
                    <i class="fas fa-clone"></i>Dự án website</a>
                <a href="<?= url('/admin/sms-campaigns') ?>" class="<?= ($adminPage ?? '') === 'sms-campaigns' ? 'active' : '' ?>">
                    <i class="fas fa-comment-sms"></i>Chiến dịch SMS</a>
                <a href="<?= url('/admin/settings') ?>" class="<?= ($adminPage ?? '') === 'settings' ? 'active' : '' ?>">
                    <i class="fas fa-cog"></i>Cấu hình</a>
            </nav>
        </div>
        <div class="col-lg-10">
            <?= $content ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>window.APP = { baseUrl: '<?= url('/') ?>', csrfToken: '<?= csrf_token() ?>' };</script>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
