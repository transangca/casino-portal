<?php
/**
 * Bảng điều khiển VPS StoreVPS (Virtualizor) — khách hàng.
 *
 * @var array  $instance
 * @var array  $osList
 * @var array  $cycles
 * @var array  $addons
 * @var array  $history
 * @var bool   $refreshed
 */
use App\Services\VirtualizorService;

$vpsId  = (string)$instance['vps_id'];
$status = (string)$instance['vps_status'];
$isOn   = in_array($status, ['running', 'on'], true);
$isOff  = in_array($status, ['stopped', 'off'], true);
$canAct = !in_array($status, VirtualizorService::BLOCKED_STATUSES, true);
?>
<div class="container py-4">

    <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
        <div>
            <h1 class="page-title mb-1">
                <i class="fas fa-layer-group me-2 text-gold"></i>
                VPS <?= e($vpsId) ?>
            </h1>
            <p class="page-sub mb-0">
                <?= e((string)($instance['product_name'] ?: 'Máy chủ ảo StoreVPS')) ?>
                <?php if ((string)$instance['os_name'] !== ''): ?>
                    · <?= e((string)$instance['os_name']) ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="ms-auto d-flex gap-2 align-items-center">
            <span class="badge-status st-<?= e(VirtualizorService::vpsStatusBadge($status)) ?>">
                <i class="fas fa-circle me-1"></i><?= e(VirtualizorService::vpsStatusLabel($status)) ?>
            </span>
            <a href="<?= url('/services/vps-storevps/manage') ?>" class="btn btn-outline-light btn-sm">
                <i class="fas fa-arrow-left me-1"></i>Danh sách
            </a>
        </div>
    </div>

    <?php if (!$refreshed): ?>
        <div class="alert alert-warning small">
            <i class="fas fa-triangle-exclamation me-1"></i>
            Không làm mới được thông tin từ API StoreVPS. Dữ liệu dưới đây là thông tin đã lưu gần nhất.
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
                        <div class="form-hint">IPv6</div>
                        <div class="font-numeric fw-semibold"><?= e((string)($instance['ipv6'] ?: '—')) ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-hint">Username</div>
                        <div class="font-numeric fw-semibold"><?= e((string)($instance['username'] ?: 'root')) ?></div>
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
                            <?php if ((string)$instance['day_left'] !== ''): ?>
                                <span class="form-hint">(<?= e((string)$instance['day_left']) ?>)</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-hint">Chu kỳ</div>
                        <div class="fw-semibold">
                            <?= e((string)($instance['billing_cycle'] ?: '—')) ?>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-hint">Mã đơn</div>
                        <div class="font-numeric fw-semibold"><?= e((string)($instance['order_code'] ?: '—')) ?></div>
                    </div>
                </div>

                <div class="alert alert-info small mt-3 mb-0">
                    <i class="fas fa-key me-1"></i>
                    Mật khẩu chỉ hiển thị tại đây. Hãy lưu lại ngay — bạn có thể cài lại OS bất cứ lúc nào nếu quên mật khẩu.
                </div>
            </div>

            <!-- ===== Nâng cấp tài nguyên (addon) ===== -->
            <div class="panel-card mb-4">
                <h6 class="mb-3"><i class="fas fa-arrow-up-wide-short me-2"></i>Nâng cấp tài nguyên</h6>

                <?php if (!$canAct): ?>
                    <p class="text-muted-2 small mb-0">
                        Không thể nâng cấp khi VPS đang ở trạng thái
                        <b><?= e(VirtualizorService::vpsStatusLabel($status)) ?></b>.
                    </p>
                <?php else: ?>
                    <form method="POST" action="<?= url('/services/vps-storevps/addon') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="vps_id" value="<?= e($vpsId) ?>">

                        <div class="row g-2 align-items-end">
                            <?php foreach (VirtualizorService::ADDON_TYPES as $type => $label): ?>
                                <div class="col-6 col-md-4">
                                    <label class="form-label" for="add_<?= e($type) ?>"><?= e($label) ?></label>
                                    <input type="number" name="addon_<?= e($type) ?>" id="add_<?= e($type) ?>"
                                           class="form-control form-control-sm font-numeric"
                                           min="0" max="64" step="1" value="0">
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="row g-2 mt-1 align-items-end">
                            <div class="col-md-6">
                                <label class="form-label" for="addonNote">Ghi chú (không bắt buộc)</label>
                                <input type="text" name="note" id="addonNote" class="form-control form-control-sm"
                                       maxlength="190" placeholder="Ví dụ: nâng RAM lên 4GB">
                            </div>
                            <div class="col-md-6 d-flex align-items-center">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"
                                           name="reboot_after_fulfillment" id="addonReboot" value="1">
                                    <label class="form-check-label small" for="addonReboot">
                                        Khởi động lại sau khi nâng cấp
                                    </label>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-outline-gold btn-sm mt-3"
                                onclick="return confirm('Gửi yêu cầu nâng cấp tài nguyên cho VPS này?')">
                            <i class="fas fa-arrow-up me-1"></i>Gửi yêu cầu nâng cấp
                        </button>
                        <p class="form-hint mt-2 mb-0">
                            Đơn vị tính theo bảng giá chính thức (hiển thị tại trang danh mục).
                        </p>
                    </form>
                <?php endif; ?>
            </div>

            <!-- ===== Lịch sử thao tác ===== -->
            <div class="panel-card">
                <h6 class="mb-3"><i class="fas fa-clock-rotate-left me-2"></i>Lịch sử thao tác</h6>

                <?php if (!$history): ?>
                    <p class="text-muted-2 small mb-0">Chưa có thao tác nào được ghi nhận.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Thời gian</th>
                                    <th>Hành động</th>
                                    <th>Kết quả</th>
                                    <th>Ghi chú</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($history as $h): ?>
                                    <tr>
                                        <td class="font-numeric small">
                                            <?= e(date('d/m/Y H:i', strtotime((string)$h['created_at']))) ?>
                                        </td>
                                        <td class="small"><?= e((string)$h['action']) ?></td>
                                        <td>
                                            <span class="badge-status st-<?= (string)$h['status'] === 'success' ? 'active' : 'danger' ?>">
                                                <?= (string)$h['status'] === 'success' ? 'OK' : 'Lỗi' ?>
                                            </span>
                                        </td>
                                        <td class="small text-muted-2"><?= e((string)($h['message'] ?: '')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ===== Hành động ===== -->
        <div class="col-lg-5">
            <!-- Nguồn -->
            <div class="panel-card mb-4">
                <h6 class="mb-3"><i class="fas fa-power-off me-2"></i>Điều khiển nguồn</h6>

                <?php if (!$canAct): ?>
                    <p class="text-muted-2 small mb-0">
                        VPS đang ở trạng thái <b><?= e(VirtualizorService::vpsStatusLabel($status)) ?></b> —
                        không thể thực hiện thao tác nguồn lúc này.
                    </p>
                <?php else: ?>
                    <div class="d-grid gap-2">
                        <?php if ($isOff): ?>
                            <form method="POST" action="<?= url('/services/vps-storevps/action') ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="vps_id" value="<?= e($vpsId) ?>">
                                <input type="hidden" name="action" value="start">
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="fas fa-play me-1"></i>Khởi động
                                </button>
                            </form>
                        <?php endif; ?>

                        <?php if ($isOn): ?>
                            <form method="POST" action="<?= url('/services/vps-storevps/action') ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="vps_id" value="<?= e($vpsId) ?>">
                                <input type="hidden" name="action" value="restart">
                                <button type="submit" class="btn btn-warning w-100">
                                    <i class="fas fa-rotate me-1"></i>Khởi động lại
                                </button>
                            </form>
                            <form method="POST" action="<?= url('/services/vps-storevps/action') ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="vps_id" value="<?= e($vpsId) ?>">
                                <input type="hidden" name="action" value="stop">
                                <button type="submit" class="btn btn-outline-danger w-100"
                                        onclick="return confirm('Tắt máy chủ?')">
                                    <i class="fas fa-stop me-1"></i>Tắt máy
                                </button>
                            </form>
                            <form method="POST" action="<?= url('/services/vps-storevps/action') ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="vps_id" value="<?= e($vpsId) ?>">
                                <input type="hidden" name="action" value="poweroff">
                                <button type="submit" class="btn btn-outline-danger w-100"
                                        onclick="return confirm('Tắt cứng máy chủ?')">
                                    <i class="fas fa-bolt me-1"></i>Tắt cứng
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Gia hạn -->
            <div class="panel-card mb-4">
                <h6 class="mb-3"><i class="fas fa-calendar-plus me-2"></i>Gia hạn</h6>
                <form method="POST" action="<?= url('/services/vps-storevps/renew') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="vps_id" value="<?= e($vpsId) ?>">

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

            <!-- Tự động gia hạn -->
            <div class="panel-card mb-4">
                <h6 class="mb-3"><i class="fas fa-repeat me-2"></i>Tự động gia hạn</h6>
                <form method="POST" action="<?= url('/services/vps-storevps/auto-renew') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="vps_id" value="<?= e($vpsId) ?>">

                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="enabled" value="1"
                               id="autoRenew" <?= (int)$instance['auto_renew'] === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="autoRenew">
                            Bật tự động gia hạn
                        </label>
                    </div>

                    <button type="submit" class="btn btn-outline-gold btn-sm mt-3">
                        <i class="fas fa-floppy-disk me-1"></i>Lưu trạng thái
                    </button>
                </form>
            </div>

            <!-- Đổi mật khẩu -->
            <div class="panel-card mb-4">
                <h6 class="mb-3"><i class="fas fa-key me-2"></i>Đổi mật khẩu root</h6>
                <form method="POST" action="<?= url('/services/vps-storevps/password') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="vps_id" value="<?= e($vpsId) ?>">

                    <label class="form-label" for="newPass">Mật khẩu mới</label>
                    <input type="password" name="new_password" id="newPass" class="form-control font-numeric mb-2"
                           required minlength="10" maxlength="64"
                           placeholder="10–64 ký tự, ≥2/3 nhóm">

                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="on_boot" value="1" id="onBoot" checked>
                        <label class="form-check-label small" for="onBoot">
                            Tự động set lại mật khẩu khi khởi động
                        </label>
                    </div>

                    <button type="submit" class="btn btn-outline-gold btn-sm w-100"
                            onclick="return confirm('Đổi mật khẩu root của VPS?')">
                        <i class="fas fa-key me-1"></i>Đổi mật khẩu
                    </button>
                </form>
            </div>

            <!-- Đổi hostname -->
            <div class="panel-card mb-4">
                <h6 class="mb-3"><i class="fas fa-signature me-2"></i>Đổi hostname</h6>
                <form method="POST" action="<?= url('/services/vps-storevps/hostname') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="vps_id" value="<?= e($vpsId) ?>">

                    <label class="form-label" for="newHost">Hostname mới</label>
                    <input type="text" name="new_hostname" id="newHost" class="form-control font-numeric mb-2"
                           required minlength="3" maxlength="63"
                           value="<?= e((string)$instance['hostname']) ?>"
                           pattern="[a-z0-9][a-z0-9._\-]{1,61}[a-z0-9]">

                    <button type="submit" class="btn btn-outline-gold btn-sm w-100"
                            onclick="return confirm('Đổi hostname của VPS?')">
                        <i class="fas fa-signature me-1"></i>Đổi hostname
                    </button>
                </form>
            </div>

            <!-- Cài lại OS -->
            <div class="panel-card mb-4">
                <h6 class="mb-3"><i class="fas fa-arrow-rotate-right me-2"></i>Cài lại hệ điều hành</h6>

                <?php if (!$canAct): ?>
                    <p class="text-muted-2 small mb-0">Chỉ cài lại OS được khi VPS không ở trạng thái đang xử lý.</p>
                <?php elseif (!$osList): ?>
                    <p class="text-muted-2 small mb-0">Chưa có danh sách hệ điều hành.</p>
                <?php else: ?>
                    <form method="POST" action="<?= url('/services/vps-storevps/rebuild') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="vps_id" value="<?= e($vpsId) ?>">

                        <label class="form-label" for="rebuildOs">Hệ điều hành mới</label>
                        <select name="os_id" id="rebuildOs" class="form-select mb-2" required>
                            <option value="">— Chọn —</option>
                            <?php foreach ($osList as $os): ?>
                                <option value="<?= e((string)$os['os_id']) ?>"><?= e((string)$os['os_name']) ?></option>
                            <?php endforeach; ?>
                        </select>

                        <label class="form-label" for="rebuildPass">Mật khẩu mới (không bắt buộc)</label>
                        <input type="password" name="new_password" id="rebuildPass" class="form-control font-numeric mb-2"
                               minlength="10" maxlength="64" placeholder="Để trống nếu dùng mật khẩu mặc định">

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

            <!-- Hủy dịch vụ -->
            <div class="panel-card">
                <h6 class="mb-3"><i class="fas fa-ban me-2"></i>Hủy dịch vụ</h6>
                <form method="POST" action="<?= url('/services/vps-storevps/cancel') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="vps_id" value="<?= e($vpsId) ?>">

                    <button type="submit" class="btn btn-outline-danger btn-sm w-100"
                            onclick="return confirm('Hủy VPS này? Thao tác không thể hoàn tác và dữ liệu sẽ mất vĩnh viễn.')">
                        <i class="fas fa-ban me-1"></i>Hủy VPS
                    </button>
                    <p class="form-hint mt-2 mb-0">
                        Hủy VPS sẽ xoá dữ liệu vĩnh viễn. Không được hoàn tiền.
                    </p>
                </form>
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
