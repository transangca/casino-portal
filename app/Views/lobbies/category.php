<?php
/**
 * Trang danh mục: Casino / Slot / Bắn Cá / Blockchain / Thể thao / Game bài / Xổ số.
 * Giao diện gọn — bỏ ảnh bìa lớn, thẻ sảnh nhỏ gọn + nút mở rộng / thu gọn.
 *
 * @var array $cat
 * @var array $providers
 * @var array $games
 * @var array $ads
 */
$visibleCount = 8;
$hasMore      = count($providers) > $visibleCount;
?>
<div class="container py-4">

    <!-- ===== Header gọn (không ảnh bìa lớn) ===== -->
    <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
        <span class="brand-icon brand-icon-lg"><i class="fas <?= e($cat['icon'] ?: 'fa-gamepad') ?>"></i></span>
        <div class="flex-fill min-w-0">
            <h1 class="page-title mb-1"><?= e($cat['name']) ?></h1>
            <div class="d-flex flex-wrap gap-2">
                <span class="chip-soft"><i class="fas fa-crown me-1"></i><?= count($providers) ?> sảnh</span>
                <span class="chip-soft"><i class="fas fa-gamepad me-1"></i><?= count($games) ?> game nổi bật</span>
            </div>
        </div>
        <a href="<?= url('/lobbies?category=' . urlencode($cat['code'])) ?>" class="btn btn-outline-gold btn-sm">
            Xem tất cả →
        </a>
    </div>

    <?php if (!empty($cat['description'])): ?>
        <p class="page-sub"><?= e($cat['description']) ?></p>
    <?php endif; ?>

    <?php if (!empty($ads)): ?>
        <div class="ad-slot mb-4">
            <?php foreach ($ads as $ad): ?>
                <?= \App\Services\AdService::render($ad) ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Sảnh trong danh mục -->
    <div class="section-head">
        <h2><i class="fas fa-crown"></i>Sảnh <?= e($cat['name']) ?></h2>
    </div>

    <?php if (!$providers): ?>
        <div class="empty-state"><p class="mb-0">Danh mục này đang được cập nhật.</p></div>
    <?php else: ?>
        <div class="row g-2 mb-4" id="providerGrid">
            <?php foreach ($providers as $i => $p): ?>
                <div class="col-6 col-md-4 col-lg-3 provider-grid-item<?= $i >= $visibleCount ? ' is-hidden' : '' ?>">
                    <a href="<?= url('/lobbies/' . $p['code']) ?>" class="provider-tile">
                        <?php if (provider_logo_url($p) !== ''): ?>
                            <img src="<?= e(provider_logo_url($p)) ?>" alt="<?= e((string)$p['name']) ?>"
                                 class="provider-tile-logo" loading="lazy" decoding="async" onerror="this.remove()">
                        <?php else: ?>
                            <span class="provider-tile-logo provider-tile-fallback"><i class="fas fa-crown"></i></span>
                        <?php endif; ?>
                        <span class="provider-tile-body">
                            <span class="provider-tile-name"><?= e((string)$p['name']) ?></span>
                            <span class="provider-tile-count"><?= (int)$p['game_count'] ?> game</span>
                        </span>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($hasMore): ?>
            <div class="text-center mt-3 mb-4">
                <button type="button" class="btn btn-outline-gold btn-sm" id="providerMoreToggle">
                    <i class="fas fa-chevron-down me-1"></i>
                    <span class="toggle-text">Mở rộng (<?= count($providers) - $visibleCount ?> sảnh nữa)</span>
                </button>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Game nổi bật -->
    <?php if ($games): ?>
        <div class="section-head">
            <h2><i class="fas fa-star"></i>Game nổi bật</h2>
        </div>
        <?php $gameList = $games; include BASE_PATH . '/app/Views/partials/game-grid.php'; ?>
    <?php endif; ?>
</div>

<script>
(function () {
    'use strict';
    var moreBtn = document.getElementById('providerMoreToggle');
    if (!moreBtn) return;

    moreBtn.addEventListener('click', function () {
        var hidden = document.querySelectorAll('.provider-grid-item.is-hidden');
        var isHidden = hidden.length > 0;

        hidden.forEach(function (el) { el.classList.toggle('is-hidden', !isHidden); });

        moreBtn.querySelector('.toggle-text').textContent = isHidden ? 'Thu gọn' : 'Mở rộng';
        moreBtn.querySelector('i').className = isHidden ? 'fas fa-chevron-up' : 'fas fa-chevron-down';
    });
})();
</script>
