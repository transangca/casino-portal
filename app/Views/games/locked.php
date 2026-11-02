<?php /** @var array $game,$packages,$balance,$check */ ?>
<div class="container">
    <div class="locked-card">
        <div class="locked-icon"><i class="fas fa-lock"></i></div>
        <h2 class="fw-bold mb-2">Cần gói chơi để chơi game này</h2>
        <p class="text-secondary mb-4">
            Số dư hiện tại: <strong class="text-gold"><?= money($balance) ?></strong>.
            Chọn một gói bên dưới để bắt đầu chơi <strong><?= e($game['name']) ?></strong>.
        </p>

        <?php if (!$packages): ?>
            <p class="text-secondary">Hiện chưa có gói nào khả dụng. Vui lòng liên hệ admin.</p>
        <?php else: ?>
            <div class="row g-3 text-start mb-4">
                <?php foreach ($packages as $p): ?>
                    <div class="col-md-6">
                        <div class="pkg-card <?= $p['is_free_trial'] ? 'featured' : '' ?>">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <div class="pkg-icon"><i class="fas <?= $p['is_free_trial'] ? 'fa-gift' : 'fa-clock' ?>"></i></div>
                                <div>
                                    <div class="fw-bold"><?= e($p['name']) ?></div>
                                    <div class="game-provider"><?= (int)$p['duration_minutes'] ?> phút</div>
                                </div>
                            </div>
                            <div class="pkg-price">
                                <?= (float)$p['price'] > 0 ? money((float)$p['price']) : 'MIỄN PHÍ' ?>
                            </div>
                            <form method="POST" action="<?= url('/packages/purchase') ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="package_id" value="<?= (int)$p['id'] ?>">
                                <button class="btn btn-primary w-100">Mua &amp; chơi ngay</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <a href="<?= url('/wallet/deposit') ?>" class="btn btn-outline-light btn-sm">
            <i class="fas fa-plus me-1"></i>Nạp tiền vào ví
        </a>
        <a href="<?= url('/games') ?>" class="btn btn-outline-secondary btn-sm">Về kho game</a>
    </div>
</div>
