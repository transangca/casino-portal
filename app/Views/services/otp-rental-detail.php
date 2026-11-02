<?php
/**
 * Chi tiết lượt thuê số OTP — hiển thị & tự động poll mã.
 *
 * @var array $rental
 */
$status  = (int)$rental['status'];
$waitMin = (int)setting('viotp_wait_minutes', '7');
?>
<div class="container py-4" style="max-width:760px">

    <a href="<?= url('/services/otp-rental') ?>" class="btn btn-outline-light btn-sm mb-3">
        <i class="fas fa-arrow-left me-1"></i>Quay lại danh sách
    </a>

    <div class="panel-card">
        <div class="d-flex flex-wrap align-items-center gap-3 mb-4 pb-3 border-bottom">
            <span class="aic-icon aic-icon-lg"><i class="fas fa-mobile-screen"></i></span>
            <div>
                <h1 class="page-title mb-1"><?= e((string)($rental['service_name'] ?: 'Thuê số OTP')) ?></h1>
                <div class="text-muted-2 small">
                    Số thuê: <b class="font-numeric" style="font-size:1.05rem"><?= e((string)($rental['phone'] ?: '—')) ?></b>
                    · Lượt #<?= (int)$rental['rental_id'] ?>
                </div>
            </div>
            <div class="ms-auto text-end">
                <div class="small text-muted-2">Đã thanh toán</div>
                <div class="fw-bold text-gold" style="font-size:1.3rem"><?= money((float)$rental['price']) ?></div>
            </div>
        </div>

        <!-- ===== Hộp mã OTP ===== -->
        <div class="otp-code-box" id="otpBox">
            <?php if ($status === 1): ?>
                <div class="otp-code-label">Mã xác thực</div>
                <div class="otp-code-value" id="otpValue"><?= e((string)$rental['otp_code']) ?></div>
                <button type="button" class="btn btn-outline-gold btn-sm mt-3 copy-btn" data-copy-target="otpValue">
                    <i class="fas fa-copy me-1"></i>Sao chép mã
                </button>
            <?php elseif ($status === 2): ?>
                <div class="otp-code-label">Đã hết thời gian chờ</div>
                <div class="text-muted-2 mt-2">
                    <i class="fas fa-clock me-1"></i>
                    Không nhận được mã trong <?= $waitMin ?> phút. Hệ thống đã tự động hoàn tiền.
                </div>
            <?php else: ?>
                <div class="otp-code-label">Đang chờ mã OTP...</div>
                <div class="otp-waiting" id="otpWaiting">
                    <span class="spinner-border spinner-border-sm text-gold me-2"></span>
                    <span id="otpTimer">Đang kiểm tra...</span>
                </div>
                <div class="progress mt-3" style="height:4px">
                    <div class="progress-bar bg-gold" id="otpProgress" style="width:0%"></div>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($rental['content'])): ?>
            <div class="alert alert-info mt-4 mb-0 small">
                <i class="fas fa-envelope-open-text me-1"></i>
                <b>Nội dung tin nhắn:</b> <?= e((string)$rental['content']) ?>
            </div>
        <?php endif; ?>

        <div class="d-flex flex-wrap gap-2 mt-4">
            <?php if ($status === 1): ?>
                <a href="<?= url('/services/otp-rental/rental/' . (int)$rental['id']) ?>" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-rotate me-1"></i>Làm mới
                </a>
            <?php endif; ?>

            <?php if ($status === 1 || $status === 2): ?>
                <form method="POST" action="<?= url('/services/otp-rental/rerent') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="rental_id" value="<?= (int)$rental['id'] ?>">
                    <button class="btn btn-gold btn-sm">
                        <i class="fas fa-redo me-1"></i>Thuê lại số này
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <div class="alert alert-secondary mt-4 mb-0 small">
            <i class="fas fa-shield-halved me-1"></i>
            Số chỉ dùng được trong khoảng 4–7 phút. Vui lòng nhập mã kịp thời.
            Nếu không có mã, hệ thống sẽ tự hoàn tiền — bạn không mất tiền.
        </div>
    </div>
</div>

<script>
(function () {
    var box = document.getElementById('otpBox');
    if (!box || !document.getElementById('otpWaiting')) return;

    var rentalId = <?= (int)$rental['id'] ?>;
    var waitMin  = <?= $waitMin ?>;
    var started  = Date.now();
    var totalMs  = waitMin * 60 * 1000;
    var stopped  = false;

    function tick() {
        var el = document.getElementById('otpTimer');
        var bar = document.getElementById('otpProgress');
        var left = totalMs - (Date.now() - started);
        if (left <= 0) {
            el.textContent = 'Đã hết thời gian chờ, đang tải lại...';
            bar.style.width = '100%';
            return;
        }
        var m = Math.floor(left / 60000);
        var s = Math.floor((left % 60000) / 1000);
        el.textContent = 'Còn ' + m + ' phút ' + s + ' giây — hệ thống tự làm mới mỗi 5 giây';
        bar.style.width = Math.min(100, ((totalMs - left) / totalMs) * 100) + '%';
    }
    tick();
    setInterval(tick, 1000);

    function poll() {
        if (stopped) return;
        fetch('<?= url('/services/otp-rental/poll') ?>?id=' + rentalId, {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (j.success && j.data && j.data.finished) {
                    stopped = true;
                    location.reload();
                } else if (j.success && j.data && j.data.code) {
                    stopped = true;
                    location.reload();
                }
            })
            .catch(function () {});
    }
    setInterval(poll, 5000);
    poll();
})();
</script>
