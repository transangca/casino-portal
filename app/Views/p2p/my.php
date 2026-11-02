<?php /** @var array $orders,$trades */ ?>
<div class="container py-4">
    <h1 class="page-title">P2P của tôi</h1>
    <p class="page-sub">Tin bán của bạn và các giao dịch đang diễn ra.</p>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="panel-card">
                <h5 class="mb-3"><i class="fas fa-store me-2"></i>Tin bán của tôi</h5>
                <?php if (!$orders): ?>
                    <p class="text-muted-2 small mb-0">Bạn chưa đăng tin nào.</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($orders as $o): ?>
                            <a href="<?= url('/p2p/order/' . $o['id']) ?>" class="list-group-item list-group-item-action bg-transparent text-reset">
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong class="small"><?= e($o['title']) ?></strong>
                                    <span class="badge bg-<?= $o['status'] === 'open' ? 'success' : ($o['status'] === 'reserved' ? 'warning' : 'secondary') ?>">
                                        <?= e(\App\Services\P2PService::statusLabel((string)$o['status'])) ?>
                                    </span>
                                </div>
                                <div class="small text-muted-2 mt-1">
                                    <?= money((float)$o['price']) ?> · Còn <?= (int)$o['remain'] ?> · <?= e($o['order_no']) ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="panel-card">
                <h5 class="mb-3"><i class="fas fa-right-left me-2"></i>Giao dịch</h5>
                <?php if (!$trades): ?>
                    <p class="text-muted-2 small mb-0">Bạn chưa có giao dịch nào.</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($trades as $t): ?>
                            <a href="<?= url('/p2p/trade/' . $t['trade_no']) ?>" class="list-group-item list-group-item-action bg-transparent text-reset">
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong class="small"><?= e($t['title']) ?></strong>
                                    <span class="badge bg-<?= $t['status'] === 'completed' ? 'success' : ($t['status'] === 'disputed' ? 'danger' : 'warning') ?>">
                                        <?= e(\App\Services\P2PService::statusLabel((string)$t['status'])) ?>
                                    </span>
                                </div>
                                <div class="small text-muted-2 mt-1">
                                    <?= e($t['trade_no']) ?> · <?= money((float)$t['amount']) ?>
                                    · Mua: <?= e($t['buyer_name']) ?> · Bán: <?= e($t['seller_name']) ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
