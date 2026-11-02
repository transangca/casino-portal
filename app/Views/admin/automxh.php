<?php
/**
 * Trang quản trị dịch vụ Auto MXH (API đối tác TrungTamMMO).
 *
 * @var array  $orders
 * @var array  $pending
 * @var array  $stats
 * @var array  $balance
 * @var array  $categories
 * @var array  $variants
 * @var string $baseUrl
 * @var bool   $hasKey
 */
use App\Services\AutoMxhService;

$adminPage = 'automxh';
?>
<h1 class="page-title">Dịch vụ Auto MXH — Mở khóa tài khoản</h1>
<p class="page-sub">
    Cấu hình API key, đồng bộ danh mục gói, theo dõi đơn và xử lý thủ công các gói
    không tự động hoá được.
</p>

<div class="row g-4">
    <!-- ===== Cột trái: cấu hình ===== -->
    <div class="col-lg-5">
        <div class="panel-card">
            <h3><i class="fas fa-key text-gold me-2"></i>Cấu hình API</h3>

            <form method="POST" action="<?= url('/admin/automxh/save') ?>">
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label class="form-label" for="base_url">Base URL</label>
                    <input type="text" class="form-control font-numeric" id="base_url" name="base_url"
                           value="<?= e($baseUrl) ?>">
                    <div class="form-hint">Bắt buộc HTTPS. Mặc định: https://trungtammmo.vn/api/external/automxh</div>
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
                           value="<?= (int)setting('automxh_markup', '0') ?>" min="0" max="500" step="1">
                    <div class="form-hint">
                        Giá bán = giá đối tác × (1 + biên lợi nhuận). Đặt 0 để bán ngang giá.
                        Giá đối tác đã bao gồm giảm giá đại lý (nếu có).
                    </div>
                </div>

                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="service_on" name="service_on" value="1"
                        <?= setting('automxh_service_on', '1') === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="service_on">Bật dịch vụ cho người dùng</label>
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="auto_sync" name="auto_sync" value="1"
                        <?= setting('automxh_auto_sync', '1') === '1' ? 'checked' : '' ?>>
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
                        <div class="stat-value"><?= (int)$stats['variants'] ?></div>
                        <div class="stat-label">Gói đang bán</div>
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
                <h3 class="mb-0"><i class="fas fa-list text-gold me-2"></i>Danh mục gói</h3>
                <div class="d-flex gap-2">
                    <form method="POST" action="<?= url('/admin/automxh/refresh') ?>">
                        <?= csrf_field() ?>
                        <button class="btn btn-sm btn-outline-light"><i class="fas fa-sync me-1"></i>Làm mới đơn</button>
                    </form>
                    <form method="POST" action="<?= url('/admin/automxh/sync') ?>">
                        <?= csrf_field() ?>
                        <button class="btn btn-sm btn-outline-gold"><i class="fas fa-rotate me-1"></i>Đồng bộ</button>
                    </form>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mb-3">
                <?php foreach ($categories as $c): ?>
                    <span class="filter-chip">
                        <i class="fas fa-folder me-1"></i><?= e((string)$c['name']) ?>
                        <b class="ms-1 text-gold"><?= count(\App\Services\AutoMxhService::localVariants((int)$c['category_id'])) ?></b>
                    </span>
                <?php endforeach; ?>
                <?php if (!$categories): ?>
                    <span class="text-muted-2 small">Chưa có danh mục — bấm "Đồng bộ" để tải từ API.</span>
                <?php endif; ?>
            </div>

            <div class="table-responsive" style="max-height:420px;overflow-y:auto">
                <table class="data-table">
                    <thead><tr><th>Gói</th><th>Nhóm</th><th>Variant</th><th>Giá</th><th>Giảm</th></tr></thead>
                    <tbody>
                    <?php if (!$variants): ?>
                        <tr><td colspan="5" class="text-center text-muted-2 py-4">Chưa có gói nào.</td></tr>
                    <?php endif; ?>
                    <?php foreach (array_slice($variants, 0, 300) as $v):
                        $disc = (float)($v['agent_discount_percent'] ?? 0);
                        ?>
                        <tr>
                            <td>
                                <div class="fw-semibold small"><?= e((string)$v['name']) ?></div>
                                <div class="form-hint text-truncate" style="max-width:280px">
                                    <?= e((string)($v['product_name'] ?: '—')) ?>
                                </div>
                            </td>
                            <td class="small"><?= e((string)($v['category_slug'] ?: '—')) ?></td>
                            <td class="small font-numeric"><?= (int)$v['variant_id'] ?></td>
                            <td class="small text-gold"><?= money((float)$v['price']) ?></td>
                            <td class="small">
                                <?php if ($disc > 0): ?>
                                    <span class="badge-status st-active">-<?= number_format($disc, 0) ?>%</span>
                                <?php else: ?>
                                    <span class="text-muted-2">—</span>
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
            <h3><i class="fas fa-hand-holding text-gold me-2"></i>Đơn cần xử lý (<?= count($pending) ?>)</h3>
            <div class="form-hint mb-3">
                Các gói chưa tự động hoá được sẽ dừng ở trạng thái <b>đang chờ</b>.
                Admin cập nhật trạng thái thủ công sau khi xử lý xong.
            </div>

            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>Đơn</th><th>Khách</th><th>Gói</th><th>Tiến độ</th><th>Thao tác</th></tr></thead>
                    <tbody>
                    <?php if (!$pending): ?>
                        <tr><td colspan="5" class="text-center text-muted-2 py-4">Không có đơn nào cần xử lý.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($pending as $o): ?>
                        <tr>
                            <td class="small font-numeric"><?= $o['remote_order'] ? '#' . (int)$o['remote_order'] : '—' ?></td>
                            <td class="small"><?= e((string)($o['username'] ?? '—')) ?></td>
                            <td class="small">
                                <?= e((string)($o['variant_name'] ?: '—')) ?>
                                <div class="form-hint text-truncate" style="max-width:220px">
                                    <?= e((string)($o['link'] ?: '')) ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge-status st-<?= e(AutoMxhService::statusBadge((string)$o['status'])) ?>">
                                    <?= e(AutoMxhService::statusLabel((string)$o['status'])) ?>
                                </span>
                            </td>
                            <td>
                                <form method="POST" action="<?= url('/admin/automxh/order/update') ?>" class="d-flex gap-1">
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
                    <thead><tr><th>Đơn</th><th>Khách</th><th>Gói</th><th>Tiền</th><th>Trạng thái</th></tr></thead>
                    <tbody>
                    <?php if (!$orders): ?>
                        <tr><td colspan="5" class="text-center text-muted-2 py-4">Chưa có đơn nào.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($orders as $o): ?>
                        <tr>
                            <td class="small font-numeric"><?= $o['remote_order'] ? '#' . (int)$o['remote_order'] : '—' ?></td>
                            <td class="small"><?= e((string)($o['username'] ?? '—')) ?></td>
                            <td class="small"><?= e((string)($o['variant_name'] ?: '—')) ?></td>
                            <td class="small text-gold"><?= money((float)$o['amount']) ?></td>
                            <td>
                                <span class="badge-status st-<?= e(AutoMxhService::statusBadge((string)$o['status'])) ?>">
                                    <?= e(AutoMxhService::statusLabel((string)$o['status'])) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ===== Tài liệu API ===== -->
        <div class="panel-card mt-4">
            <h3><i class="fas fa-book text-gold me-2"></i>Tài liệu API Auto MXH</h3>
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>Method</th><th>Endpoint</th><th>Mô tả</th></tr></thead>
                    <tbody>
                    <tr><td>GET</td><td><code>/services</code></td><td class="text-secondary">Toàn bộ gói đang active + giá (lọc <code>category</code>, <code>search</code>)</td></tr>
                    <tr><td>GET</td><td><code>/catalog</code></td><td class="text-secondary">Cây category / product</td></tr>
                    <tr><td>GET</td><td><code>/category/{slug}</code></td><td class="text-secondary">Product kèm đủ variant con</td></tr>
                    <tr><td>POST</td><td><code>/quote</code></td><td class="text-secondary">Tính giá (không tạo đơn, không trừ tiền)</td></tr>
                    <tr><td>POST</td><td><code>/order</code></td><td class="text-secondary">Tạo đơn thật + trừ ví chính</td></tr>
                    <tr><td>GET</td><td><code>/status?order=</code></td><td class="text-secondary">Trạng thái &amp; tiến độ 1 đơn</td></tr>
                    <tr><td>GET</td><td><code>/orders</code></td><td class="text-secondary">Lịch sử đơn (<code>limit</code>, <code>product_ids</code>)</td></tr>
                    <tr><td>GET</td><td><code>/balance</code></td><td class="text-secondary">Số dư ví chính</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="alert alert-info small mt-3 mb-0">
                <i class="fas fa-circle-info me-1"></i>
                Xác thực bằng <code>x-api-key</code> (hoặc <code>Authorization: Bearer</code>).
                Giá = <code>variant.price</code>, <b>không cộng VAT</b>.
                Không nhập quantity tự do — quantity là thuộc tính của gói.
                Tài khoản đại lý được giảm %: response có thêm <code>list_price</code> và
                <code>agent_discount_percent</code>.
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('btnRefreshBalance')?.addEventListener('click', function () {
    var el = document.getElementById('balanceValue');
    if (!el) return;
    el.textContent = 'đang tải...';
    fetch('<?= url('/admin/automxh/partner-balance') ?>', {
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
