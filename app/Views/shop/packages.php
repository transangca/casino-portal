<?php /** @var array $packages,$active,$balance,$remaining */ ?>
<div class="container py-4">
    <h1 class="page-title">Gói chơi</h1>
    <p class="page-sub">Mua gói theo thời gian để mở khóa toàn bộ game trong sảnh.</p>

    <?php if ($active): ?>
    <div class="panel-card" style="border-color:var(--gold)">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <div class="game-provider mb-1">Gói đang hiệu lực</div>
                <h4 class="mb-1"><?= e($active['package_name']) ?></h4>
                <div class="text-secondary small">
                    Hết hạn lúc <?= date('d/m/Y H:i', strtotime($active['expires_at'])) ?>
                    · Còn <strong class="text-gold" id="pkgRemain"><?= gmdate('H:i:s', $remaining) ?></strong>
                </div>
            </div>
            <a href="<?= url('/games') ?>" class="btn btn-play">Chơi ngày</a>
        </div>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <?php foreach ($packages as $i => $p): ?>
        <div class="col-md-6 col-lg-4">
            <div class="pkg-card <?= $p['is_free_trial'] ? 'featured' : '' ?>">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <div class="pkg-icon"><i class="fas <?= $p['is_free_trial'] ? 'fa-gift' : ($i % 2 ? 'fa-bolt' : 'fa-clock') ?>"></i></div>
                    <div>
                        <div class="fw-bold"><?= e($p['name']) ?></div>
                        <div class="game-provider"><?= (int)$p['duration_minutes'] ?> phút chơi</div>
                    </div>
                </div>

                <div class="pkg-price">
                    <?php if ((float)$p['price'] > 0): ?>
                        <?= money((float)$p['price']) ?>
                        <?php if ($p['original_price'] && (float)$p['original_price'] > (float)$p['price']): ?>
                            <small class="text-decoration-line-through"><?= money((float)$p['original_price']) ?></small>
                        <?php endif; ?>
                    <?php else: ?>
                        MIỄN PHÍ
                    <?php endif; ?>
                </div>

                <?php if (!empty($p['description'])): ?>
                    <p class="text-secondary small"><?= e($p['description']) ?></p>
                <?php endif; ?>

                <ul class="pkg-features">
                    <li><i class="fas fa-check"></i>Mở khóa toàn bộ game sảnh</li>
                    <li><i class="fas fa-check"></i>Ví đồng bộ trực tiếp</li>
                    <li><i class="fas fa-check"></i>Đếm ngược thời gian realtime</li>
                    <?php if (!empty($p['provider_code'])): ?>
                        <li><i class="fas fa-check"></i>Chỉ áp dụng chờ sảnh <?= e($p['provider_code']) ?></li>
                    <?php endif; ?>
                    <?php if ($p['is_free_trial']): ?>
                        <li><i class="fas fa-check"></i>Lượt dùng: <?= (int)$p['trial_limit_per_user'] ?>/người</li>
                    <?php endif; ?>
                </ul>

                <?php if (\App\Core\Auth::check()): ?>
                    <form method="POST" action="<?= url('/packages/purchase') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="package_id" value="<?= (int)$p['id'] ?>">
                        <button class="btn <?= $p['is_free_trial'] ? 'btn-primary' : 'btn-outline-light' ?> w-100">
                            <?= $p['is_free_trial'] ? 'Dùng ngay' : 'Mua gói' ?>
                        </button>
                    </form>
                <?php else: ?>
                    <a href="<?= url('/login') ?>" class="btn btn-outline-light w-100">Đăng nhập để mua</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
(function () {
    var el = document.getElementById('pkgRemain');
    if (!el) return;
    var secs = <?= (int)$remaining ?>;
    setInterval(function () {
        secs = Math.max(0, secs - 1);
        var h = Math.floor(secs / 3600), m = Math.floor((secs % 3600) / 60), s = secs % 60;
        el.textContent = (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
    }, 1000);
})();
</script>
