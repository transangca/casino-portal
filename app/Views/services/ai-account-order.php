<?php
/**
 * Chi tiết đơn tài khoản AI — hiển thị & nhận tài khoản đã giao.
 *
 * @var array $order
 */
$accounts = $order['accounts'] ?? [];
?>
<div class="container py-4" style="max-width:900px">

    <a href="<?= url('/services/ai-accounts') ?>" class="btn btn-outline-light btn-sm mb-3">
        <i class="fas fa-arrow-left me-1"></i>Quay lại danh sách
    </a>

    <div class="panel-card">
        <div class="d-flex flex-wrap align-items-center gap-3 mb-4 pb-3 border-bottom">
            <span class="aic-icon aic-icon-lg"><i class="<?= e((string)($order['icon'] ?? 'fa-solid fa-box')) ?>"></i></span>
            <div>
                <h1 class="page-title mb-1"><?= e((string)($order['product_name'] ?: $order['product_id'])) ?></h1>
                <div class="text-muted-2 small">
                    Mã đơn: <b class="font-numeric"><?= e((string)($order['order_code'] ?: '#' . $order['id'])) ?></b>
                    · <?= e((string)($order['type_label'] ?: $order['type'])) ?>
                    · <?= (int)$order['quantity'] ?> tài khoản
                </div>
            </div>
            <div class="ms-auto text-end">
                <div class="small text-muted-2">Tổng thanh toán</div>
                <div class="fw-bold text-gold" style="font-size:1.4rem"><?= money((float)$order['amount']) ?></div>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-3 mb-4 small">
            <span class="badge-status st-<?= $order['status'] === 'success' ? 'active' : ($order['status'] === 'failed' ? 'closed' : 'pending') ?>">
                <?= $order['status'] === 'success' ? 'Thành công' : ($order['status'] === 'failed' ? 'Thất bại' : 'Đang xử lý') ?>
            </span>
            <?php if ((int)$order['replay'] === 1): ?>
                <span class="badge-status st-pending"><i class="fas fa-clone me-1"></i>Đơn trùng</span>
            <?php endif; ?>
            <span class="text-muted-2">Thời gian: <?= e((string)$order['created_at']) ?></span>
        </div>

        <?php if ($order['status'] === 'failed'): ?>
            <div class="alert alert-danger">
                <i class="fas fa-circle-exclamation me-1"></i>
                <?= e((string)($order['message'] ?: 'Đơn hàng thất bại. Số tiền đã được hoàn lại.')) ?>
            </div>
        <?php endif; ?>

        <h6 class="mb-3"><i class="fas fa-key me-2"></i>Tài khoản đã giao (<?= count($accounts) ?>)</h6>

        <?php if (!$accounts): ?>
            <div class="alert alert-info mb-0">
                <i class="fas fa-circle-info me-1"></i>
                Chưa có tài khoản nào trong đơn này.
            </div>
        <?php else: ?>
            <div class="aic-delivery-list">
                <?php foreach ($accounts as $acc): ?>
                    <?php $pwd = \App\Services\AiAccountService::revealPassword($acc['password_enc']); ?>
                    <div class="aic-delivery">
                        <div class="d-flex flex-wrap align-items-center gap-3">
                            <div class="flex-fill min-w-0">
                                <div class="small text-muted-2">Tài khoản</div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="fw-semibold font-numeric" id="usr_<?= (int)$acc['id'] ?>"><?= e((string)($acc['username'] ?: '—')) ?></span>
                                    <?php if (!empty($acc['username'])): ?>
                                        <button type="button" class="btn btn-sm btn-outline-light copy-btn"
                                                data-copy-target="usr_<?= (int)$acc['id'] ?>">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($acc['verify_email'])): ?>
                                    <div class="form-hint">
                                        <i class="fas fa-envelope me-1"></i><?= e((string)$acc['verify_email']) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($acc['product_item_id'])): ?>
                                    <div class="form-hint">Item ID: <?= e((string)$acc['product_item_id']) ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="flex-fill min-w-0">
                                <div class="small text-muted-2">Mật khẩu</div>
                                <?php if ($pwd): ?>
                                    <div class="d-flex align-items-center gap-2">
                                        <code class="text-gold" id="pwd_<?= (int)$acc['id'] ?>"><?= e($pwd) ?></code>
                                        <button type="button" class="btn btn-sm btn-outline-light copy-btn"
                                                data-copy-target="pwd_<?= (int)$acc['id'] ?>">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted-2 small">—</span>
                                <?php endif; ?>
                            </div>

                            <div>
                                <?php if ((int)$acc['is_redeemed'] === 1): ?>
                                    <span class="badge-status st-active"><i class="fas fa-check me-1"></i>Đã nhận</span>
                                <?php else: ?>
                                    <form method="POST" action="<?= url('/services/ai-accounts/redeem') ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="delivery_id" value="<?= (int)$acc['id'] ?>">
                                        <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                                        <button class="btn btn-sm btn-outline-gold">Đã nhận</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="alert alert-warning mt-3 mb-0 small">
                <i class="fas fa-triangle-exclamation me-1"></i>
                Hãy sao lưu thông tin tài khoản ngay. Vui lòng không chia sẻ cho người khác.
            </div>
        <?php endif; ?>
    </div>
</div>
