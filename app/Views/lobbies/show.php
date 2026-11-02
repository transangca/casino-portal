<?php
/**
 * Trang sảnh riêng — có 2 tab: Chơi thử (demo) & Đồng bộ ví (real).
 *
 * @var array  $provider
 * @var string $mode
 * @var bool   $supportsWallet
 * @var array  $games
 * @var int    $total
 * @var array  $siblings
 * @var array  $ads
 */
$walletMode = (string)($provider['wallet_mode'] ?? 'demo_only');
$icon = (string)($provider['logo'] ?? '');
$promoBg = provider_promo($provider);
?>
<div class="container py-4">

    <!-- ===== Hero sảnh (gọn — không ảnh bìa lớn) ===== -->
    <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
        <?php if ($icon !== ''): ?>
            <?= provider_logo($provider, 'provider-tile-logo') ?>
        <?php else: ?>
            <span class="provider-tile-logo provider-tile-fallback"><i class="fas fa-crown"></i></span>
        <?php endif; ?>
        <div class="flex-fill min-w-0">
            <h1 class="page-title mb-1"><?= e($provider['name']) ?></h1>
            <div class="d-flex flex-wrap gap-2">
                <span class="chip-soft"><i class="fas fa-gamepad me-1"></i><?= (int)$total ?> game</span>
                <?php if ($supportsWallet): ?>
                    <span class="chip-soft"><i class="fas fa-link me-1"></i>Đồng bộ ví 2 chiều</span>
                <?php else: ?>
                    <span class="chip-soft"><i class="fas fa-flask me-1"></i>Chơi thử miễn phí</span>
                <?php endif; ?>
                <?php if (!empty($provider['category_name'])): ?>
                    <span class="chip-soft"><?= e((string)$provider['category_name']) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($promoBg !== ''): ?>
            <a href="<?= e($promoBg) ?>" target="_blank" rel="noopener" class="btn btn-outline-light btn-sm">
                <i class="fas fa-image me-1"></i>Ảnh nhà phát hành
            </a>
        <?php endif; ?>
    </div>

    <?php if (!empty($provider['description'])): ?>
        <p class="page-sub"><?= e($provider['description']) ?></p>
    <?php endif; ?>

    <!-- ===== Banner quảng cáo ===== -->
    <?php if (!empty($ads)): ?>
        <div class="ad-slot mb-4">
            <?php foreach ($ads as $ad): ?>
                <?= \App\Services\AdService::render($ad) ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- ===== Trailer / promo nhà phát hành ===== -->
    <?php
    $promoVideo = media_url((string)($provider['promo_video'] ?? ''));
    $videoPoster = media_url((string)($provider['video_poster'] ?? ''));
    ?>
    <?php if ($promoVideo !== '' || $videoPoster !== ''): ?>
        <div class="provider-promo mb-4">
            <?php if ($promoVideo !== ''): ?>
                <video src="<?= e($promoVideo) ?>" controls preload="metadata"
                       <?= $videoPoster !== '' ? 'poster="' . e($videoPoster) . '"' : '' ?>></video>
            <?php else: ?>
                <div class="promo-poster" style="background-image:url('<?= e($videoPoster) ?>')"></div>
            <?php endif; ?>
            <div class="promo-caption">
                <h4>Giới thiệu <?= e($provider['name']) ?></h4>
                <p><?= e($provider['description'] ?? 'Video giới thiệu chính thức từ nhà phát hành.') ?></p>
            </div>
        </div>
    <?php endif; ?>

    <!-- ===== Đổi sảnh cùng danh mục (thu gọn / mở rộng) ===== -->
    <?php if (!empty($siblings)): ?>
        <div class="provider-switcher" id="providerSwitcher">
            <div class="provider-switcher-head">
                <span class="provider-switcher-title">
                    <i class="fas fa-layer-group me-1"></i>Đổi sảnh nhanh
                </span>
                <button type="button" class="provider-switcher-toggle" id="providerToggle"
                        aria-expanded="false" aria-controls="providerPills">
                    <i class="fas fa-chevron-down"></i>
                    <span class="toggle-text">Mở rộng</span>
                </button>
            </div>

            <!-- Logo thương hiệu (luôn hiện, phần dưới khi thu gọn) -->
            <div class="provider-logo-rail" id="providerLogoRail">
                <?php foreach ($siblings as $s): ?>
                    <a href="<?= url('/lobbies/' . $s['code']) ?>"
                       class="provider-logo-chip <?= $s['code'] === $provider['code'] ? 'active' : '' ?>"
                       title="<?= e((string)$s['name']) ?>">
                        <?php $logo = provider_logo_url($s); ?>
                        <?php if ($logo !== ''): ?>
                            <img src="<?= e($logo) ?>" alt="<?= e((string)$s['name']) ?>" loading="lazy" decoding="async" onerror="this.remove()">
                        <?php else: ?>
                            <span class="provider-logo-fallback"><i class="fas fa-crown"></i></span>
                        <?php endif; ?>
                        <span class="provider-logo-name"><?= e((string)$s['name']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Danh sách sảnh dạng pill (mở rộng) -->
            <div class="provider-pills" id="providerPills">
                <a href="<?= url('/lobbies') ?>" class="provider-pill <?= count($siblings) === 1 ? 'active' : '' ?>">
                    <i class="fas fa-layer-group"></i>Tất cả sảnh
                </a>
                <?php foreach ($siblings as $s): ?>
                    <a href="<?= url('/lobbies/' . $s['code']) ?>"
                       class="provider-pill <?= $s['code'] === $provider['code'] ? 'active' : '' ?>">
                        <?php if (!empty($s['logo'])): ?>
                            <img src="<?= e(provider_logo_url($s)) ?>" alt="" class="pill-logo" onerror="this.remove()">
                        <?php endif; ?>
                        <?= e($s['name']) ?>
                        <span class="pill-count">(<?= (int)$s['game_count'] ?>)</span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- ===== Tabs chế độ ===== -->
    <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
        <div class="mode-tabs">
            <a href="<?= url('/lobbies/' . $provider['code'] . '?mode=demo') ?>"
               class="<?= $mode === 'demo' ? 'active' : '' ?>">
                <i class="fas fa-flask me-1"></i>Chơi thử
            </a>
            <?php if ($supportsWallet): ?>
                <a href="<?= url('/lobbies/' . $provider['code'] . '?mode=real') ?>"
                   class="<?= $mode === 'real' ? 'active' : '' ?>">
                    <i class="fas fa-link me-1"></i>Đồng bộ ví
                </a>
            <?php endif; ?>
        </div>

        <form class="d-flex ms-auto" action="<?= url('/lobbies/' . $provider['code']) ?>" method="GET">
            <input type="hidden" name="mode" value="<?= e($mode) ?>">
            <div class="input-group input-group-sm" style="max-width:260px">
                <input type="text" name="q" class="form-control" placeholder="Tìm trong sảnh..." value="<?= e($q) ?>">
                <button class="btn btn-gold" type="submit"><i class="fas fa-search"></i></button>
            </div>
        </form>
    </div>

    <p class="text-muted-2 small">
        <?php if ($mode === 'real'): ?>
            <i class="fas fa-circle-info me-1"></i>Chế độ đồng bộ ví: tiền nạp trên website hiển thị ngay trong game, thắng cược được ghi nhận về ví.
        <?php else: ?>
            <i class="fas fa-circle-info me-1"></i>Chế độ chơi thử: dùng tiền ảo, ai cũng chơi được, không cần nạp tiền.
        <?php endif; ?>
    </p>

    <!-- ===== Nhúng lobby chính thức (studio không có API danh mục) ===== -->
    <?php if (!empty($embedLobby) && !empty($embedUrl)): ?>
        <div class="lobby-embed mb-4">
            <div class="lobby-embed-head">
                <span class="lobby-chip demo"><i class="fas fa-flask"></i>Chơi thử trực tiếp</span>
                <span class="text-muted-2 small">
                    Đây là lobby/demo chính thức của <strong><?= e($provider['name']) ?></strong>.
                    Chế độ dùng tiền ảo, không cần nạp tiền.
                </span>
                <a href="<?= e($embedUrl) ?>" target="_blank" rel="noopener"
                   class="btn btn-sm btn-outline-gold ms-auto">
                    <i class="fas fa-arrow-up-right-from-square me-1"></i>Mở tab mới
                </a>
            </div>
            <div class="lobby-embed-frame">
                <iframe src="<?= e($embedUrl) ?>" title="Lobby <?= e($provider['name']) ?>"
                        loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                        allow="autoplay; fullscreen; gamepad; clipboard-write"
                        allowfullscreen></iframe>
            </div>
        </div>
    <?php elseif (!empty($provider['lobby_url']) && !$games): ?>
        <!-- Sảnh chưa có danh mục game & lobby là trang marketing → không nhúng iframe -->
        <div class="lobby-external-note mb-4">
            <i class="fas fa-circle-info me-2"></i>
            <span>
                Sảnh <strong><?= e($provider['name']) ?></strong> hiện chưa công bố danh mục game công khai.
                Bạn có thể mở <a href="<?= e($provider['lobby_url']) ?>" target="_blank" rel="noopener">trang chính thức của nhà phát hành</a>
                để xem & chơi thử trực tiếp.
            </span>
        </div>
    <?php endif; ?>

    <!-- ===== Danh sách game ===== -->
    <?php if (!$games): ?>
        <?php if (empty($embedLobby)): ?>
        <div class="empty-state">
            <i class="fas fa-box-open fs-1 d-block mb-3"></i>
            <p class="mb-3">Danh mục game của sảnh này đang được cập nhật. Vui lòng quay lại sau!</p>
            <a href="<?= url('/lobbies') ?>" class="btn btn-outline-gold btn-sm">
                <i class="fas fa-rotate-left me-1"></i>Xem tất cả sảnh
            </a>
        </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="game-grid">
            <?php foreach ($games as $g): ?>
                <a href="<?= url('/games/' . $g['slug']) ?>" class="game-card text-decoration-none">
                    <?php if (!empty($g['is_hot'])): ?>
                        <span class="icon-badge badge-hot">HOT</span>
                    <?php endif; ?>
                    <?php if (!empty($g['is_new'])): ?>
                        <span class="icon-badge badge-new">MỚI</span>
                    <?php endif; ?>
                    <?php if (!empty($g['is_featured'])): ?>
                        <span class="icon-badge badge-featured">NỔI BẬT</span>
                    <?php endif; ?>

                    <div class="game-thumb">
                        <?php if (!empty($g['icon'])): ?>
                            <img src="<?= e(media_url((string)$g['icon'])) ?>" alt="<?= e($g['name']) ?>"
                                 loading="lazy" decoding="async" width="300" height="300"
                                 onerror="this.onerror=null;this.src='<?= asset('img/placeholder.svg') ?>';">
                        <?php else: ?>
                            <img src="<?= asset('img/placeholder.svg') ?>" alt="<?= e($g['name']) ?>" loading="lazy">
                        <?php endif; ?>
                        <div class="game-overlay"></div>

                        <?php if (!empty($provider['logo'])): ?>
                            <span class="game-provider-badge">
                                <img src="<?= e(provider_logo_url($provider)) ?>" alt="" onerror="this.remove()">
                                <span><?= e((string)$provider['name']) ?></span>
                            </span>
                        <?php endif; ?>

                        <?php
                        $launchState = (string)($g['launch_state'] ?? 'ready');
                        $playMode    = (string)($g['play_mode'] ?? 'demo');
                        ?>
                        <?php if ($launchState !== 'ready'): ?>
                            <span class="game-mode-badge demo" title="Sản chưa công bố API đồng bộ ví — chỉ chơi thử">
                                <i class="fas fa-flask"></i> Demo
                            </span>
                        <?php elseif ($playMode === 'real'): ?>
                            <span class="game-mode-badge real" title="Đồng bộ ví 2 chiều">
                                <i class="fas fa-link"></i> Đồng bộ ví
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="game-name" title="<?= e($g['name']) ?>"><?= e($g['name']) ?></div>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Phân trang -->
        <?php if ($pages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination">
                    <?php for ($i = 1; $i <= $pages; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link"
                               href="<?= url('/lobbies/' . $provider['code'] . '?mode=' . $mode . '&q=' . urlencode($q) . '&page=' . $i) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
(function () {
    const toggle = document.getElementById('providerToggle');
    const pills  = document.getElementById('providerPills');
    const switcher = document.getElementById('providerSwitcher');
    if (!toggle || !pills || !switcher) return;

    // Mặc định thu gọn: chỉ hiện dải logo thương hiệu
    switcher.classList.add('collapsed');

    toggle.addEventListener('click', function () {
        const collapsed = switcher.classList.toggle('collapsed');
        toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        toggle.querySelector('.toggle-text').textContent = collapsed ? 'Mở rộng' : 'Thu gọn';
        toggle.querySelector('i').className = collapsed ? 'fas fa-chevron-down' : 'fas fa-chevron-up';
    });
})();
</script>
