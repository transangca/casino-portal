<?php
/**
 * Bảng điều khiển VPS — khách hàng.
 *
 * @var array  $instance  thông tin VPS
 * @var array  $osList    danh sách OS (cho rebuild)
 * @var array  $cycles    chu kỳ (cho gia hạn)
 * @var bool   $refreshed đã làm mới từ API hay chưa
 */
use App\Services\XvpsService;

$vpsId   = (string)$instance['vps_id'];
$status  = (string)$instance['vps_status'];
$isOn    = in_array($status, ['on', 'running'], true);
$isOff   = in_array($status, ['off', 'stopped'], true);
$canAct  = in_array($status, ['on', 'off', 'running', 'stopped'], true);
?>
<div class="container py-4">

    <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
        <div>
            <h1 class="page-title mb-1">
                <i class="fas fa-server me-2 text-gold"></i>
                VPS <?= e($vpsId) ?>
            </h1>
            <p class="page-sub mb-0">
                <?= e((string)($instance['product_name'] ?: 'Máy chủ ảo')) ?>
                <?php if ((string)$instance['text_config'] !== ''): ?>
                    · <?= e((string)$instance['text_config']) ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="ms-auto d-flex gap-2">
            <span class="badge-status st-<?= e(XvpsService::vpsStatusBadge($status)) ?> align-self-center">
                <i class="fas fa-circle me-1"></i><?= e(XvpsService::vpsStatusLabel($status)) ?>
            </span>
            <a href="<?= url('/services/vps/manage') ?>" class="btn btn-outline-light btn-sm">
                <i class="fas fa-arrow-left me-1"></i>Danh sách
            </a>
        </div>
    </div>

    <?php if (!$refreshed): ?>
        <div class="alert alert-warning small">
            <i class="fas fa-triangle-exclamation me-1"></i>
            Không làm mới được thông tin từ API XVPS. Dữ liệu dưới đây là thông tin đã lưu gần nhất.
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- ===== Thông tin ===== -->
        <div class="col-lg-7">
            <div class="panel-card mb-4">
                <h6 class="mb-3"><i class="fas fa-circle-info me-2"></i>Thông tin máy chủ</h6>
                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="form-hint">IPv4</div>
                        <div class="font-numeric fw-semibold"><?= e((string)($instance['ip'] ?: '—')) ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-hint">Hệ điều hành</div>
                        <div class="fw-semibold"><?= e((string)($instance['os'] ?: '—')) ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-hint">Username</div>
                        <div class="font-numeric fw-semibold"><?= e((string)($instance['username'] ?: '—')) ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-hint">Mật khẩu root</div>
                        <div class="d-flex align-items-center gap-2">
                            <input type="password" class="form-control form-control-sm font-numeric js-toggle-pw"
                                   value="<?= e((string)($instance['password'] ?: '')) ?>" readonly
                                   placeholder="Chưa có thông tin">
                            <button type="button" class="btn btn-outline-light btn-sm js-eye" title="Hiện / ẩn">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-hint">Cấu hình</div>
                        <div class="fw-semibold">
                            <?= (int)$instance['cpu'] ?> vCPU · <?= (int)$instance['ram'] ?> MB RAM ·
                            <?= (int)$instance['disk'] ?> GB
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-hint">Ngày hết hạn</div>
                        <div class="fw-semibold">
                            <?= (string)$instance['next_due_date'] !== ''
                                ? e(date('d/m/Y', strtotime((string)$instance['next_due_date'])))
                                : '—' ?>
                            <span class="form-hint">(<?= (int)$instance['day_left'] ?> ngày còn)</span>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-hint">Chu kỳ</div>
                        <div class="fw-semibold"><?= e((string)($instance['billing_cycle'] ?: '—')) ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-hint">Mã đơn</div>
                        <div class="font-numeric fw-semibold"><?= e((string)($instance['order_code'] ?: '—')) ?></div>
                    </div>
                </div>

                <div class="alert alert-info small mt-3 mb-0">
                    <i class="fas fa-key me-1"></i>
                    Mật khẩu chỉ hiển thị tại đây. Hãy lưu lại ngay — bạn có thể cài lại OS bất cứ lúc nào
                    nếu quên mật khẩu.
                </div>
            </div>

            <!-- ===== Nâng cấp tài nguyên ===== -->
            <?php if ($canAct): ?>
                <div class="panel-card">
                    <h6 class="mb-3"><i class="fas fa-arrow-up-wide-short me-2"></i>Nâng cấp tài nguyên</h6>
                    <form method="POST" action="<?= url('/services/vps/action') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="vps_id" value="<?= e($vpsId) ?>">
                        <input type="hidden" name="action" value="addon-vps">

                        <div class="row g-2 align-items-end">
                            <div class="col-4">
                                <label class="form-label" for="addonCpu">CPU</label>
                                <input type="number" name="cpu" id="addonCpu" class="form-control font-numeric"
                                       min="0" step="1" value="0">
                            </div>
                            <div class="col-4">
                                <label class="form-label" for="addonRam">RAM (GB)</label>
                                <input type="number" name="ram" id="addonRam" class="form-control font-numeric"
                                       min="0" step="1" value="0">
                            </div>
                            <div class="col-4">
                                <label class="form-label" for="addonDisk">Disk (GB)</label>
                                <input type="number" name="disk" id="addonDisk" class="form-control font-numeric"
                                       min="0" step="10" value="0">
                            </div>
                            <div class="col-12 d-grid">
                                <button type="submit" class="btn btn-outline-gold btn-sm"
                                        onclick="return confirm('Nâng cấp tài nguyên VPS? Phí sẽ được trừ từ số dư ví của bạn.')">
                                    <i class="fas fa-arrow-up me-1"></i>Nâng cấp
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <!-- ===== Hành động ===== -->
        <div class="col-lg-5">
            <div class="panel-card mb-4">
                <h6 class="mb-3"><i class="fas fa-power-off me-2"></i>Điều khiển nguồn</h6>

                <?php if (!$canAct): ?>
                    <p class="text-muted-2 small mb-0">
                        VPS đang ở trạng thái <b><?= e(XvpsService::vpsStatusLabel($status)) ?></b> —
                        không thể thực hiện thao tác nguồn lúc này.
                    </p>
                <?php else: ?>
                    <div class="d-grid gap-2">
                        <?php if ($isOff): ?>
                            <form method="POST" action="<?= url('/services/vps/action') ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="vps_id" value="<?= e($vpsId) ?>">
                                <input type="hidden" name="action" value="start">
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="fas fa-play me-1"></i>Khởi động
                                </button>
                            </form>
                        <?php endif; ?>

                        <?php if ($isOn): ?>
                            <form method="POST" action="<?= url('/services/vps/action') ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="vps_id" value="<?= e($vpsId) ?>">
                                <input type="hidden" name="action" value="restart">
                                <button type="submit" class="btn btn-warning w-100">
                                    <i class="fas fa-rotate me-1"></i>Khởi động lại
                                </button>
                            </form>
                            <form method="POST" action="<?= url('/services/vps/action') ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="vps_id" value="<?= e($vpsId) ?>">
                                <input type="hidden" name="action" value="stop">
                                <button type="submit" class="btn btn-outline-danger w-100"
                                        onclick="return confirm('Tắt máy chủ?')">
                                    <i class="fas fa-stop me-1"></i>Tắt máy
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ===== Gia hạn ===== -->
            <div class="panel-card mb-4">
                <h6 class="mb-3"><i class="fas fa-calendar-plus me-2"></i>Gia hạn</h6>
                <form method="POST" action="<?= url('/services/vps/action') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="vps_id" value="<?= e($vpsId) ?>">
                    <input type="hidden" name="action" value="renew-vps">

                    <label class="form-label" for="renewCycle">Chu kỳ gia hạn</label>
                    <select name="cycle" id="renewCycle" class="form-select mb-2">
                        <?php foreach ($cycles as $k => $label): ?>
                            <option value="<?= e($k) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit" class="btn btn-gold w-100"
                            onclick="return confirm('Gia hạn VPS? Tiền sẽ trừ từ ví chính của bạn.')">
                        <i class="fas fa-rotate me-1"></i>Gia hạn ngay
                    </button>
                    <p class="form-hint mt-2 mb-0">
                        Số dư của bạn bị trừ khi gia hạn. Nếu đơn không được chấp nhận, tiền được hoàn lại.
                    </p>
                </form>
            </div>

            <!-- ===== Cài lại OS ===== -->
            <div class="panel-card">
                <h6 class="mb-3"><i class="fas fa-arrow-rotate-right me-2"></i>Cài lại hệ điều hành</h6>

                <?php if (!$canAct): ?>
                    <p class="text-muted-2 small mb-0">Chỉ cài lại OS được khi VPS đang hoạt động hoặc đã tắt.</p>
                <?php elseif (!$osList): ?>
                    <p class="text-muted-2 small mb-0">Chưa có danh sách hệ điều hành.</p>
                <?php else: ?>
                    <form method="POST" action="<?= url('/services/vps/action') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="vps_id" value="<?= e($vpsId) ?>">
                        <input type="hidden" name="action" value="confirm-rebuild-vps">

                        <label class="form-label" for="rebuildOs">Chọn hệ điều hành mới</label>
                        <select name="os_id" id="rebuildOs" class="form-select mb-2" required>
                            <option value="">— Chọn —</option>
                            <?php foreach ($osList as $os): ?>
                                <option value="<?= e((string)$os['os_id']) ?>"><?= e((string)$os['os_name']) ?></option>
                            <?php endforeach; ?>
                        </select>

                        <button type="submit" class="btn btn-outline-danger w-100"
                                onclick="return confirm('Cài lại OS sẽ XOÁ toàn bộ dữ liệu trên VPS. Bạn có chắc không?')">
                            <i class="fas fa-triangle-exclamation me-1"></i>Cài lại OS
                        </button>
                        <p class="form-hint mt-2 mb-0">
                            <i class="fas fa-circle-exclamation me-1"></i>
                            Toàn bộ dữ liệu trên ổ đĩa sẽ bị xoá. IP thường được giữ nguyên.
                        </p>
                    </form>
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
