<?php
/**
 * Chi tiết đơn VPS StoreVPS.
 *
 * @var array $order
 */
use App\Services\VirtualizorService;

$status  = (string)$order['status'];
$vpsStat = (string)($order['vps_status'] ?? '');
$addons  = json_decode((string)($order['addons_json'] ?? '{}'), true);
$addons  = is_array($addons) ? $addons : [];
?>
<div class="container py-4">

    <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
        <div>
            <h1 class="page-title mb-1">
                <i class="fas fa-layer-group me-2 text-gold"></i>
                Đơn VPS <?= $order['order_code'] ? '#' . e((string)$order['order_code']) : '#' . (int)$order['id'] ?>
            </h1>
            <p class="page-sub mb-0">
                <?= e((string)($order['product_name'] ?: 'VPS StoreVPS')) ?>
                · <?= e(date('d/m/Y H:i', strtotime((string)$order['created_at']))) ?>
            </p>
        </div>
        <div class="ms-auto d-flex gap-2 align-items-center">
            <span class="badge-status st-<?= e(VirtualizorService::statusBadge($status)) ?>">
                <?= e(VirtualizorService::statusLabel($status)) ?>
            </span>
            <a href="<?= url('/services/vps-storevps/manage') ?>" class="btn btn-outline-light btn-sm">
                <i class="fas fa-arrow-left me-1"></i>VPS của tôi
            </a>
        </div>
    </div>

    <?php if ((string)$order['message'] !== ''): ?>
        <div class="alert alert-<?= $status === VirtualizorService::STATUS_FAILED ? 'danger' : 'info' ?> small">
            <i class="fas fa-circle-info me-1"></i><?= e((string)$order['message']) ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="panel-card mb-4">
                <h6 class="mb-3"><i class="fas fa-list-check me-2"></i>Chi tiết đơn</h6>
                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="form-hint">Sản phẩm</div>
                        <div class="fw-semibold"><?= e((string)($order['product_name'] ?: '—')) ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-hint">Chu kỳ</div>
                        <div class="fw-semibold">
                            <?= e(VirtualizorService::CYCLES[(string)$order['billing_cycle']] ?? (string)$order['billing_cycle']) ?>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-hint">Hệ điều hành</div>
                        <div class="fw-semibold"><?= e((string)($order['os_name'] ?: '—')) ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-hint">Hostname</div>
                        <div class="fw-semibold font-numeric"><?= e((string)($order['hostname'] ?: '—')) ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-hint">Datacenter</div>
                        <div class="fw-semibold"><?= e((string)($order['location_name'] ?: 'Mặc định')) ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-hint">Số lượng</div>
                        <div class="fw-semibold"><?= (int)$order['quantity'] ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-hint">Mã đơn hệ thống</div>
                        <div class="fw-semibold font-numeric"><?= e((string)($order['remote_order'] ?: '—')) ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-hint">Trạng thái VPS</div>
                        <div class="fw-semibold">
                            <span class="badge-status st-<?= e(VirtualizorService::vpsStatusBadge($vpsStat)) ?>">
                                <?= e(VirtualizorService::vpsStatusLabel($vpsStat)) ?>
                            </span>
                        </div>
                    </div>
                </div>

                <?php if ($addons): ?>
                    <hr class="my-3" style="border-color:rgba(255,255,255,.12)">
                    <div class="form-hint mb-2">Addon đã chọn</div>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($addons as $type => $n): ?>
                            <span class="badge-status st-active">
                                <?= e(VirtualizorService::ADDON_TYPES[(string)$type] ?? (string)$type) ?> × <?= (int)$n ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ((string)($order['instance_id'] ?? '') !== ''): ?>
                <div class="panel-card">
                    <h6 class="mb-3"><i class="fas fa-server me-2"></i>Thông tin kết nối</h6>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <div class="form-hint">IP</div>
                            <div class="font-numeric fw-semibold"><?= e((string)($order['ip'] ?: '—')) ?></div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-hint">Username</div>
                            <div class="font-numeric fw-semibold"><?= e((string)($order['username'] ?: 'root')) ?></div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-hint">Mật khẩu</div>
                            <div class="d-flex align-items-center gap-2">
                                <input type="password" class="form-control form-control-sm font-numeric js-toggle-pw"
                                       value="<?= e((string)($order['password'] ?: '')) ?>" readonly
                                       placeholder="Chưa có thông tin">
                                <button type="button" class="btn btn-outline-light btn-sm js-eye" title="Hiện / ẩn">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <a href="<?= url('/services/vps-storevps/manage/' . rawurlencode((string)$order['instance_id'])) ?>"
                       class="btn btn-gold btn-sm mt-3">
                        <i class="fas fa-gauge-high me-1"></i>Mở bảng điều khiển VPS
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
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
                    <span class="fw-semibold">Tổng</span>
                    <span class="fs-5 fw-bold text-gold"><?= money((float)$order['amount']) ?></span>
                </div>
                <p class="form-hint mt-2 mb-0">
                    Đã trừ trực tiếp từ ví chính. Nếu đơn không được chấp nhận, tiền được hoàn tự động.
                </p>
            </div>

            <div class="panel-card">
                <h6 class="mb-3"><i class="fas fa-timeline me-2"></i>Tiến trình</h6>
                <ol class="small text-muted-2 mb-0" style="padding-left:1.1rem">
                    <li class="<?= $status !== 'pending' ? 'text-success' : '' ?>">Tiếp nhận yêu cầu</li>
                    <li class="<?= in_array($status, ['processing', 'completed'], true) ? 'text-success' : '' ?>">
                        Gửi đơn của bạn
                    </li>
                    <li class="<?= $status === 'completed' ? 'text-success' : '' ?>">
                        Khởi tạo VPS
                    </li>
                    <li class="<?= $status === 'failed' ? 'text-danger' : '' ?>">
                        Bàn giao thông tin kết nối
                    </li>
                </ol>
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
