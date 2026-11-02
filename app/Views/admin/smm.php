<?php
/**
 * Trang quản trị dịch vụ SMM (API đối tác TrungTamMMO).
 *
 * @var array  $orders
 * @var array  $pending
 * @var array  $stats
 * @var array  $balance
 * @var array  $categories
 * @var array  $services
 * @var array  $platforms
 * @var string $baseUrl
 * @var bool   $hasKey
 */
use App\Services\SmmService;

$adminPage = 'smm';
?>
<h1 class="page-title">Dịch vụ SMM — Like, View, Comment, Follow</h1>
<p class="page-sub">
    Cấu hình API key, đồng bộ bảng giá dịch vụ, theo dõi đơn và xử lý các đơn
    chưa tự động hoá được.
</p>

<div class="row g-4">
    <!-- ===== Cột trái: cấu hình ===== -->
    <div class="col-lg-5">
        <div class="panel-card">
            <h3><i class="fas fa-key text-gold me-2"></i>Cấu hình API</h3>

            <form method="POST" action="<?= url('/admin/smm/save') ?>">
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label class="form-label" for="base_url">Base URL</label>
                    <input type="text" class="form-control font-numeric" id="base_url" name="base_url"
                           value="<?= e($baseUrl) ?>">
                    <div class="form-hint">Bắt buộc HTTPS. Mặc định: https://trungtammmo.vn/api/external/smm</div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="api_key">API key (apikey đối tác)</label>
                    <input type="password" class="form-control font-numeric" id="api_key" name="api_key"
                           placeholder="<?= $hasKey ? 'Đã cấu hình — nhập mới để thay đổi' : 'ttmmo_apikey_xxxxxxxx...' ?>">
                    <div class="form-hint">
                        Key được mã hoá AES-256-GCM trước khi lưu. Gửi qua header
                        <code>x-api-key</code> và <code>Authorization: Bearer</code>.
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="markup">Biên lợi nhuận (%)</label>
                    <input type="number" class="form-control font-numeric" id="markup" name="markup"
                           value="<?= (int)setting('smm_markup', '0') ?>" min="0" max="500" step="1">
                    <div class="form-hint">
                        Giá bán = giá bảng giá × (1 + biên lợi nhuận). Đặt 0 để bán ngang giá.
                        Giá bảng giá đã bao gồm giảm giá đại lý (nếu có).
                    </div>
                </div>

                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="service_on" name="service_on" value="1"
                        <?= setting('smm_service_on', '1') === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="service_on">Bật dịch vụ cho người dùng</label>
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="auto_sync" name="auto_sync" value="1"
                        <?= setting('smm_auto_sync', '1') === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="auto_sync">Tự đồng bộ danh mục (mỗi 5 phút)</label>
                </div>

                <button class="btn btn-primary w-100"><i class="fas fa-save me-2"></i>Lưu cấu hình</button>
            </form>

            <div class="alert alert-<?= $hasKey ? 'success' : 'warning' ?> mt-3 mb-0 small">
                <i class="fas fa-circle-info me-1"></i>
                Trạng thái: <b><?= $hasKey ? 'Đã có API key' : 'Chưa có API key' ?></b>
            </div>
        </div>

        <div class="panel-card mt-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h3 class="mb-0"><i class="fas fa-coins text-gold me-2"></i>Số dư ví đối tác</h3>
                <button type="button" class="btn btn-sm btn-outline-gold" id="btnRefreshBalance">
                    <i class="fas fa-rotate"></i>
                </button>
            </div>

            <?php if (!empty($balance['ok'])): ?>
                <div class="convert-result">
                    <i class="fas fa-wallet text-gold fs-3"></i>
                    <div>
                        <div class="small text-muted-2">Số dư khả dụng</div>
                        <div class="fw-bold text-gold" style="font-size:1.4rem" id="balanceValue">
                            <?= money((float)$balance['balance']) ?>
                        </div>
                        <?php if ((string)($balance['user']['username'] ?? '') !== ''): ?>
                            <div class="form-hint">
                                Tài khoản: <?= e((string)$balance['user']['username']) ?>
                                (<?= e((string)($balance['user']['api_status'] ?: '—')) ?>)
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-danger mb-0 small">
                    <?= e((string)($balance['message'] ?? 'Không đọc được số dư.')) ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="panel-card mt-4">
            <h3><i class="fas fa-chart-simple text-gold me-2"></i>Thống kê</h3>
            <div class="row g-3 text-center">
                <div class="col-6">
                    <div class="stat-card">
                        <div class="stat-value"><?= (int)$stats['orders'] ?></div>
                        <div class="stat-label">Tổng đơn</div>
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
                        <div class="stat-value"><?= (int)$stats['processing'] ?></div>
                        <div class="stat-label">Đang xử lý</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="stat-card">
                        <div class="stat-value"><?= (int)$stats['completed'] ?></div>
                        <div class="stat-label">Hoàn thành</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="stat-card">
                        <div class="stat-value"><?= (int)$stats['services'] ?></div>
                        <div class="stat-label">Dịch vụ đang bán</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="stat-card">
                        <div class="stat-value"><?= money((float)$stats['revenue']) ?></div>
                        <div class="stat-label">Doanh thu</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== Cột phải: danh mục & đơn ===== -->
    <div class="col-lg-7">
        <div class="panel-card">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <h3 class="mb-0"><i class="fas fa-list text-gold me-2"></i>Bảng giá dịch vụ</h3>
                <div class="d-flex gap-2">
                    <form method="POST" action="<?= url('/admin/smm/refresh') ?>">
                        <?= csrf_field() ?>
                        <button class="btn btn-sm btn-outline-light"><i class="fas fa-sync me-1"></i>Làm mới đơn</button>
                    </form>
                    <form method="POST" action="<?= url('/admin/smm/sync') ?>">
                        <?= csrf_field() ?>
                        <button class="btn btn-sm btn-outline-gold"><i class="fas fa-rotate me-1"></i>Đồng bộ</button>
                    </form>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mb-3">
                <?php foreach ($categories as $c): ?>
                    <span class="filter-chip">
                        <i class="fas fa-folder me-1"></i><?= e((string)($c['clean_category'] ?: $c['category'])) ?>
                        <b class="ms-1 text-gold"><?= (int)$c['service_count'] ?></b>
                    </span>
                <?php endforeach; ?>
                <?php if (!$categories): ?>
                    <span class="text-muted-2 small">Chưa có danh mục — bấm "Đồng bộ" để tải từ API.</span>
                <?php endif; ?>
            </div>

            <div class="table-responsive" style="max-height:460px;overflow-y:auto">
                <table class="data-table">
                    <thead><tr><th>Dịch vụ</th><th>Nhóm</th><th>Mã</th><th>Giá/1k</th><th>Min/Max</th><th>Loại</th></tr></thead>
                    <tbody>
                    <?php if (!$services): ?>
                        <tr><td colspan="6" class="text-center text-muted-2 py-4">Chưa có dịch vụ nào.</td></tr>
                    <?php endif; ?>
                    <?php foreach (array_slice($services, 0, 400) as $s): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold small"><?= e((string)$s['name']) ?></div>
                                <div class="form-hint text-truncate" style="max-width:280px">
                                    <?= e((string)($s['platform'] ?: '—')) ?>
                                </div>
                            </td>
                            <td class="small"><?= e((string)($s['category_slug'] ?: '—')) ?></td>
                            <td class="small font-numeric"><?= (int)$s['service_id'] ?></td>
                            <td class="small text-gold"><?= money((float)$s['price_per_1k_vnd']) ?></td>
                            <td class="small font-numeric"><?= (int)$s['min'] ?> / <?= (int)$s['max'] ?></td>
                            <td class="small">
                                <?php if ((int)$s['is_comment_service'] === 1): ?>
                                    <span class="badge-status st-active">Comment</span>
                                <?php else: ?>
                                    <span class="text-muted-2"><?= e((string)($s['type'] ?: '—')) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ===== Đơn cần xử lý thủ công ===== -->
        <div class="panel-card mt-4">
            <h3><i class="fas fa-hand-holding text-gold me-2"></i>Đơn đang chạy (<?= count($pending) ?>)</h3>
            <div class="form-hint mb-3">
                Đơn đang ở trạng thái <b>chờ</b> / <b>đang xử lý</b>.
                Admin có thể cập nhật trạng thái thủ công sau khi đối tác báo kết quả.
            </div>

            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>Đơn</th><th>Khách</th><th>Dịch vụ</th><th>Tiến độ</th><th>Thao tác</th></tr></thead>
                    <tbody>
                    <?php if (!$pending): ?>
                        <tr><td colspan="5" class="text-center text-muted-2 py-4">Không có đơn nào đang chạy.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($pending as $o): ?>
                        <tr>
                            <td class="small font-numeric"><?= $o['remote_order'] ? '#' . (int)$o['remote_order'] : '—' ?></td>
                            <td class="small"><?= e((string)($o['username'] ?? '—')) ?></td>
                            <td class="small">
                                <?= e((string)($o['service_name'] ?: '—')) ?>
                                <div class="form-hint text-truncate" style="max-width:220px">
                                    <?= e((string)($o['link'] ?: '')) ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge-status st-<?= e(SmmService::statusBadge((string)$o['status'])) ?>">
                                    <?= e(SmmService::statusLabel((string)$o['status'])) ?>
                                </span>
                            </td>
                            <td>
                                <form method="POST" action="<?= url('/admin/smm/order/update') ?>" class="d-flex gap-1">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                                    <select name="status" class="form-select form-select-sm" style="min-width:130px">
                                        <option value="pending"     <?= $o['status'] === 'pending' ? 'selected' : '' ?>>Chờ</option>
                                        <option value="processing" <?= $o['status'] === 'processing' ? 'selected' : '' ?>>Đang xử lý</option>
                                        <option value="completed"  <?= $o['status'] === 'completed' ? 'selected' : '' ?>>Hoàn thành</option>
                                        <option value="failed"     <?= $o['status'] === 'failed' ? 'selected' : '' ?>>Thất bại</option>
                                    </select>
                                    <button class="btn btn-sm btn-outline-gold"><i class="fas fa-check"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ===== Lịch sử đơn ===== -->
        <div class="panel-card mt-4">
            <h3><i class="fas fa-clock-rotate-left text-gold me-2"></i>Đơn gần đây</h3>
            <div class="table-responsive" style="max-height:460px;overflow-y:auto">
                <table class="data-table">
                    <thead><tr><th>Đơn</th><th>Khách</th><th>Dịch vụ</th><th>SL</th><th>Tiền</th><th>Trạng thái</th></tr></thead>
                    <tbody>
                    <?php if (!$orders): ?>
                        <tr><td colspan="6" class="text-center text-muted-2 py-4">Chưa có đơn nào.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($orders as $o): ?>
                        <tr>
                            <td class="small font-numeric"><?= $o['remote_order'] ? '#' . (int)$o['remote_order'] : '—' ?></td>
                            <td class="small"><?= e((string)($o['username'] ?? '—')) ?></td>
                            <td class="small text-truncate" style="max-width:220px"><?= e((string)($o['service_name'] ?: '—')) ?></td>
                            <td class="small font-numeric"><?= (int)$o['quantity'] ?></td>
                            <td class="small text-gold"><?= money((float)$o['amount']) ?></td>
                            <td>
                                <span class="badge-status st-<?= e(SmmService::statusBadge((string)$o['status'])) ?>">
                                    <?= e(SmmService::statusLabel((string)$o['status'])) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ===== Quản lý API key & giảm giá đại lý ===== -->
        <div class="panel-card mt-4">
            <h3><i class="fas fa-percent text-gold me-2"></i>API key &amp; giảm giá đại lý</h3>
            <p class="form-hint mb-3">
                Admin đặt % giảm cho từng apikey. Giảm giá chỉ áp dụng cho đơn gọi qua API.
                Đơn trên web khách hàng luôn tính đúng giá bán hiển thị.
            </p>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Người dùng</th>
                            <th>API key</th>
                            <th style="width:160px">Giảm (%)</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php $keyRows = $keys ?? []; ?>
                    <?php if (empty($keyRows)): ?>
                        <tr><td colspan="4" class="text-center text-muted-2 py-3">Chưa có API key nào.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($keyRows as $k): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= e((string)$k['username']) ?></div>
                                <div class="form-hint">
                                    Số dư: <?= money((float)$k['balance']) ?>
                                    <?php if ((int)$k['is_active'] !== 1): ?>
                                        · <span class="text-warning">đang tắt</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="font-numeric small">
                                <?= e(mb_substr((string)$k['apikey'], 0, 26)) ?>…
                                <?php if ((string)($k['label'] ?: '') !== ''): ?>
                                    <div class="form-hint"><?= e((string)$k['label']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" action="<?= url('/admin/smm/key/discount') ?>"
                                      class="d-flex gap-2">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="key_id" value="<?= (int)$k['id'] ?>">
                                    <input type="number" name="discount" class="form-control form-control-sm font-numeric"
                                           value="<?= (float)$k['agent_discount_percent'] ?>"
                                           min="0" max="100" step="0.01">
                                    <button class="btn btn-sm btn-primary">Lưu</button>
                                </form>
                            </td>
                            <td></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ===== Tài liệu API ===== -->
        <div class="panel-card mt-4">
            <h3><i class="fas fa-book text-gold me-2"></i>Tài liệu API SMM</h3>
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>Method</th><th>Endpoint</th><th>Mô tả</th></tr></thead>
                    <tbody>
                    <tr><td>GET</td><td><code>/services</code></td><td class="text-secondary">Toàn bộ dịch vụ đang active + giá bán trên web (lọc <code>platform</code>, <code>category</code>, <code>search</code>)</td></tr>
                    <tr><td>GET</td><td><code>/categories</code></td><td class="text-secondary">Card category (lọc <code>platform</code>, <code>search</code>)</td></tr>
                    <tr><td>GET</td><td><code>/category/{slug}</code></td><td class="text-secondary">Dịch vụ thuộc một nhóm</td></tr>
                    <tr><td>POST</td><td><code>/quote</code></td><td class="text-secondary">Tính giá (không tạo đơn, không trừ tiền)</td></tr>
                    <tr><td>POST</td><td><code>/order</code></td><td class="text-secondary">Tạo đơn thật + trừ ví chính</td></tr>
                    <tr><td>GET</td><td><code>/status?order=</code></td><td class="text-secondary">Trạng thái &amp; tiến độ 1 đơn</td></tr>
                    <tr><td>GET</td><td><code>/status?orders=</code></td><td class="text-secondary">Trạng thái nhiều đơn (tối đa 100 mã)</td></tr>
                    <tr><td>GET</td><td><code>/orders?service_ids=</code></td><td class="text-secondary">Lịch sử đơn (<code>limit</code>, <code>service_ids</code>)</td></tr>
                    <tr><td>GET</td><td><code>/balance</code></td><td class="text-secondary">Số dư ví chính</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="alert alert-info small mt-3 mb-0">
                <i class="fas fa-circle-info me-1"></i>
                Xác thực bằng <code>x-api-key</code> (hoặc <code>Authorization: Bearer</code>).
                Giá = <code>ceil(quantity / 1000 × price_per_1k_vnd)</code>, <b>không cộng VAT</b>.
                Dịch vụ comment tự lấy quantity theo số dòng comment hợp lệ.
                User thường chỉ xem được đơn của chính mình; admin được kiểm tra nhiều đơn để vận hành.
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('btnRefreshBalance')?.addEventListener('click', function () {
    var el = document.getElementById('balanceValue');
    if (!el) return;
    el.textContent = 'đang tải...';
    fetch('<?= url('/admin/smm/partner-balance') ?>', {
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin'
    })
        .then(function (r) { return r.json(); })
        .then(function (j) {
            el.textContent = j.success ? (j.data.text || j.data.balance) : (j.message || 'Lỗi');
        })
        .catch(function () { el.textContent = 'Không tải được'; });
});
</script>
