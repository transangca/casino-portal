<?php
/**
 * 60 cấp VIP với badge 3D.
 *
 * @var array  $tiers
 * @var int    $level
 * @var float  $total
 * @var array|null $nextTier
 * @var float  $progress
 * @var int    $minVip
 * @var float  $maxShare
 */
?>
<div class="container py-4">
    <h1 class="page-title"><i class="fas fa-gem text-gold me-2"></i><?= t('vip.title') ?></h1>
    <p class="page-sub">60 cấp bậc với ưu đãi riêng. Từ VIP <?= (int)$minVip ?> trở lên được tạo website con (ăn chia tối đa <?= number_format($maxShare * 100, 1) ?>%).</p>

    <!-- Thẻ cấp hiện tại -->
    <div class="panel-card mb-4">
        <div class="row align-items-center g-4">
            <div class="col-lg-4 text-center">
                <div class="vip-badge-wrap" data-level="<?= max(1, $level) ?>">
                    <div class="vip-badge vip-badge-3d <?= \App\Services\VipService::groupClass(max(1, $level)) ?>">
                        <?= \App\Services\VipService::badge(max(1, $level), 128) ?>
                    </div>
                    <div class="mt-2">
                        <span class="badge-status st-active"><?= t('vip.current') ?> · VIP <?= (int)$level ?></span>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <h3 class="mb-1" style="font-size:1.15rem">
                    <?= e($tiers ? ($tiers[array_search($level, array_column($tiers, 'level'), true)]['name'] ?? 'VIP ' . $level) : 'VIP ' . $level) ?>
                </h3>
                <p class="text-muted-2 small">
                    Tổng đã nạp: <strong class="text-gold"><?= money($total) ?></strong>
                    <?php if ($nextTier): ?>
                        · Cần <?= money((float)$nextTier['deposit_required']) ?> để lên VIP <?= (int)$nextTier['level'] ?>
                    <?php else: ?>
                        · Đã đạt cấp cao nhất
                    <?php endif; ?>
                </p>

                <?php if ($nextTier): ?>
                    <div class="progress" style="height:8px;background:rgba(255,255,255,.06)">
                        <div class="progress-bar" style="width:<?= number_format($progress, 1) ?>%;background:linear-gradient(90deg,var(--casino-gold),#ff9d3d)"></div>
                    </div>
                    <div class="form-hint mt-1"><?= number_format($progress, 1) ?>%</div>
                <?php endif; ?>

                <div class="d-flex flex-wrap gap-2 mt-3">
                    <a href="<?= url('/child-sites') ?>" class="btn btn-sm btn-outline-gold">
                        <i class="fas fa-sitemap me-1"></i><?= t('vip.create_child') ?>
                    </a>
                    <a href="<?= url('/wallet/deposit') ?>" class="btn btn-sm btn-gold">
                        <i class="fas fa-plus me-1"></i>Nạp tiền nâng cấp
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Lưới 60 cấp -->
    <div class="section-head">
        <h2><i class="fas fa-layer-group"></i>Danh sách 60 cấp bậc</h2>
    </div>

    <div class="vip-tier-grid">
        <?php foreach ($tiers as $t): ?>
            <?php $lvl = (int)$t['level']; ?>
            <?php $perks = json_decode((string)($t['perks'] ?? '[]'), true) ?: []; ?>
            <div class="vip-tier-card <?= $lvl === $level ? 'current' : '' ?>" data-level="<?= $lvl ?>">
                <div class="vip-badge vip-badge-3d <?= \App\Services\VipService::groupClass($lvl) ?>">
                    <?= \App\Services\VipService::badge($lvl, 62) ?>
                </div>
                <div class="tier-name"><?= e($t['name']) ?></div>
                <div class="tier-req"><?= money((float)$t['deposit_required']) ?></div>
                <ul class="tier-perks ps-0">
                    <?php foreach (array_slice($perks, 0, 3) as $perk): ?>
                        <li><?= e($perk) ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php if (!empty($t['can_child_site'])): ?>
                    <div class="mt-2">
                        <span class="news-tag"><i class="fas fa-sitemap me-1"></i>Ăn chia <?= number_format((float)$t['rev_share_max'] * 100, 1) ?>%</span>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
