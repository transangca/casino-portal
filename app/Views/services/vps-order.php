<?php
/**
 * Chi tiết đơn VPS.
 *
 * @var array $order
 */
use App\Services\XvpsService;

$st    = (string)$order['status'];
$badge = XvpsService::statusBadge($st);
$label = XvpsService::statusLabel($st);
$isPending = in_array($st, [XvpsService::STATUS_PENDING, XvpsService::STATUS_PROCESSING], true);
?>
<div class="container py-4">

    <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
        <div>
            <h1 class="page-title mb-1">
                <i class="fas fa-receipt me-2 text-gold"></i>
                Đơn VPS <?= $order['remote_order'] ? '#' . e((string)$order['remote_order']) : '#' . (int)$order['id'] ?>
            </h1>
            <p class="page-sub mb-0">
                Đặt lúc <?= e(date('d/m/Y H:i', strtotime((string)$order['created_at']))) ?>
            </p>
        </div>
        <div class="ms-auto d-flex gap-2 align-items-center">
            <span class="badge-status st-<?= e($badge) ?>"><i class="fas fa-circle me-1"></i><?= e($label) ?></span>
            <a href="<?= url('/services/vps') ?>" class="btn btn-outline-light btn-sm">
                <i class="fas fa-arrow-left me-1"></i>Về danh mục
            </a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="panel-card mb-4">
                <h6 class="mb-3"><i class="fas fa-box me-2"></i>Chi tiết đơn</h6>
                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="form-hint">Gói VPS</div>
                        <div class="fw-semibold"><?= e((string)($order['product_name'] ?: '—')) ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-hint">Mã sản phẩm</div>
                        <div class="font-numeric fw-semibold"><?= e((string)($order['product_id'] ?: '—')) ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-hint">Hệ điều hành</div>
                        <div class="fw-semibold"><?= e((string)($order['os_name'] ?: '—')) ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-hint">Chu kỳ</div>
                        <div class="fw-semibold">
                            <?= e(XvpsService::CYCLES[(string)$order['billing_cycle']] ?? (string)$order['billing_cycle']) ?>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-hint">Số lượng</div>
                        <div class="fw-semibold"><?= (int)$order['quantity'] ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-hint">Nâng cấp thêm</div>
                        <div class="fw-semibold font-numeric">
                            +<?= (int)$order['addon_cpu'] ?> CPU ·
                            +<?= (int)$order['addon_ram'] ?> GB RAM ·
                            +<?= (int)$order['addon_disk'] ?> GB Disk
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-hint">IP</div>
                        <div class="font-numeric fw-semibold"><?= e((string)($order['ip'] ?: '—')) ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-hint">Ngày hết hạn</div>
                        <div class="fw-semibold">
                            <?= (string)$order['next_due_date'] !== ''
                                ? e(date('d/m/Y', strtotime((string)$order['next_due_date'])))
                                : '—' ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ((string)$order['password'] !== ''): ?>
                <div class="panel-card mb-4">
                    <h6 class="mb-3"><i class="fas fa-key me-2"></i>Thông tin đăng nhập</h6>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <div class="form-hint">Username</div>
                            <div class="font-numeric fw-semibold"><?= e((string)$order['username']) ?></div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-hint">Mật khẩu</div>
                            <div class="d-flex align-items-center gap-2">
                                <input type="password" class="form-control form-control-sm font-numeric js-toggle-pw"
                                       value="<?= e((string)$order['password']) ?>" readonly>
                                <button type="button" class="btn btn-outline-light btn-sm js-eye">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ((string)$order['message'] !== ''): ?>
                <div class="panel-card">
                    <h6 class="mb-2"><i class="fas fa-comment-dots me-2"></i>Thông báo</h6>
                    <p class="small mb-0 text-muted-2"><?= e((string)$order['message']) ?></p>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-5">
            <div class="panel-card mb-4">
                <h6 class="mb-3"><i class="fas fa-receipt me-2"></i>Thanh toán</h6>
                <div class="d-flex justify-content-between small mb-1">
                    <span class="text-muted-2">Đơn giá</span>
                    <span><?= money((float)$order['price']) ?></span>
                </div>
                <div class="d-flex justify-content-between small mb-1">
                    <span class="text-muted-2">VAT</span>
                    <span>0 đ</span>
                </div>
                <hr class="my-2" style="border-color:rgba(255,255,255,.12)">
                <div class="d-flex justify-content-between">
                    <span class="fw-semibold">Tổng cộng</span>
                    <span class="fs-5 fw-bold text-gold"><?= money((float)$order['amount']) ?></span>
                </div>
                <p class="form-hint mt-2 mb-0">
                    Đã trừ trực tiếp từ ví chính. Hoàn tiền tự động nếu đơn không được chấp nhận.
                </p>
            </div>

            <?php if ($isPending): ?>
                <div class="panel-card mb-4">
                    <h6 class="mb-2"><i class="fas fa-spinner me-2"></i>Đang xử lý</h6>
                    <p class="small text-muted-2 mb-3">
                        VPS được tạo qua hệ thống hàng chờ. Quá trình khởi tạo có thể mất vài phút.
                    </p>
                    <div class="progress mb-2" style="height:6px">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-gold"
                             style="width:100%"></div>
                    </div>
                    <p class="form-hint mb-0">
                        Trang tự làm mới mỗi 15 giây.
                    </p>
                </div>

                <script>
                setTimeout(function () { window.location.reload(); }, 15000);
                </script>
            <?php endif; ?>

            <div class="panel-card">
                <h6 class="mb-2"><i class="fas fa-circle-info me-2"></i>Trạng thái VPS</h6>
                <div class="small mb-2">
                    <span class="text-muted-2">Trạng thái hiện tại:</span>
                    <span class="badge-status st-<?= e(XvpsService::vpsStatusBadge((string)$order['vps_status'])) ?> ms-1">
                        <?= e(XvpsService::vpsStatusLabel((string)$order['vps_status'])) ?>
                    </span>
                </div>
                <?php if ((string)$order['vps_id'] !== ''): ?>
                    <a href="<?= url('/services/vps/manage/' . rawurlencode((string)$order['vps_id'])) ?>"
                       class="btn btn-gold btn-sm w-100 mt-2">
                        <i class="fas fa-gauge-high me-1"></i>Mở bảng điều khiển VPS
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.js-eye').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var input = btn.parentElement.querySelector('.js-toggle-pw');
        if (!input) return;
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.innerHTML = show ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>';
    });
});
</script>
