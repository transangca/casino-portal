<?php
/**
 * Trang quản trị dịch vụ Bank ảo.
 *
 * @var array  $accounts
 * @var array  $stats
 * @var string $baseUrl
 * @var bool   $hasKey
 */
$adminPage = 'virtual-bank';

// Danh sách file/link tải xuống do admin quản lý
$bankApps = json_decode((string)setting('virtual_bank_apps', '[]'), true);
if (!is_array($bankApps)) {
    $bankApps = [];
}
?>
<h1 class="page-title">Dịch vụ Bank ảo</h1>
<p class="page-sub">
    Cấu hình ACCESS_KEY lấy từ bot <b>@WsVenderBot</b> (gõ <code>/mykey</code>),
    theo dõi toàn bộ tài khoản ảo đã cấp cho khách.
</p>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="panel-card">
            <h3><i class="fas fa-key text-gold me-2"></i>Cấu hình API</h3>

            <form method="POST" action="<?= url('/admin/virtual-bank/save') ?>">
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label class="form-label" for="base_url">Base URL</label>
                    <input type="text" class="form-control font-numeric" id="base_url" name="base_url"
                           value="<?= e($baseUrl) ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label" for="access_key">ACCESS_KEY</label>
                    <input type="password" class="form-control font-numeric" id="access_key" name="access_key"
                           placeholder="<?= $hasKey ? 'Đã cấu hình — nhập mới để thay đổi' : 'Dán access key vào đây' ?>">
                    <div class="form-hint">
                        Được mã hóa AES-256-GCM trước khi lưu vào database.
                    </div>
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="service_on" name="service_on" value="1"
                        <?= setting('heybank_service_on', '1') === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="service_on">Bật dịch vụ Bank ảo cho người dùng</label>
                </div>

                <button class="btn btn-primary w-100"><i class="fas fa-save me-2"></i>Lưu cấu hình</button>
            </form>

            <div class="alert alert-<?= $hasKey ? 'success' : 'warning' ?> mt-3 mb-0 small">
                <i class="fas fa-circle-info me-1"></i>
                Trạng thái: <b><?= $hasKey ? 'Đã có ACCESS_KEY' : 'Chưa có ACCESS_KEY' ?></b>
            </div>
        </div>

        <div class="panel-card mt-4">
            <h3><i class="fas fa-chart-simple text-gold me-2"></i>Thống kê</h3>
            <div class="row g-3 text-center">
                <div class="col-6">
                    <div class="stat-card">
                        <div class="stat-value"><?= (int)$stats['total'] ?></div>
                        <div class="stat-label">Tài khoản</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="stat-card">
                        <div class="stat-value"><?= (int)$stats['active'] ?></div>
                        <div class="stat-label">Đang hoạt động</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="stat-card">
                        <div class="stat-value"><?= (int)$stats['expired'] ?></div>
                        <div class="stat-label">Hết hạn</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="stat-card">
                        <div class="stat-value"><?= number_format((float)$stats['revenue'], 0, ',', '.') ?></div>
                        <div class="stat-label">Doanh thu (đ)</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <!-- ===== Quản lý file tải xuống ===== -->
        <div class="panel-card mb-4">
            <h3><i class="fas fa-cloud-arrow-down text-gold me-2"></i>File / link tải xuống</h3>
            <p class="form-hint">
                Thêm bản APK, file cài đặt hoặc link tải cho từng ứng dụng ngân hàng.
                Danh sách này hiển thị ở tab "Tải xuống" của trang Bank ảo.
            </p>

            <form method="POST" action="<?= url('/admin/virtual-bank/save-apps') ?>" class="mb-3">
                <?= csrf_field() ?>
                <div class="row g-2 mb-2">
                    <div class="col-md-4">
                        <label class="form-label">Tên ứng dụng</label>
                        <input type="text" name="name" class="form-control form-control-sm" placeholder="VD: MB Bank" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Mã ngân hàng</label>
                        <select name="bank_code" class="form-select form-select-sm">
                            <option value="">-- Không --</option>
                            <?php foreach (\App\Services\VirtualBankService::BANK_TYPES as $code => $label): ?>
                                <option value="<?= e((string)$code) ?>"><?= e((string)$code) ?> — <?= e((string)$label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Loại</label>
                        <select name="type" class="form-select form-select-sm">
                            <option value="apk">APK</option>
                            <option value="link">Link</option>
                            <option value="zip">ZIP</option>
                            <option value="exe">Windows</option>
                            <option value="dmg">macOS</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button class="btn btn-gold btn-sm w-100"><i class="fas fa-plus me-1"></i>Thêm</button>
                    </div>
                </div>
                <div class="row g-2">
                    <div class="col-md-7">
                        <label class="form-label">Link tải (URL)</label>
                        <input type="text" name="url" class="form-control form-control-sm" placeholder="https://...">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Mô tả</label>
                        <input type="text" name="note" class="form-control form-control-sm" placeholder="VD: Bản cập nhật mới nhất">
                    </div>
                </div>
            </form>

            <?php if ($bankApps): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead><tr><th>Tên</th><th>Mã</th><th>Loại</th><th>Link</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($bankApps as $i => $app):
                            if (!is_array($app)) {
                                continue;
                            }
                            ?>
                            <tr>
                                <td class="small fw-semibold"><?= e((string)($app['name'] ?? '—')) ?></td>
                                <td class="small"><?= e((string)($app['bank_code'] ?? '—')) ?></td>
                                <td class="small"><?= e(strtoupper((string)($app['type'] ?? 'apk'))) ?></td>
                                <td class="small">
                                    <a href="<?= e((string)($app['url'] ?? '#')) ?>" target="_blank" rel="noopener" class="text-truncate d-inline-block" style="max-width:220px">
                                        <?= e((string)($app['url'] ?: '—')) ?>
                                    </a>
                                </td>
                                <td class="text-end">
                                    <form method="POST" action="<?= url('/admin/virtual-bank/delete-app') ?>" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="index" value="<?= (int)$i ?>">
                                        <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted-2 small mb-0">Chưa có mục tải xuống nào.</p>
            <?php endif; ?>
        </div>

        <div class="panel-card">
            <h3><i class="fas fa-list text-gold me-2"></i>Tài khoản đã cấp</h3>
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>Khách</th><th>Ngân hàng</th><th>Số TK</th><th>Số dư</th><th>Hết hạn</th></tr></thead>
                    <tbody>
                    <?php if (!$accounts): ?>
                        <tr><td colspan="5" class="text-center text-muted-2 py-4">Chưa có tài khoản nào.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($accounts as $a): ?>
                        <tr>
                            <td class="small"><?= e((string)($a['username'] ?? '—')) ?></td>
                            <td class="small">
                                <?php $alogo = bank_logo((string)$a['bank_type'], 'brand-logo'); ?>
                                <?php if ($alogo !== ''): ?>
                                    <span class="d-inline-flex align-items-center gap-2">
                                        <span class="vbank-avatar vbank-avatar-fallback" style="width:24px;height:24px;flex:0 0 24px;"><?= $alogo ?></span>
                                        <?= e((string)$a['bank_type']) ?>
                                    </span>
                                <?php else: ?>
                                    <?= e((string)$a['bank_type']) ?>
                                <?php endif; ?>
                            </td>
                            <td class="small font-numeric"><?= e((string)($a['bank_number'] ?: '—')) ?></td>
                            <td class="small text-gold"><?= money((float)$a['account_balance']) ?></td>
                            <td class="small text-muted-2"><?= e((string)($a['expired_at'] ?: '—')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
