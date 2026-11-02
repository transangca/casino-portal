<?php /** @var array $orders */ ?>
<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= url('/services/proxy') ?>">Kho proxy</a></li>
            <li class="breadcrumb-item active">Proxy đã mua</li>
        </ol>
    </nav>

    <h1 class="page-title"><i class="fas fa-receipt me-2 text-gold"></i>Proxy đã mua</h1>
    <p class="page-sub">Danh sách gói proxy bạn đã thanh toán. Bạn có thể sao chép thông tin kết nối bất cứ lúc nào.</p>

    <?php if (!$orders): ?>
        <div class="empty-state">
            <i class="fas fa-box-open fs-1 d-block mb-3"></i>
            <p class="mb-0">Bạn chưa mua gói proxy nào.</p>
            <a href="<?= url('/services/proxy') ?>" class="btn btn-gold btn-sm mt-3">
                <i class="fas fa-store me-1"></i>Xem kho proxy
            </a>
        </div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($orders as $o): ?>
                <div class="col-lg-6">
                    <div class="panel-card h-100">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <strong><?= e((string)$o['title']) ?></strong>
                            <span class="badge-status st-active"><?= e(strtoupper((string)$o['status'])) ?></span>
                        </div>

                        <div class="row g-2 small text-muted-2 mb-3">
                            <div class="col-6"><i class="fas fa-hashtag me-1"></i>Mã đơn #<?= (int)$o['id'] ?></div>
                            <div class="col-6"><i class="fas fa-calendar me-1"></i><?= date('d/m/Y H:i', strtotime((string)$o['created_at'])) ?></div>
                            <div class="col-6"><i class="fas fa-box me-1"></i><?= (int)$o['quantity'] ?> gói</div>
                            <div class="col-6"><i class="fas fa-wallet me-1"></i><?= money((float)$o['total_amount']) ?></div>
                        </div>

                        <?php if (!empty($o['endpoint'])): ?>
                            <div class="input-group mb-2">
                                <input type="text" class="form-control font-numeric" readonly
                                       value="<?= e((string)$o['endpoint']) ?>">
                                <button class="btn btn-outline-secondary" data-copy="<?= e((string)$o['endpoint']) ?>">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info mb-0 small">
                                <i class="fas fa-info-circle me-1"></i>Thông tin kết nối sẽ được hiển thị khi người bán giao hàng.
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($o['note'])): ?>
                            <div class="form-hint mt-2"><?= e((string)$o['note']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
