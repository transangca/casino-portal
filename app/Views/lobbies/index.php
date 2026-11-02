<?php
/**
 * Danh sách tất cả sảnh game — giao diện gọn (không ảnh lớn), có nút mở rộng / thu gọn.
 *
 * @var array $providers
 * @var array $categories
 * @var string $category
 * @var string $q
 */
$visibleCount = 8;
$hasMore      = count($providers) > $visibleCount;
?>
<div class="container py-4">
    <div class="d-flex flex-wrap align-items-center gap-3 mb-2">
        <h1 class="page-title mb-0">Sảnh game</h1>
        <span class="chip-soft"><?= count($providers) ?> sảnh</span>
        <span class="chip-soft"><?= count($categories) ?> danh mục</span>
    </div>
    <p class="page-sub">Sảnh nào đồng bộ ví sẽ có tab riêng.</p>

    <!-- ===== Dải logo nhà phát hành (thu gọn / mở rộng) ===== -->
    <?php if ($providers): ?>
        <div class="provider-switcher" id="providerSwitcher">
            <div class="provider-switcher-head">
                <span class="provider-switcher-title">
                    <i class="fas fa-layer-group me-1"></i>Nhà phát hành
                    <span class="text-muted-2">(<?= count($providers) ?>)</span>
                </span>
                <button type="button" class="provider-switcher-toggle" id="providerToggle"
                        aria-expanded="false" aria-controls="providerPills">
                    <i class="fas fa-chevron-down"></i>
                    <span class="toggle-text">Mở rộng</span>
                </button>
            </div>

            <!-- Logo thương hiệu nhỏ (luôn hiện) -->
            <div class="provider-logo-rail" id="providerLogoRail">
                <?php foreach ($providers as $p): ?>
                    <a href="<?= url('/lobbies/' . $p['code']) ?>"
                       class="provider-logo-chip"
                       title="<?= e((string)$p['name']) ?>">
                        <?php if (provider_logo_url($p) !== ''): ?>
                            <img src="<?= e(provider_logo_url($p)) ?>" alt="<?= e((string)$p['name']) ?>"
                                 loading="lazy" decoding="async" onerror="this.remove()">
                        <?php else: ?>
                            <span class="provider-logo-fallback"><i class="fas fa-crown"></i></span>
                        <?php endif; ?>
                        <span class="provider-logo-name"><?= e((string)$p['name']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Danh sách đầy đủ (mở rộng) -->
            <div class="provider-pills" id="providerPills">
                <a href="<?= url('/lobbies') ?>" class="provider-pill <?= $category === '' ? 'active' : '' ?>">
                    <i class="fas fa-layer-group"></i>Tất cả sảnh
                </a>
                <?php foreach ($providers as $p): ?>
                    <a href="<?= url('/lobbies/' . $p['code']) ?>" class="provider-pill">
                        <?php if (provider_logo_url($p) !== ''): ?>
                            <img src="<?= e(provider_logo_url($p)) ?>" alt="" class="pill-logo" onerror="this.remove()">
                        <?php endif; ?>
                        <?= e((string)$p['name']) ?>
                        <span class="pill-count">(<?= (int)$p['game_count'] ?>)</span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Danh mục -->
    <div class="d-flex flex-wrap mb-3">
        <a href="<?= url('/lobbies') ?>" class="filter-chip <?= $category === '' ? 'active' : '' ?>">
            <i class="fas fa-layer-group me-1"></i>Tất cả
        </a>
        <?php foreach ($categories as $c): ?>
            <a href="<?= url('/lobbies?category=' . urlencode($c['code'])) ?>"
               class="filter-chip <?= $category === $c['code'] ? 'active' : '' ?>">
                <i class="fas <?= e($c['icon'] ?: 'fa-gamepad') ?> me-1"></i><?= e($c['name']) ?>
                <span class="text-muted-2">(<?= (int)$c['provider_count'] ?>)</span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Tìm kiếm -->
    <form class="d-flex mb-4" action="<?= url('/lobbies') ?>" method="GET">
        <?php if ($category !== ''): ?>
            <input type="hidden" name="category" value="<?= e($category) ?>">
        <?php endif; ?>
        <div class="input-group" style="max-width:420px">
            <input type="text" name="q" class="form-control" placeholder="Tìm sảnh..." value="<?= e($q) ?>">
            <button class="btn btn-gold" type="submit"><i class="fas fa-search"></i></button>
        </div>
    </form>

    <!-- Danh sách sảnh — dạng thẻ gọn, không ảnh nền lớn -->
    <?php if (!$providers): ?>
        <div class="empty-state">
            <i class="fas fa-ghost fs-1 d-block mb-3"></i>
            <p class="mb-0">Không tìm thấy sảnh nào phù hợp.</p>
        </div>
    <?php else: ?>
        <div class="row g-2" id="providerGrid">
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
                            <span class="provider-tile-count">
                                <?= (int)$p['game_count'] ?> game<?php if ($p['category_name']): ?> · <?= e($p['category_name']) ?><?php endif; ?>
                            </span>
                        </span>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($hasMore): ?>
            <div class="text-center mt-3">
                <button type="button" class="btn btn-outline-gold btn-sm" id="providerMoreToggle"
                        data-visible="<?= $visibleCount ?>">
                    <i class="fas fa-chevron-down me-1"></i>
                    <span class="toggle-text">Mở rộng (<?= count($providers) - $visibleCount ?> sảnh nữa)</span>
                </button>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
(function () {
    'use strict';

    // ---- Thu gọn / mở rộng danh sách sảnh (dải pill) ----
    var toggle = document.getElementById('providerToggle');
    var pills  = document.getElementById('providerPills');
    var switcher = document.getElementById('providerSwitcher');
    if (toggle && pills && switcher) {
        switcher.classList.add('collapsed');

        toggle.addEventListener('click', function () {
            const collapsed = switcher.classList.toggle('collapsed');
            toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            toggle.querySelector('.toggle-text').textContent = collapsed ? 'Mở rộng' : 'Thu gọn';
            toggle.querySelector('i').className = collapsed ? 'fas fa-chevron-down' : 'fas fa-chevron-up';
        });
    }

    // ---- Mở rộng / thu gọn lưới thẻ sảnh ----
    var moreBtn = document.getElementById('providerMoreToggle');
    if (moreBtn) {
        moreBtn.addEventListener('click', function () {
            const hidden = document.querySelectorAll('.provider-grid-item.is-hidden');
            const isHidden = hidden.length > 0;

            hidden.forEach(function (el) {
                el.classList.toggle('is-hidden', !isHidden);
            });

            moreBtn.querySelector('.toggle-text').textContent = isHidden ? 'Thu gọn' : 'Mở rộng';
            moreBtn.querySelector('i').className = isHidden ? 'fas fa-chevron-up' : 'fas fa-chevron-down';
        });
    }
})();
</script>
