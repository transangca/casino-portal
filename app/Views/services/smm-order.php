<?php
/**
 * Chi tiết đơn SMM — trạng thái & tiến độ.
 *
 * @var array $order
 */
use App\Services\SmmService;

$st      = (string)$order['status'];
$isAlive = in_array($st, [SmmService::STATUS_PENDING, SmmService::STATUS_PROCESSING], true);
?>
<div class="container py-4" style="max-width:900px">

    <a href="<?= url('/services/smm') ?>" class="btn btn-outline-light btn-sm mb-3">
        <i class="fas fa-arrow-left me-1"></i>Quay lại danh sách
    </a>

    <div class="panel-card">
        <div class="d-flex flex-wrap align-items-center gap-3 mb-4 pb-3 border-bottom">
            <span class="aic-icon aic-icon-lg"><i class="fas fa-thumbs-up"></i></span>
            <div class="min-w-0">
                <h1 class="page-title mb-1"><?= e((string)($order['service_name'] ?: 'Đơn SMM')) ?></h1>
                <div class="text-muted-2 small">
                    Mã đơn hệ thống: <b class="font-numeric"><?= $order['remote_order'] ? '#' . (int)$order['remote_order'] : 'Chưa có' ?></b>
                    <?php if ((string)$order['platform'] !== ''): ?>
                        · <?= e((string)$order['platform']) ?>
                    <?php endif; ?>
                    <?php if ((string)$order['category_slug'] !== ''): ?>
                        · <?= e((string)$order['category_slug']) ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="ms-auto text-end">
                <div class="small text-muted-2">Tổng thanh toán</div>
                <div class="fw-bold text-gold" style="font-size:1.4rem"><?= money((float)$order['amount']) ?></div>
            </div>
        </div>

        <!-- Thanh trạng thái -->
        <div class="d-flex flex-wrap gap-3 mb-4 small align-items-center">
            <span class="badge-status st-<?= e(SmmService::statusBadge($st)) ?>" id="smmStatusBadge">
                <?= e(SmmService::statusLabel($st)) ?>
            </span>
            <span class="text-muted-2" id="smmProgressText">
                <?php if ((string)$order['start_count'] !== '' || (string)$order['remains'] !== ''): ?>
                    Đã chạy: <?= e((string)$order['start_count']) ?>
                    · còn <?= e((string)$order['remains']) ?>
                <?php else: ?>
                    Đang chờ cập nhật...
                <?php endif; ?>
            </span>
            <span class="text-muted-2 ms-auto">Đặt lúc: <?= e((string)$order['created_at']) ?></span>
        </div>

        <?php if ($st === SmmService::STATUS_FAILED): ?>
            <div class="alert alert-danger">
                <i class="fas fa-circle-exclamation me-1"></i>
                <?= e((string)($order['message'] ?: 'Đơn hàng thất bại. Số tiền đã được hoàn lại.')) ?>
            </div>
        <?php elseif ($st === SmmService::STATUS_COMPLETED): ?>
            <div class="alert alert-success">
                <i class="fas fa-circle-check me-1"></i>
                Đơn hàng đã hoàn thành. Vui lòng kiểm tra tài khoản của bạn.
            </div>
        <?php elseif ($isAlive): ?>
            <div class="alert alert-info small">
                <i class="fas fa-spinner fa-spin me-1"></i>
                Hệ thống đang xử lý đơn hàng. Trang tự động làm mới mỗi 10 giây — bạn có thể để nguyên trang này.
            </div>
        <?php endif; ?>

        <h6 class="mb-3"><i class="fas fa-circle-info me-2"></i>Thông tin đơn</h6>
        <div class="row g-3 small">
            <div class="col-md-6">
                <div class="text-muted-2">Dịch vụ</div>
                <div class="fw-semibold"><?= e((string)($order['service_name'] ?: '—')) ?></div>
            </div>
            <div class="col-md-6">
                <div class="text-muted-2">Số lượng</div>
                <div class="fw-semibold font-numeric"><?= (int)$order['quantity'] ?></div>
            </div>
            <div class="col-md-6">
                <div class="text-muted-2">Giá / 1.000</div>
                <div class="fw-semibold font-numeric"><?= money((float)$order['price']) ?></div>
            </div>
            <div class="col-md-6">
                <div class="text-muted-2">Bạn đã trả</div>
                <div class="fw-semibold font-numeric text-gold"><?= money((float)$order['amount']) ?></div>
            </div>
            <div class="col-12">
                <div class="text-muted-2">Link xử lý</div>
                <div class="fw-semibold font-numeric" style="word-break:break-all"><?= e((string)($order['link'] ?: '—')) ?></div>
            </div>
            <?php if ((string)$order['reaction'] !== ''): ?>
                <div class="col-md-6">
                    <div class="text-muted-2">Reaction</div>
                    <div class="fw-semibold"><?= e((string)$order['reaction']) ?></div>
                </div>
            <?php endif; ?>
            <?php if ((string)$order['comments'] !== ''): ?>
                <div class="col-12">
                    <div class="text-muted-2">Danh sách comment</div>
                    <div class="fw-semibold" style="white-space:pre-wrap"><?= e((string)$order['comments']) ?></div>
                </div>
            <?php endif; ?>
        </div>

        <?php if ((string)$order['message'] !== '' && $st !== SmmService::STATUS_FAILED): ?>
            <div class="form-hint mt-3">
                <i class="fas fa-message me-1"></i><?= e((string)$order['message']) ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($isAlive): ?>
<script>
(function () {
    var badge  = document.getElementById('smmStatusBadge');
    var prog   = document.getElementById('smmProgressText');
    var url    = '<?= url('/services/smm/status') ?>';
    var orderId = <?= (int)$order['id'] ?>;

    var timer = setInterval(function () {
        fetch(url + '?id=' + orderId, {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (!j.success) return;
                var d = j.data;
                badge.className = 'badge-status st-' + d.badge;
                badge.textContent = d.statusText;
                var t = '';
                if (d.start_count) t += 'Đã chạy: ' + d.start_count;
                if (d.remains) t += (t ? ' · ' : '') + 'còn ' + d.remains;
                prog.textContent = t || 'Đang chờ cập nhật...';
                if (d.finished) {
                    clearInterval(timer);
                    setTimeout(function () { location.reload(); }, 1200);
                }
            })
            .catch(function () { /* im lặng, thử lần sau */ });
    }, 10000);
})();
</script>
<?php endif; ?>
