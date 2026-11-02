<?php
/**
 * Trang bảo mật 2FA — QR + khoá thủ công + nhập mã 6 số, căn chỉnh chuẩn.
 *
 * @var array  $status
 * @var string $secret
 * @var string $otpauthUri
 * @var string $qrSvg
 */
$enabled = (bool)$status['enabled'];
$recovery = $sessionRecovery ?? [];
?>
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-9">

            <h1 class="page-title"><i class="fas fa-shield-halved text-gold me-2"></i>Bảo mật 2FA</h1>
            <p class="page-sub">Thêm một lớp bảo vệ bằng mã 6 số sinh từ ứng dụng xác thực (Google Authenticator).</p>

            <!-- ===== Trạng thái ===== -->
            <div class="panel-card mb-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="locked-icon" style="width:56px;height:56px;font-size:1.4rem;margin:0">
                        <i class="fas <?= $enabled ? 'fa-shield-halved' : 'fa-lock-open' ?>"></i>
                    </div>
                    <div>
                        <h3 class="mb-1" style="font-size:1.05rem"><?= $enabled ? '2FA đang được bật' : '2FA chưa được bật' ?></h3>
                        <p class="text-muted-2 small mb-0">
                            <?= $enabled
                                ? 'Tài khoản của bạn được bảo vệ bằng mã xác thực 2 lớp.'
                                : 'Bật 2FA để bảo vệ tài khoản khỏi việc truy cập trái phép.' ?>
                        </p>
                    </div>
                </div>
            </div>

            <?php if (!empty($recovery)): ?>
                <div class="panel-card mb-4">
                    <h3 class="mb-2" style="font-size:1rem"><i class="fas fa-key text-gold me-2"></i>Mã khôi phục (chỉ hiện 1 lần)</h3>
                    <p class="text-muted-2 small">Lưu lại các mã này ở nơi an toàn. Mỗi mã chỉ dùng được 1 lần khi không có ứng dụng xác thực.</p>
                    <div class="recovery-codes">
                        <?php foreach ($recovery as $code): ?>
                            <span><?= e($code) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <button class="btn btn-sm btn-outline-gold mt-3" type="button"
                            onclick="navigator.clipboard && navigator.clipboard.writeText(<?= json_encode(implode("\n", $recovery)) ?>)">
                        <i class="fas fa-copy me-1"></i>Sao chép mã
                    </button>
                </div>
            <?php endif; ?>

            <?php if (!$enabled): ?>
                <!-- ===== Cài đặt ===== -->
                <div class="panel-card mb-4">
                    <h3 class="mb-3" style="font-size:1.05rem"><i class="fas fa-qrcode text-gold me-2"></i>Quét mã QR để kích hoạt</h3>

                    <div class="tfa-wrap">
                        <!-- Cột trái: QR căn giữa -->
                        <div class="tfa-qr-panel">
                            <div class="tfa-qr-box">
                                <?= $qrSvg ?>
                            </div>
                            <p class="form-hint mb-0">Quét bằng Google Authenticator</p>
                        </div>

                        <!-- Cột phải: hướng dẫn + khoá thủ công -->
                        <div>
                            <ol class="qa-steps">
                                <li><?= t('security.step1') ?></li>
                                <li><?= t('security.step2') ?></li>
                                <li><?= t('security.step3') ?></li>
                                <li><?= t('security.step4') ?></li>
                            </ol>

                            <div class="mt-3">
                                <label class="form-label"><?= t('security.manual_key') ?></label>
                                <div class="tfa-secret" id="manualKey"><?= e($secret) ?></div>
                                <button class="btn btn-sm btn-outline-light mt-2" type="button"
                                        onclick="navigator.clipboard && navigator.clipboard.writeText('<?= e($secret) ?>');toastOk('Đã sao chép khóa')">
                                    <i class="fas fa-copy me-1"></i>Sao chép khóa
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ===== Xác nhận ===== -->
                <div class="panel-card">
                    <h3 class="mb-3" style="font-size:1.05rem"><i class="fas fa-check-circle text-gold me-2"></i>Xác nhận mã</h3>
                    <form method="POST" action="<?= url('/security/2fa/enable') ?>" id="tfaEnableForm">
                        <?= csrf_field() ?>
                        <input type="hidden" name="secret" value="<?= e($secret) ?>">
                        <div class="text-center">
                            <div class="otp-inputs mb-3">
                                <?php for ($i = 0; $i < 6; $i++): ?>
                                    <input type="text" class="form-control otp-input tfa-digit" inputmode="numeric" pattern="[0-9]"
                                           maxlength="1" autocomplete="<?= $i === 0 ? 'one-time-code' : 'off' ?>"
                                           aria-label="Chữ số <?= $i + 1 ?>">
                                <?php endfor; ?>
                            </div>
                            <input type="hidden" name="code" id="tfaCode">
                            <button class="btn btn-gold px-4"><i class="fas fa-shield-halved me-2"></i><?= t('security.enable') ?></button>
                            <p class="form-hint mt-2 mb-0">Nhập 6 chữ số hiển thị trong ứng dụng xác thực.</p>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <!-- ===== Đã bật ===== -->
                <div class="panel-card mb-4">
                    <h3 class="mb-3" style="font-size:1.05rem"><i class="fas fa-list-check text-gold me-2"></i>Mã khôi phục</h3>
                    <?php if (!empty($status['recovery'])): ?>
                        <div class="recovery-codes">
                            <?php foreach ($status['recovery'] as $code): ?>
                                <span><?= e($code) ?></span>
                            <?php endforeach; ?>
                        </div>
                        <p class="form-hint mt-2"><?= t('security.recovery_hint') ?></p>
                    <?php else: ?>
                        <p class="text-muted-2 small mb-0">Bạn chưa có mã khôi phục nào.</p>
                    <?php endif; ?>

                    <form method="POST" action="<?= url('/security/2fa/recovery') ?>" class="mt-3">
                        <?= csrf_field() ?>
                        <button class="btn btn-sm btn-outline-gold">
                            <i class="fas fa-rotate me-1"></i>Tạo lại mã khôi phục
                        </button>
                    </form>
                </div>

                <div class="panel-card">
                    <h3 class="mb-3" style="font-size:1.05rem"><i class="fas fa-power-off text-danger me-2"></i><?= t('security.disable') ?></h3>
                    <form method="POST" action="<?= url('/security/2fa/disable') ?>">
                        <?= csrf_field() ?>
                        <div class="row g-3 align-items-end">
                            <div class="col-md-5">
                                <label class="form-label" for="pw">Mật khẩu hiện tại</label>
                                <input type="password" class="form-control" id="pw" name="password" autocomplete="current-password">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label" for="c2">Hoặc mã 2FA</label>
                                <input type="text" class="form-control font-numeric" id="c2" name="code" inputmode="numeric" maxlength="6" placeholder="000000">
                            </div>
                            <div class="col-md-2">
                                <button class="btn btn-outline-danger w-100">Tắt</button>
                            </div>
                        </div>
                        <p class="form-hint mt-2 mb-0">Nhập mật khẩu hoặc mã 2FA hiện tại để xác nhận tắt bảo mật.</p>
                    </form>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script>
