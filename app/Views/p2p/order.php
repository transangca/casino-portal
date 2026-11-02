<?php /** @var array $order,$categories */ ?>
<div class="container py-4" style="max-width:900px">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= url('/p2p') ?>">Sàn P2P</a></li>
            <li class="breadcrumb-item active">Chi tiết tin</li>
        </ol>
    </nav>

    <div class="panel-card">
        <div class="d-flex align-items-center gap-2 mb-2">
            <span class="p2p-cat"><?= e(\App\Services\P2PService::categoryLabel((string)$order['category'])) ?></span>
            <span class="text-muted-2 small">Mã tin: <?= e($order['order_no']) ?></span>
        </div>

        <h1 class="page-title" style="font-size:1.5rem"><?= e($order['title']) ?></h1>

        <div class="p2p-seller my-3">
            <i class="fas fa-user"></i> <?= e($order['seller_name']) ?>
            <?php if (!empty($order['seller_verified'])): ?>
                <span class="verified-tick" title="Đã xác minh"><i class="fas fa-check"></i></span>
            <?php endif; ?>
        </div>

        <div class="p2p-price mb-4" style="font-size:1.8rem">
            <strong><?= money((float)$order['price']) ?></strong>
            <span>Còn <?= (int)$order['quantity'] - (int)$order['sold_count'] ?> / <?= (int)$order['quantity'] ?></span>
        </div>

        <?php if (!empty($order['description'])): ?>
            <div class="mb-3">
                <h6 class="text-gold">Mô tả</h6>
                <p class="text-muted-2" style="white-space:pre-line"><?= e($order['description']) ?></p>
            </div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
            <div class="col-sm-6">
                <div class="info-box">
                    <span class="info-label">Phương thức thanh toán</span>
                    <span class="info-value"><?= e($order['payment_method'] ?: 'Chuyển khoản ngân hàng') ?></span>
                </div>
            </div>
            <div class="col-sm-6">
                <div class="info-box">
                    <span class="info-label">Điều khoản</span>
                    <span class="info-value"><?= e($order['terms'] ?: 'Thoả thuận với người bán') ?></span>
                </div>
            </div>
        </div>

        <hr class="my-4">

        <h6 class="text-gold mb-3"><i class="fas fa-shopping-cart me-1"></i>Đặt mua</h6>

        <?php if ($order['status'] !== 'open'): ?>
            <div class="alert alert-warning">Tin này đã có người mua khác.</div>
        <?php else: ?>
            <form method="POST" action="<?= url('/p2p/buy') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">

                <div class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label">Số tiền mua (đ)</label>
                        <input type="number" name="amount" class="form-control" required
                               min="<?= (float)($order['min_amount'] ?: 1) ?>"
                               max="<?= (float)($order['max_amount'] ?: $order['price']) ?>"
                               step="1000"
                               value="<?= (float)($order['max_amount'] ?: $order['price']) ?>">
                    </div>
                    <div class="col-md-7">
                        <button type="submit" class="btn btn-gold w-100">
                            <i class="fas fa-lock me-1"></i>Đặt mua an toàn
                        </button>
                    </div>
                </div>

                <div class="alert alert-info mt-3 mb-0 small">
                    <i class="fas fa-info-circle me-1"></i>
                    Số tiền sẽ được <strong>trừ từ ví của bạn</strong> và giữ an toàn trong ví dự phòng của hệ thống.
                    Người bán chỉ nhận được tiền sau khi <strong>cả hai bên xác nhận</strong> hoàn tất giao dịch.
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>
