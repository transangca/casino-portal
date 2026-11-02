<?php
/**
 * Dịch vụ Bank ảo.
 *
 * @var string     $tab
 * @var array      $accounts
 * @var array      $transactions
 * @var array      $orders
 * @var array      $prices
 * @var array      $bankTypes
 * @var array      $bankNames
 * @var bool       $configured
 * @var bool       $enabled
 */
$periods = [];
foreach ($prices as $p) {
    $periods[$p['period']] = $p;
}
$statusLabels = [1 => 'Hoạt động', 2 => 'Tạm khoá', 0 => 'Hết hạn'];

// Danh sách phần mềm / APK do admin quản lý (mỗi mục: name, url, type, note)
$bankApps = json_decode((string)setting('virtual_bank_apps', '[]'), true);
if (!is_array($bankApps)) {
    $bankApps = [];
}
?>
<div class="container py-4">

    <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
        <div>
            <h1 class="page-title mb-1"><i class="fas fa-vault me-2 text-gold"></i>Bank ảo</h1>
            <p class="page-sub mb-0">
                Thuê tài khoản ngân hàng ảo (MB, VCB, ACB, BIDV, TCB, TPBank, Vietinbank),
                gia hạn và tạo thông báo biến động số dư — đồng bộ 2 chiều.
            </p>
        </div>
        <div class="ms-auto d-flex gap-2">
            <a href="<?= url('/services/virtual-bank/sync') ?>" class="btn btn-outline-gold btn-sm">
                <i class="fas fa-rotate me-1"></i>Đồng bộ từ API
            </a>
            <a href="<?= url('/services/virtual-bank/sync-transactions') ?>" class="btn btn-outline-gold btn-sm">
                <i class="fas fa-list me-1"></i>Đồng bộ giao dịch
            </a>
        </div>
    </div>

    <?php if (!$enabled): ?>
        <div class="alert alert-secondary">
            <i class="fas fa-pause-circle me-1"></i>
            Dịch vụ Bank ảo hiện đang tạm dừng. Vui lòng quay lại sau.
        </div>
    <?php elseif (!$configured): ?>
        <div class="alert alert-warning">
            <i class="fas fa-triangle-exclamation me-1"></i>
            Dịch vụ chưa được cấu hình ACCESS_KEY. Admin nhập key lấy từ bot
            <b>@WsVenderBot</b> (gõ <code>/mykey</code>) tại trang
            <a href="<?= url('/admin/virtual-bank') ?>">quản trị Bank ảo</a>.
        </div>
    <?php endif; ?>

    <!-- ===== Tabs ===== -->
    <ul class="mode-tabs mb-3">
        <a href="<?= url('/services/virtual-bank?tab=accounts') ?>" class="<?= $tab === 'accounts' ? 'active' : '' ?>">
            <i class="fas fa-list me-1"></i>Tài khoản (<?= count($accounts) ?>)
        </a>
        <a href="<?= url('/services/virtual-bank?tab=transactions') ?>" class="<?= $tab === 'transactions' ? 'active' : '' ?>">
            <i class="fas fa-receipt me-1"></i>Giao dịch (<?= count($transactions) ?>)
        </a>
        <a href="<?= url('/services/virtual-bank?tab=orders') ?>" class="<?= $tab === 'orders' ? 'active' : '' ?>">
            <i class="fas fa-clipboard-list me-1"></i>Lịch sử thuê (<?= count($orders) ?>)
        </a>
        <a href="<?= url('/services/virtual-bank?tab=downloads') ?>" class="<?= $tab === 'downloads' ? 'active' : '' ?>">
            <i class="fas fa-download me-1"></i>Tải xuống (<?= count($bankApps) ?>)
        </a>
    </ul>

    <?php if ($tab === 'accounts'): ?>
        <div class="row g-4">
            <!-- ===== Danh sách tài khoản ===== -->
            <div class="col-lg-8">
                <!-- Logo các ngân hàng hỗ trợ -->
                <div class="panel-card mb-4">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <i class="fas fa-building-columns text-gold"></i>
                        <b class="small">Ngân hàng hỗ trợ</b>
                        <span class="form-hint ms-auto">Logo chính thức từng ngân hàng</span>
                    </div>
                    <div class="brand-grid">
                        <?php foreach ($bankTypes as $code => $label): ?>
                            <?php
                            $logo = bank_logo((string)$code, 'brand-logo');
                            $name = (string)($bankNames[$code] ?? $label);
                            ?>
                            <div class="brand-card<?= $logo !== '' ? ' is-brand' : '' ?>">
                                <span class="brand-icon">
                                    <?php if ($logo !== ''): ?>
                                        <?= $logo ?>
                                    <?php else: ?>
                                        <i class="fas fa-building-columns"></i>
                                    <?php endif; ?>
                                </span>
                                <span class="brand-name"><?= e(bank_display_name((string)$code, $name)) ?></span>
                                <span class="brand-sub"><?= e((string)$code) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if (!$accounts): ?>
                    <div class="empty-state">
                        <i class="fas fa-vault fs-1 d-block mb-3"></i>
                        <p class="mb-0">Chưa có tài khoản ngân hàng ảo nào.</p>
                    </div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($accounts as $a): ?>
                            <?php
                            $expired = $a['expired_at'] && strtotime((string)$a['expired_at']) < time();
                            $st      = (int)$a['status'];
                            $code    = (string)($a['bank_type'] ?: '');
                            $logo    = bank_logo($code, 'brand-logo');
                            $bName   = (string)($a['bank_name'] ?: bank_display_name($code, $code));
                            ?>
                            <div class="col-sm-6">
                                <div class="vbank-card">
                                    <div class="d-flex align-items-center gap-3">
                                        <?php if ($logo !== ''): ?>
                                            <span class="vbank-avatar vbank-avatar-fallback">
                                                <?= $logo ?>
                                            </span>
                                        <?php elseif (!empty($a['avatar'])): ?>
                                            <img src="<?= e((string)$a['avatar']) ?>" alt="" class="vbank-avatar" loading="lazy">
                                        <?php else: ?>
                                            <span class="vbank-avatar vbank-avatar-fallback">
                                                <i class="fas fa-building-columns"></i>
                                            </span>
                                        <?php endif; ?>
                                        <div class="min-w-0 flex-fill">
                                            <div class="fw-semibold text-truncate"><?= e((string)($a['bank_username'] ?: 'Chưa đặt tên')) ?></div>
                                            <div class="small text-muted-2 text-truncate">
                                                <?= e($bName) ?>
                                            </div>
                                        </div>
                                        <span class="badge-status st-<?= $expired ? 'closed' : ($st === 1 ? 'active' : 'pending') ?>">
                                            <?= $expired ? 'Hết hạn' : ($statusLabels[$st] ?? 'Hoạt động') ?>
                                        </span>
                                    </div>

                                    <div class="vbank-meta">
                                        <div><span>Số TK</span><b class="font-numeric"><?= e((string)($a['bank_number'] ?: '—')) ?></b></div>
                                        <div><span>Số dư</span><b class="text-gold"><?= money((float)$a['account_balance']) ?></b></div>
                                        <div><span>Hết hạn</span><b><?= $a['expired_at'] ? e((string)$a['expired_at']) : '—' ?></b></div>
                                        <div><span>Loại</span><b><?= e($bName) ?></b></div>
                                    </div>

                                    <div class="d-flex flex-wrap gap-2 mt-3">
                                        <a href="<?= url('/services/virtual-bank/account/' . (int)$a['id']) ?>"
                                           class="btn btn-sm btn-outline-gold">Chi tiết</a>
                                        <button type="button" class="btn btn-sm btn-outline-light"
                                                data-bs-toggle="modal" data-bs-target="#extendModal"
                                                data-id="<?= (int)$a['id'] ?>">Gia hạn</button>
                                        <button type="button" class="btn btn-sm btn-outline-light"
                                                data-bs-toggle="modal" data-bs-target="#notifyModal"
                                                data-id="<?= (int)$a['id'] ?>">Thông báo biến động</button>
                                        <form method="POST" action="<?= url('/services/virtual-bank/delete') ?>"
                                              data-confirm="Xoá tài khoản ngân hàng ảo này?" class="d-inline ms-auto">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="account_id" value="<?= (int)$a['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ===== Form tạo tài khoản ===== -->
            <div class="col-lg-4">
                <div class="panel-card">
                    <h6 class="mb-3"><i class="fas fa-plus-circle me-2"></i>Thuê tài khoản mới</h6>

                    <form method="POST" action="<?= url('/services/virtual-bank/create') ?>">
                        <?= csrf_field() ?>

                        <div class="mb-2">
                            <label class="form-label">Loại ngân hàng</label>
                            <select name="type" class="form-select form-select-sm" required>
                                <?php foreach ($bankTypes as $code => $label): ?>
                                    <option value="<?= e((string)$code) ?>">
                                        <?= e(bank_display_name((string)$code, (string)($bankNames[$code] ?? $label))) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-2">
                            <label class="form-label">Số điện thoại</label>
                            <input type="text" name="phone" class="form-control form-control-sm font-numeric"
                                   placeholder="0912345678" required>
                        </div>

                        <div class="mb-2">
                            <label class="form-label">Số tài khoản</label>
                            <input type="text" name="bank_number" class="form-control form-control-sm font-numeric"
                                   placeholder="9704151234567890" required>
                        </div>

                        <div class="mb-2">
                            <label class="form-label">Tên chủ tài khoản</label>
                            <input type="text" name="bank_username" class="form-control form-control-sm"
                                   placeholder="NGUYEN VAN A" required>
                        </div>

                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label">Mật khẩu</label>
                                <input type="text" name="password" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">OTP</label>
                                <input type="text" name="otp" class="form-control form-control-sm font-numeric">
                            </div>
                        </div>

                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label">Số dư ban đầu</label>
                                <input type="number" name="account_balance" class="form-control form-control-sm font-numeric"
                                       value="0" min="0" step="1000">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Thời hạn</label>
                                <select name="expired_period" class="form-select form-select-sm" required>
                                    <?php foreach ($periods as $key => $p): ?>
                                        <option value="<?= e((string)$key) ?>">
                                            <?= e((string)($p['label'] ?? $key)) ?> — <?= money((float)($p['price'] ?? 0)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <button class="btn btn-gold btn-sm w-100">
                            <i class="fas fa-check me-1"></i>Thuê & thanh toán qua ví
                        </button>
                        <p class="form-hint mt-2 mb-0">
                            Tiền được trừ từ ví khi đặt, tự hoàn lại nếu API báo lỗi.
                        </p>
                    </form>
                </div>
            </div>
        </div>

    <?php elseif ($tab === 'transactions'): ?>
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="panel-card">
                    <h6 class="mb-3"><i class="fas fa-receipt me-2"></i>Lịch sử giao dịch</h6>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                            <tr><th>Mã</th><th>Người gửi</th><th>Số tiền</th><th>Ngân hàng</th><th>Thời gian</th></tr>
                            </thead>
                            <tbody>
                            <?php if (!$transactions): ?>
                                <tr><td colspan="5" class="text-center text-muted-2 py-4">Chưa có giao dịch nào.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($transactions as $t): ?>
                                <tr>
                                    <td class="small font-numeric"><?= e((string)($t['transaction_code'] ?: '—')) ?></td>
                                    <td>
                                        <div class="small"><?= e((string)($t['from_name'] ?: '—')) ?></div>
                                        <div class="form-hint"><?= e((string)($t['note'] ?: '')) ?></div>
                                    </td>
                                    <td class="text-gold fw-semibold font-numeric"><?= money((float)$t['amount']) ?></td>
                                    <td class="small">
                                        <?php $tlogo = bank_logo((string)($t['bank_code_id'] ?? ''), 'brand-logo'); ?>
                                        <?php if ($tlogo !== ''): ?>
                                            <span class="d-inline-flex align-items-center gap-2">
                                                <span class="vbank-avatar vbank-avatar-fallback" style="width:26px;height:26px;flex:0 0 26px;">
                                                    <?= $tlogo ?>
                                                </span>
                                                <?= e((string)($t['bank_name'] ?: $t['bank_code_id'])) ?>
                                            </span>
                                        <?php else: ?>
                                            <?= e((string)($t['bank_name'] ?: $t['bank_code_id'])) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted-2"><?= e((string)($t['created_at'] ?: $t['synced_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="panel-card">
                    <h6 class="mb-3"><i class="fas fa-bolt me-2"></i>Tạo thông báo biến động</h6>
                    <p class="form-hint">
                        Hỗ trợ ngân hàng: <?= e(implode(', ', \App\Services\VirtualBankService::NOTIFY_BANKS)) ?>.
                    </p>

                    <form method="POST" action="<?= url('/services/virtual-bank/notify') ?>">
                        <?= csrf_field() ?>
                        <div class="mb-2">
                            <label class="form-label">Tài khoản</label>
                            <select name="account_id" class="form-select form-select-sm" required>
                                <option value="">-- Chọn tài khoản --</option>
                                <?php foreach ($accounts as $a): ?>
                                    <option value="<?= (int)$a['id'] ?>">
                                        <?= e((string)($a['bank_username'] ?: 'TK #' . $a['id'])) ?>
                                        — <?= e((string)$a['bank_number']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Số tiền</label>
                            <input type="number" name="amount" class="form-control form-control-sm font-numeric" min="1" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Tên người gửi</label>
                            <input type="text" name="sender_name" class="form-control form-control-sm" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Số tài khoản người gửi</label>
                            <input type="text" name="sender_account" class="form-control form-control-sm font-numeric" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nội dung</label>
                            <input type="text" name="note" class="form-control form-control-sm" placeholder="Chuyen tien" required>
                        </div>
                        <button class="btn btn-gold btn-sm w-100">Tạo thông báo</button>
                    </form>
                </div>
            </div>
        </div>

    <?php elseif ($tab === 'downloads'): ?>
        <div class="panel-card">
            <h6 class="mb-3"><i class="fas fa-download me-2"></i>Tải xuống ứng dụng ngân hàng</h6>

            <?php if (!$bankApps): ?>
                <div class="empty-state">
                    <i class="fas fa-cloud-arrow-down fs-1 d-block mb-3"></i>
                    <p class="mb-0">
                        Chưa có file tải xuống nào. Admin có thể thêm file hoặc link tải
                        (APK, bản cài đặt, tài liệu hướng dẫn) tại trang quản trị Bank ảo.
                    </p>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($bankApps as $app):
                        if (!is_array($app)) {
                            continue;
                        }
                        $appName  = (string)($app['name'] ?? 'Ứng dụng');
                        $appUrl   = (string)($app['url'] ?? '');
                        $appType  = (string)($app['type'] ?? 'apk');
                        $appNote  = (string)($app['note'] ?? '');
                        $appCode  = (string)($app['bank_code'] ?? '');
                        $logo     = $appCode !== '' ? bank_logo($appCode, 'brand-logo') : brand_logo($appName, 'bank');
                        $typeMap  = ['apk' => 'APK', 'dmg' => 'macOS', 'exe' => 'Windows', 'zip' => 'ZIP', 'link' => 'Link'];
                        ?>
                        <div class="col-sm-6 col-lg-4">
                            <div class="svc-card">
                                <div class="d-flex align-items-center gap-3">
                                    <span class="brand-icon">
                                        <?php if ($logo !== ''): ?>
                                            <?= $logo ?>
                                        <?php else: ?>
                                            <i class="fas fa-file-arrow-down"></i>
                                        <?php endif; ?>
                                    </span>
                                    <div class="min-w-0 flex-fill">
                                        <div class="svc-card-title text-truncate"><?= e($appName) ?></div>
                                        <div class="svc-card-desc"><?= e($appNote ?: $typeMap[$appType] ?? $appType) ?></div>
                                    </div>
                                </div>
                                <div class="svc-card-meta">
                                    <span class="badge-status st-active"><?= e($typeMap[$appType] ?? strtoupper($appType)) ?></span>
                                    <?php if ($appUrl !== ''): ?>
                                        <a class="text-gold fw-semibold" href="<?= e($appUrl) ?>" target="_blank" rel="noopener">
                                            <i class="fas fa-download me-1"></i>Tải xuống
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <div class="panel-card">
            <h6 class="mb-3"><i class="fas fa-clipboard-list me-2"></i>Lịch sử thuê & gia hạn</h6>
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>Thời gian</th><th>Hành động</th><th>Chu kỳ</th><th>Giá</th><th>Trạng thái</th><th>Ghi chú</th></tr></thead>
                    <tbody>
                    <?php if (!$orders): ?>
                        <tr><td colspan="6" class="text-center text-muted-2 py-4">Chưa có đơn nào.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($orders as $o): ?>
                        <tr>
                            <td class="small text-muted-2"><?= e((string)$o['created_at']) ?></td>
                            <td class="small">
                                <?= $o['action'] === 'create' ? 'Tạo tài khoản' : 'Gia hạn' ?>
                            </td>
                            <td class="small"><?= e((string)($o['period'] ?: '—')) ?></td>
                            <td class="text-gold fw-semibold font-numeric"><?= money((float)$o['price']) ?></td>
                            <td>
                                <span class="badge-status st-<?= $o['status'] === 'success' ? 'active' : ($o['status'] === 'failed' ? 'closed' : 'pending') ?>">
                                    <?= $o['status'] === 'success' ? 'Thành công' : ($o['status'] === 'failed' ? 'Thất bại' : 'Đang xử lý') ?>
                                </span>
                            </td>
                            <td class="small text-muted-2"><?= e((string)($o['message'] ?: '—')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- ===== Modal gia hạn ===== -->
<div class="modal fade" id="extendModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content casino-modal">
            <form method="POST" action="<?= url('/services/virtual-bank/extend') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="account_id" id="extendAccountId" value="">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-clock-rotate-left me-2"></i>Gia hạn tài khoản</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Chu kỳ gia hạn</label>
                    <select name="period" class="form-select" required>
                        <?php foreach ($periods as $key => $p): ?>
                            <option value="<?= e((string)$key) ?>">
                                <?= e((string)($p['label'] ?? $key)) ?> — <?= money((float)($p['price'] ?? 0)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Huỷ</button>
                    <button class="btn btn-gold">Gia hạn & thanh toán</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== Modal thông báo biến động ===== -->
<div class="modal fade" id="notifyModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content casino-modal">
            <form method="POST" action="<?= url('/services/virtual-bank/notify') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="account_id" id="notifyAccountId" value="">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-bolt me-2"></i>Thông báo biến động số dư</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label">Số tiền</label>
                        <input type="number" name="amount" class="form-control font-numeric" min="1" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Tên người gửi</label>
                        <input type="text" name="sender_name" class="form-control" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Số tài khoản người gửi</label>
                        <input type="text" name="sender_account" class="form-control font-numeric" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Nội dung</label>
                        <input type="text" name="note" class="form-control" placeholder="Chuyen tien" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Huỷ</button>
                    <button class="btn btn-gold">Tạo thông báo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('click', function (e) {
    var ext = e.target.closest('[data-bs-target="#extendModal"]');
    if (ext) {
        document.getElementById('extendAccountId').value = ext.getAttribute('data-id');
    }
    var nt = e.target.closest('[data-bs-target="#notifyModal"]');
    if (nt) {
        document.getElementById('notifyAccountId').value = nt.getAttribute('data-id');
    }
});
</script>
