<?php
/**
 * Trang quản trị dịch vụ thuê số OTP (API ViOTP v1).
 *
 * @var array  $services
 * @var array  $rentals
 * @var array  $stats
 * @var array  $balance
 * @var string $baseUrl
 * @var bool   $hasToken
 */
$adminPage = 'otp-rental';
?>
<h1 class="page-title">Dịch vụ thuê số nhận OTP (ViOTP API)</h1>
<p class="page-sub">
    Cấu hình token ViOTP, theo dõi số dư và quản lý các lượt thuê số.
</p>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="panel-card">
            <h3><i class="fas fa-key text-gold me-2"></i>Cấu hình API</h3>

            <form method="POST" action="<?= url('/admin/otp-rental/save') ?>">
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label class="form-label" for="base_url">Base URL</label>
                    <input type="text" class="form-control font-numeric" id="base_url" name="base_url"
                           value="<?= e($baseUrl) ?>">
                    <div class="form-hint">Bắt buộc HTTPS. Mặc định: https://api.viotp.net</div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="token">API Token</label>
                    <input type="password" class="form-control font-numeric" id="token" name="token"
                           placeholder="<?= $hasToken ? 'Đã cấu hình — nhập mới để thay đổi' : 'Dán token ViOTP của bạn' ?>">
                    <div class="form-hint">
                        Token được mã hoá AES-256-GCM trước khi lưu. Gửi qua header
                        <code>Authorization: Bearer {token}</code>.
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="markup">Biên lợi nhuận (%)</label>
                    <input type="number" class="form-control font-numeric" id="markup" name="markup"
                           value="<?= (int)setting('viotp_markup', '0') ?>" min="0" max="500" step="1">
                    <div class="form-hint">Giá bán = giá đối tác × (1 + biên lợi nhuận). Đặt 0 để bán ngang giá.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="wait_minutes">Thời gian chờ tối đa (phút)</label>
                    <input type="number" class="form-control font-numeric" id="wait_minutes" name="wait_minutes"
                           value="<?= (int)setting('viotp_wait_minutes', '7') ?>" min="1" max="30">
                    <div class="form-hint">Thường là 4–7 phút. Dùng để hiển thị đếm ngược cho khách.</div>
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="service_on" name="service_on" value="1"
                        <?= setting('viotp_service_on', '1') === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="service_on">Bật dịch vụ cho người dùng</label>
                </div>

                <button class="btn btn-primary w-100"><i class="fas fa-save me-2"></i>Lưu cấu hình</button>
            </form>

            <div class="alert alert-<?= $hasToken ? 'success' : 'warning' ?> mt-3 mb-0 small">
                <i class="fas fa-circle-info me-1"></i>
                Trạng thái: <b><?= $hasToken ? 'Đã có token' : 'Chưa có token' ?></b>
            </div>
        </div>

        <div class="panel-card mt-4">
            <h3><i class="fas fa-coins text-gold me-2"></i>Số dư tài khoản ViOTP</h3>
            <?php if (!empty($balance['ok'])): ?>
                <div class="convert-result">
                    <i class="fas fa-wallet text-gold fs-3"></i>
                    <div>
                        <div class="small text-muted-2">Số dư khả dụng</div>
                        <div class="fw-bold text-gold" style="font-size:1.4rem"><?= money((float)$balance['balance']) ?></div>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-danger mb-0 small">
                    <b><?= e((string)($balance['code'] ?? '')) ?></b> — <?= e((string)($balance['message'] ?? '')) ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="panel-card mt-4">
            <h3><i class="fas fa-chart-simple text-gold me-2"></i>Thống kê</h3>
            <div class="row g-3 text-center">
                <div class="col-6">
                    <div class="stat-card">
                        <div class="stat-value"><?= (int)$stats['rentals'] ?></div>
                        <div class="stat-label">Lượt thuê</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="stat-card">
                        <div class="stat-value"><?= (int)$stats['completed'] ?></div>
                        <div class="stat-label">Đã có mã</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="stat-card">
                        <div class="stat-value"><?= (int)$stats['pending'] ?></div>
                        <div class="stat-label">Đang chờ</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="stat-card">
                        <div class="stat-value"><?= (int)$stats['expired'] ?></div>
                        <div class="stat-label">Hết hạn</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="panel-card">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h3 class="mb-0"><i class="fas fa-list text-gold me-2"></i>Danh mục dịch vụ</h3>
                <form method="POST" action="<?= url('/admin/otp-rental/sync') ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-outline-gold"><i class="fas fa-rotate me-1"></i>Đồng bộ</button>
                </form>
            </div>

            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>Dịch vụ</th><th>ID</th><th>Giá</th><th>Cập nhật</th></tr></thead>
                    <tbody>
                    <?php if (!$services): ?>
                        <tr><td colspan="4" class="text-center text-muted-2 py-4">Chưa có dịch vụ. Bấm "Đồng bộ" để tải từ API.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($services as $s): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold small d-flex align-items-center gap-2">
                                    <i class="<?= e((string)($s['icon'] ?? 'fa-solid fa-comment-sms')) ?> text-gold"></i>
                                    <?= e((string)$s['name']) ?>
                                </div>
                            </td>
                            <td class="small font-numeric"><?= (int)$s['service_id'] ?></td>
                            <td class="small text-gold"><?= money((float)$s['price']) ?></td>
                            <td class="text-end small text-muted-2"><?= e((string)$s['updated_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel-card mt-4">
            <h3><i class="fas fa-clock-rotate-left text-gold me-2"></i>Lượt thuê gần đây</h3>
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>Số</th><th>Khách</th><th>Dịch vụ</th><th>Mã</th><th>Tiền</th><th>Trạng thái</th></tr></thead>
                    <tbody>
                    <?php if (!$rentals): ?>
                        <tr><td colspan="6" class="text-center text-muted-2 py-4">Chưa có lượt thuê nào.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rentals as $r): ?>
                        <tr>
                            <td class="small font-numeric"><?= e((string)($r['phone'] ?: '—')) ?></td>
                            <td class="small"><?= e((string)($r['username'] ?? '—')) ?></td>
                            <td class="small"><?= e((string)($r['service_name'] ?: '—')) ?></td>
                            <td class="small font-numeric text-gold"><?= e((string)($r['otp_code'] ?: '—')) ?></td>
                            <td class="small text-gold"><?= money((float)$r['price']) ?></td>
                            <td>
                                <span class="badge-status st-<?= (int)$r['status'] === 1 ? 'active' : ((int)$r['status'] === 2 ? 'closed' : 'pending') ?>">
                                    <?= (int)$r['status'] === 1 ? 'Có mã' : ((int)$r['status'] === 2 ? 'Hết hạn' : 'Chờ') ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel-card mt-4">
            <h3><i class="fas fa-book text-gold me-2"></i>Tài liệu API ViOTP v1</h3>
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>Method</th><th>Endpoint</th><th>Mô tả</th></tr></thead>
                    <tbody>
                    <tr><td>GET</td><td><code>/v1/balance</code></td><td class="text-secondary">Số dư tài khoản (VND)</td></tr>
                    <tr><td>GET</td><td><code>/v1/carriers</code></td><td class="text-secondary">Danh sách nhà mạng</td></tr>
                    <tr><td>GET</td><td><code>/v1/services</code></td><td class="text-secondary">Danh sách dịch vụ (id, name, price)</td></tr>
                    <tr><td>POST</td><td><code>/v1/rentals</code></td><td class="text-secondary">Thuê số mới</td></tr>
                    <tr><td>POST</td><td><code>/v1/rentals/rerent</code></td><td class="text-secondary">Thuê lại số cũ (15–30 phút)</td></tr>
                    <tr><td>GET</td><td><code>/v1/rentals/{id}</code></td><td class="text-secondary">Trạng thái &amp; mã OTP (poll 5s)</td></tr>
                    <tr><td>GET</td><td><code>/v1/rentals</code></td><td class="text-secondary">Lịch sử thuê (5 query param bắt buộc)</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="alert alert-info small mt-3 mb-0">
                <i class="fas fa-circle-info me-1"></i>
                <b>status</b>: <code>0</code> = đang chờ mã, <code>1</code> = đã nhận được mã
                (nằm ở trường <code>code</code>), <code>2</code> = hết thời gian chờ (tiền tự hoàn).
                Lỗi trả về theo chuẩn <code>application/problem+json</code> với trường <code>code</code> và <code>detail</code>.
            </div>
        </div>
    </div>
</div>
