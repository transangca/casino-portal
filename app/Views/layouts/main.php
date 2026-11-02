<?php
/**
 * Layout chính — navbar gọn 1 mục "Dịch vụ" + sub-menu, theme switcher,
 * đổi ngôn ngữ, avatar + tick xanh.
 *
 * @var string $content
 * @var array  $__flash
 */
$user     = \App\Core\Auth::user();
$siteName = setting('site_name', 'Casino Portal');
$logo     = (string)setting('site_logo', '');
$balance  = $user ? (float)$user['balance'] : 0;
$telegram = (string)setting('support_telegram', '');
$zalo     = (string)setting('support_zalo', '');
$hotline  = (string)setting('support_hotline', '');
$theme    = current_theme();
$locale   = current_locale();
$cats     = provider_categories();
$path     = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

/** Đánh dấu menu con đang active */
$inGame = str_starts_with($path, '/lobbies') || str_starts_with($path, '/category');
$inWallet = str_starts_with($path, '/wallet');
$inMarket = str_starts_with($path, '/market');
$inVip   = str_starts_with($path, '/vip') || str_starts_with($path, '/child-sites');
?>
<!DOCTYPE html>
<html lang="<?= e($locale) ?>" data-theme="<?= e($theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sảnh casino & slots - trải nghiệm game cao cấp, tiền nạp đồng bộ trực tiếp với game">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title><?= e(($title ?? '') ? $title . ' | ' . $siteName : $siteName) ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.1/dist/sweetalert2.min.css" rel="stylesheet">
    <link rel="preload" as="font" type="font/ttf" href="<?= asset('fonts/inter-400.ttf') ?>" crossorigin>
    <link rel="preload" as="font" type="font/ttf" href="<?= asset('fonts/inter-700.ttf') ?>" crossorigin>
    <link href="<?= asset('css/style.css?v=6') ?>" rel="stylesheet">
    <link href="<?= asset('css/theme.css?v=6') ?>" rel="stylesheet">
    <link href="<?= asset('css/ui.css?v=1') ?>" rel="stylesheet">
    <link href="<?= asset('css/pgsoft.css?v=11') ?>" rel="stylesheet">
    <link href="<?= asset('css/deno.css?v=2') ?>" rel="stylesheet">
    <!-- PG SOFT showcase: lottie + gsap + scrolltrigger -->
    <script src="<?= asset('js/lottie.min.js?v=1') ?>" defer></script>
    <script src="<?= asset('js/gsap.min.js?v=1') ?>" defer></script>
    <script src="<?= asset('js/ScrollTrigger.min.js?v=1') ?>" defer></script>
</head>
<body>