/* Ô nhập mã 6 số: tự chuyển ô + gộp vào hidden field */
(function () {
    var form = document.getElementById('tfaEnableForm');
    if (!form) return;
    var boxes = Array.prototype.slice.call(form.querySelectorAll('.tfa-digit'));
    var hidden = form.querySelector('#tfaCode');

    function sync() {
        var code = boxes.map(function (b) { return (b.value || '').replace(/\D/g, ''); }).join('');
        if (hidden) hidden.value = code;
        return code;
    }

    boxes.forEach(function (box, i) {
        box.addEventListener('input', function () {
            box.value = box.value.replace(/\D/g, '').slice(-1);
            if (box.value && boxes[i + 1]) boxes[i + 1].focus();
            sync();
        });
        box.addEventListener('keydown', function (e) {
            if (e.key === 'Backspace' && !box.value && boxes[i - 1]) {
                boxes[i - 1].focus();
                boxes[i - 1].value = '';
                sync();
            }
        });
        box.addEventListener('paste', function (e) {
            e.preventDefault();
            var txt = (e.clipboardData || window.clipboardData).getData('text') || '';
            txt = txt.replace(/\D/g, '').slice(0, 6);
            boxes.forEach(function (b, k) { b.value = txt[k] || ''; });
            var last = Math.min(txt.length, 6) - 1;
            if (last >= 0 && boxes[last]) boxes[last].focus();
            sync();
        });
    });

    form.addEventListener('submit', function (e) {
        if (sync().length !== 6) {
            e.preventDefault();
            if (window.toastErr) toastErr('Vui lòng nhập đủ 6 chữ số mã xác thực.');
        }
    });
})();
</script>