<!-- ================= NAVBAR ================= -->
<nav class="navbar navbar-expand-lg navbar-dark main-navbar sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= url('/') ?>">
            <?php if ($logo !== ''): ?>
                <img src="<?= e($logo) ?>" alt="<?= e($siteName) ?>" class="brand-logo">
            <?php else: ?>
                <span class="brand-logo-fallback">P</span>
            <?php endif; ?>
            <span class="d-none d-sm-inline"><?= e($siteName) ?></span>
        </a>

        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 align-items-lg-center gap-lg-1">

                <!-- ===== Dịch vụ (mega menu gộp) ===== -->
                <li class="nav-item services-menu">
                    <a class="nav-link" href="<?= url('/games') ?>">
                        <i class="fas fa-th me-1"></i>Dịch vụ
                        <i class="fas fa-chevron-down ms-1" style="font-size:.6rem"></i>
                    </a>

                    <div class="services-panel">
                        <!-- Cột 1: Sảnh game -->
                        <div>
                            <div class="services-col-title">Sảnh game</div>
                            <a href="<?= url('/lobbies') ?>"><i class="fas fa-crown"></i>Tất cả sảnh</a>
                            <?php foreach ($cats as $c): ?>
                                <a href="<?= url('/category/' . $c['code']) ?>">
                                    <i class="fas <?= e($c['icon'] ?: 'fa-gamepad') ?>"></i>
                                    <?= e($c['name']) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>

                        <!-- Cột 2: Ví & tài chính -->
                        <div>
                            <div class="services-col-title">Ví &amp; tài chính</div>
                            <a href="<?= url('/wallet') ?>"><i class="fas fa-wallet"></i>Ví của tôi</a>
                            <a href="<?= url('/wallet/deposit') ?>"><i class="fas fa-circle-plus"></i>Nạp tiền</a>
                            <a href="<?= url('/wallet/withdraw') ?>"><i class="fas fa-money-bill-transfer"></i>Rút tiền</a>
                            <a href="<?= url('/wallet/transfer') ?>"><i class="fas fa-right-left"></i>Chuyển tiền</a>
                            <a href="<?= url('/p2p') ?>"><i class="fas fa-handshake-angle"></i>Sàn P2P mua bán</a>
                            <a href="<?= url('/wallet/history') ?>"><i class="fas fa-history"></i>Lịch sử giao dịch</a>
                        </div>

                        <!-- Cột 3: Dịch vụ số -->
                        <div>
                            <div class="services-col-title">Dịch vụ số</div>
                            <a href="<?= url('/services/website-builder') ?>"><i class="fas fa-laptop-code"></i>Thiết kế website</a>
                            <a href="<?= url('/services/design') ?>"><i class="fas fa-palette"></i>Thiết kế ảnh</a>
                            <a href="<?= url('/services/ai') ?>"><i class="fas fa-robot"></i>Trung tâm AI</a>
                            <a href="<?= url('/services/ai-accounts') ?>"><i class="fas fa-robot"></i>Tài khoản AI</a>
                            <a href="<?= url('/services/vuavia-accounts') ?>"><i class="fas fa-user-secret"></i>Tài khoản &amp; Nick</a>
                            <a href="<?= url('/services/otp-rental') ?>"><i class="fas fa-comment-sms"></i>Thuê số OTP</a>
                            <a href="<?= url('/services/cv') ?>"><i class="fas fa-user-pen"></i>Tạo CV</a>
                            <a href="<?= url('/services/forum') ?>"><i class="fas fa-comments"></i>Diễn đàn</a>
                            <a href="<?= url('/services/site-clone') ?>"><i class="fas fa-clone"></i>Copy 1-1 website</a>
                            <a href="<?= url('/services/site-auto') ?>"><i class="fas fa-wand-sparkles"></i>Tạo website A-Z</a>
                        </div>

                        <!-- Cột 4: Giải trí & khác -->
                        <div>
                            <div class="services-col-title">Giải trí &amp; khác</div>
                            <a href="<?= url('/services/media') ?>"><i class="fas fa-film"></i>Xem phim</a>
                            <a href="<?= url('/services/proxy') ?>"><i class="fas fa-network-wired"></i>Kho proxy</a>
                            <a href="<?= url('/services/infra') ?>"><i class="fas fa-server"></i>Domain &amp; hosting</a>
                            <a href="<?= url('/services/vps') ?>"><i class="fas fa-server"></i>Thuê VPS VMware</a>
                            <a href="<?= url('/services/vps/manage') ?>"><i class="fas fa-list"></i>VPS của tôi</a>
                            <a href="<?= url('/services/vps-storevps') ?>"><i class="fas fa-layer-group"></i>Thuê VPS StoreVPS</a>
                            <a href="<?= url('/services/vps-storevps/manage') ?>"><i class="fas fa-list-check"></i>VPS StoreVPS của tôi</a>
                            <a href="<?= url('/services/bank-lookup') ?>"><i class="fas fa-building-columns"></i>Tra cứu ngân hàng</a>
                            <a href="<?= url('/services/virtual-bank') ?>"><i class="fas fa-vault"></i>Bank ảo</a>
                            <a href="<?= url('/services/ui-scraper') ?>"><i class="fas fa-wand-magic-sparkles"></i>Đục UI/UX web</a>
                            <a href="<?= url('/services/phone-check') ?>"><i class="fas fa-mobile-screen-button"></i>Check số điện thoại</a>
                            <a href="<?= url('/services/sms') ?>"><i class="fas fa-comment-dots"></i>Gửi tin nhắn SMS</a>
                            <a href="<?= url('/services/social') ?>"><i class="fas fa-share-nodes"></i>Dịch vụ social &amp; buff</a>
                            <a href="<?= url('/services/sale') ?>"><i class="fas fa-paper-plane"></i>Dịch vụ sale</a>
                            <a href="<?= url('/services/pos') ?>"><i class="fas fa-cash-register"></i>Phần mềm bán hàng</a>
                            <a href="<?= url('/services/accounting') ?>"><i class="fas fa-calculator"></i>Phần mềm kế toán</a>
                        </div>

                        <!-- Cột 5: Tiện ích & kỹ thuật -->
                        <div>
                            <div class="services-col-title">Tiện ích &amp; kỹ thuật</div>
                            <a href="<?= url('/tools') ?>"><i class="fas fa-screwdriver-wrench"></i>Công cụ tiện ích</a>
                            <a href="<?= url('/services/telegram') ?>"><i class="fab fa-telegram-plane"></i>Telegram Mini App</a>
                            <a href="<?= url('/services/payment-api') ?>"><i class="fas fa-money-bill-transfer"></i>API thanh toán</a>
                            <a href="<?= url('/packages') ?>"><i class="fas fa-box-open"></i>Gói chơi</a>
                            <a href="<?= url('/market') ?>"><i class="fas fa-store"></i>Chợ tài nguyên</a>
                            <a href="<?= url('/vip') ?>"><i class="fas fa-gem"></i>VIP 60 cấp</a>
                            <a href="<?= url('/api-keys') ?>"><i class="fas fa-key"></i>API của tôi</a>
                            <a href="<?= url('/api/docs') ?>"><i class="fas fa-book"></i>Tài liệu API</a>
                            <a href="<?= url('/security/activity') ?>"><i class="fas fa-list-check"></i>Lịch sử hoạt động</a>
                            <a href="<?= url('/tickets') ?>"><i class="fas fa-headset"></i>Hỗ trợ</a>
                        </div>
                    </div>
                </li>

                <!-- ===== Trang chủ / Tin tức ===== -->
                <li class="nav-item"><a class="nav-link" href="<?= url('/') ?>"><i class="fas fa-home me-1"></i>Trang chủ</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= url('/news') ?>"><i class="fas fa-bullhorn me-1"></i>Tin tức</a></li>
            </ul>

            <form class="d-flex me-2 my-2 my-lg-0" action="<?= url('/games') ?>" method="GET">
                <div class="input-group input-group-sm">
                    <input type="text" name="q" class="form-control" placeholder="Tìm game..." value="<?= e($_GET['q'] ?? '') ?>" autocomplete="off">
                    <button class="btn btn-gold" type="submit"><i class="fas fa-search"></i></button>
                </div>
            </form>

            <ul class="navbar-nav align-items-lg-center gap-lg-2">
                <!-- Theme switcher: 1 nút, mở ra 3 lựa chọn -->
                <li class="nav-item dropdown">
                    <a class="nav-link theme-trigger" href="#" data-bs-toggle="dropdown"
                       role="button" aria-haspopup="true" aria-expanded="false" title="Giao diện">
                        <i class="fas fa-palette"></i>
                        <span class="theme-trigger-label"><?= $theme === 'dark' ? 'Tối' : ($theme === 'light' ? 'Sáng' : 'Tự động') ?></span>
                        <i class="fas fa-chevron-down theme-caret"></i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end theme-menu">
                        <li>
                            <a class="dropdown-item <?= $theme === 'dark' ? 'active' : '' ?>"
                               href="<?= url('/set-theme?theme=dark&back=' . urlencode($path)) ?>">
                                <i class="fas fa-moon me-2"></i>Giao diện tối
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?= $theme === 'light' ? 'active' : '' ?>"
                               href="<?= url('/set-theme?theme=light&back=' . urlencode($path)) ?>">
                                <i class="fas fa-sun me-2"></i>Giao diện sáng
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?= $theme === 'system' ? 'active' : '' ?>"
                               href="<?= url('/set-theme?theme=system&back=' . urlencode($path)) ?>">
                                <i class="fas fa-desktop me-2"></i>Theo hệ thống
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- Ngôn ngữ -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" title="Ngôn ngữ">
                        <i class="fas fa-globe me-1"></i><?= e(mb_strtoupper($locale)) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <?php foreach (available_locales() as $code => $info): ?>
                            <li>
                                <a class="dropdown-item <?= $code === $locale ? 'active' : '' ?>"
                                   href="<?= url('/set-language?lang=' . $code . '&back=' . urlencode($path)) ?>">
                                    <span class="me-2"><?= $info['flag'] ?></span><?= e($info['name']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </li>

                <?php if ($user): ?>
                    <li class="nav-item">
                        <a class="nav-link balance-chip" href="<?= url('/wallet') ?>">
                            <i class="fas fa-wallet me-1"></i><?= money($balance) ?>
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" data-bs-toggle="dropdown">
                            <?= user_avatar($user, 'avatar avatar-sm') ?>
                            <span class="d-none d-lg-inline"><?= e($user['username']) ?></span>
                            <?= verified_tick($user) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li class="px-3 py-2">
                                <div class="d-flex align-items-center gap-2">
                                    <?= user_avatar($user, 'avatar') ?>
                                    <div class="min-w-0">
                                        <div class="fw-semibold small"><?= e($user['full_name'] ?: $user['username']) ?> <?= verified_tick($user) ?></div>
                                        <div class="text-muted-2" style="font-size:.72rem">VIP <?= (int)($user['vip_level'] ?? 0) ?></div>
                                    </div>
                                </div>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="<?= url('/wallet') ?>"><i class="fas fa-wallet me-2"></i>Ví của tôi</a></li>
                            <li><a class="dropdown-item" href="<?= url('/wallet/transfer') ?>"><i class="fas fa-right-left me-2"></i>Chuyển tiền</a></li>
                            <li><a class="dropdown-item" href="<?= url('/wallet/history') ?>"><i class="fas fa-history me-2"></i>Lịch sử giao dịch</a></li>
                            <li><a class="dropdown-item" href="<?= url('/vip') ?>"><i class="fas fa-gem me-2"></i>VIP 60 cấp</a></li>
                            <li><a class="dropdown-item" href="<?= url('/child-sites') ?>"><i class="fas fa-sitemap me-2"></i>Website con</a></li>
                            <li><a class="dropdown-item" href="<?= url('/market') ?>"><i class="fas fa-store me-2"></i>Chợ tài nguyên</a></li>
                            <li><a class="dropdown-item" href="<?= url('/my-packages') ?>"><i class="fas fa-box me-2"></i>Gói của tôi</a></li>
                            <li><a class="dropdown-item" href="<?= url('/services/vps/manage') ?>"><i class="fas fa-server me-2"></i>VPS của tôi</a></li>
                            <li><a class="dropdown-item" href="<?= url('/api-keys') ?>"><i class="fas fa-key me-2"></i>API của tôi</a></li>
                            <li><a class="dropdown-item" href="<?= url('/security/activity') ?>"><i class="fas fa-list-check me-2"></i>Lịch sử hoạt động</a></li>
                            <li><a class="dropdown-item" href="<?= url('/security/profile') ?>"><i class="fas fa-user-gear me-2"></i>Hồ sơ &amp; bảo mật</a></li>
                            <li><a class="dropdown-item" href="<?= url('/security/2fa') ?>"><i class="fas fa-shield-halved me-2"></i>Bảo mật 2FA</a></li>
                            <li><a class="dropdown-item" href="<?= url('/cso/referrals') ?>"><i class="fas fa-users me-2"></i>Người giới thiệu</a></li>
                            <li><a class="dropdown-item" href="<?= url('/tickets') ?>"><i class="fas fa-headset me-2"></i>Hỗ trợ</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?= url('/logout') ?>"><i class="fas fa-right-from-bracket me-2"></i>Đăng xuất</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="<?= url('/login') ?>"><i class="fas fa-right-to-bracket me-1"></i>Đăng nhập</a></li>
                    <li class="nav-item"><a class="btn btn-gold btn-sm px-3" href="<?= url('/register') ?>">Đăng ký</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- ================= FLASH ================= -->
<?php if (!empty($__flash)): ?>
<div class="container mt-3">
    <?php foreach ($__flash as $msg): ?>
        <div class="alert alert-<?= e($msg['type'] === 'error' ? 'danger' : ($msg['type'] === 'success' ? 'success' : 'info')) ?> alert-dismissible fade show" role="alert">
            <?= e($msg['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<main class="main-content">
    <?= $content ?>
</main>

<!-- ================= FOOTER ================= -->
<footer class="site-footer mt-auto">
    <div class="container">
        <div class="row gy-4">
            <div class="col-lg-4">
                <h5 class="footer-brand"><i class="fas fa-dice me-2"></i><?= e($siteName) ?></h5>
                <p class="text-muted-2 small">Sảnh casino &amp; slots chất lượng cao. Tiền nạp đồng bộ trực tiếp với game.</p>
            </div>
            <div class="col-6 col-lg-2">
                <h6 class="footer-title">Khám phá</h6>
                <ul class="footer-links">
                    <li><a href="<?= url('/lobbies') ?>">Tất cả sảnh</a></li>
                    <li><a href="<?= url('/games') ?>">Kho game</a></li>
                    <li><a href="<?= url('/packages') ?>">Gói chơi</a></li>
                    <li><a href="<?= url('/vip') ?>">VIP 60 cấp</a></li>
                </ul>
            </div>
            <div class="col-6 col-lg-2">
                <h6 class="footer-title">Tài khoản</h6>
                <ul class="footer-links">
                    <li><a href="<?= url('/wallet') ?>">Ví tiền</a></li>
                    <li><a href="<?= url('/wallet/deposit') ?>">Nạp tiền</a></li>
                    <li><a href="<?= url('/wallet/transfer') ?>">Chuyển tiền</a></li>
                    <li><a href="<?= url('/market') ?>">Chợ tài nguyên</a></li>
                </ul>
            </div>
            <div class="col-lg-4">
                <h6 class="footer-title">Hỗ trợ</h6>
                <ul class="footer-links">
                    <li><a href="<?= url('/news') ?>">Tin tức &amp; sự kiện</a></li>
                    <li><a href="<?= url('/wallet/history') ?>">Lịch sử giao dịch</a></li>
                    <li><a href="<?= url('/security/activity') ?>">Lịch sử hoạt động</a></li>
                    <li><a href="<?= url('/tickets') ?>">Gửi yêu cầu hỗ trợ</a></li>
                    <li><a href="<?= url('/api/docs') ?>">Tài liệu API</a></li>
                </ul>
            </div>
        </div>
        <hr class="footer-divider">
        <?php $footerAds = \App\Services\AdService::forSlot('footer', 2); ?>
        <?php if (!empty($footerAds)): ?>
            <div class="ad-slot ad-slot-footer">
                <?php foreach ($footerAds as $ad): ?>
                    <?= \App\Services\AdService::render($ad) ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="text-center text-muted-2 small py-3">
            &copy; <?= date('Y') ?> <?= e($siteName) ?>. All rights reserved.
        </div>
    </div>
</footer>

<!-- ================= SUPPORT DOCK ================= -->
<div class="support-dock">
    <?php if ($telegram !== ''): ?>
        <a class="support-btn tg" href="<?= e($telegram) ?>" target="_blank" rel="noopener" title="Telegram hỗ trợ">
            <i class="fab fa-telegram-plane"></i>
        </a>
    <?php endif; ?>
    <?php if ($zalo !== ''): ?>
        <a class="support-btn zalo" href="<?= e($zalo) ?>" target="_blank" rel="noopener" title="Zalo hỗ trợ">
            <i class="fas fa-comment-dots"></i>
        </a>
    <?php endif; ?>
    <?php if ($hotline !== ''): ?>
        <a class="support-btn hotline" href="tel:<?= e(preg_replace('/\D+/', '', $hotline)) ?>" title="Hotline">
            <i class="fas fa-headset"></i>
        </a>
    <?php endif; ?>
    <a class="support-btn ticket" href="<?= url('/tickets') ?>" title="Gửi ticket hỗ trợ">
        <i class="fas fa-life-ring"></i>
    </a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.1/dist/sweetalert2.all.min.js"></script>
<script>window.APP = { baseUrl: '<?= url('/') ?>', csrfToken: '<?= csrf_token() ?>', isLoggedIn: <?= $user ? 'true' : 'false' ?>, antiDebug: <?= setting('anti_debug_enabled', '1') === '1' ? 'true' : 'false' ?> };</script>
<script src="<?= asset('js/app.js?v=3') ?>"></script>
<?= $__scripts ?? '' ?>
</body>
</html>
