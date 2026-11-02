<?php
/**
 * Trang quản trị dịch vụ tài khoản AI (API đối tác GenzShop).
 *
 * @var array  $products
 * @var array  $orders
 * @var array  $stats
 * @var array  $balance
 * @var string $baseUrl
 * @var bool   $hasKey
 */
$adminPage = 'ai-accounts';
?>
<h1 class="page-title">Dịch vụ tài khoản AI (GenzShop Partner API)</h1>
<p class="page-sub">
    Cấu hình API key đối tác, theo dõi số dư ví và quản lý đơn giao tài khoản tự động.
</p>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="panel-card">
            <h3><i class="fas fa-key text-gold me-2"></i>Cấu hình API</h3>

            <form method="POST" action="<?= url('/admin/ai-accounts/save') ?>">
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label class="form-label" for="base_url">Base URL</label>
                    <input type="text" class="form-control font-numeric" id="base_url" name="base_url"
                           value="<?= e($baseUrl) ?>">
                    <div class="form-hint">
                        Bắt buộc HTTPS. Endpoint phải có đuôi <code>.php</code>.
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="api_key">API Key</label>
                    <input type="password" class="form-control font-numeric" id="api_key" name="api_key"
                           placeholder="<?= $hasKey ? 'Đã cấu hình — nhập mới để thay đổi' : 'Dán API key của bạn' ?>">
                    <div class="form-hint">
                        Liên hệ admin GenzShop để được cấp key. Được mã hoá AES-256-GCM trước khi lưu.
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="markup">Biên lợi nhuận (%)</label>
                    <input type="number" class="form-control font-numeric" id="markup" name="markup"
                           value="<?= (int)setting('genzshop_markup', '0') ?>" min="0" max="500" step="1">
                    <div class="form-hint">
                        Giá bán cho khách = giá đại lý × (1 + biên lợi nhuận). Đặt 0 để bán ngang giá.
                    </div>
                </div>

                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="service_on" name="service_on" value="1"
                        <?= setting('genzshop_service_on', '1') === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="service_on">Bật dịch vụ cho người dùng</label>
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="auto_sync" name="auto_sync" value="1"
                        <?= setting('genzshop_auto_sync', '1') === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="auto_sync">Tự đồng bộ danh mục sản phẩm mỗi 5 phút</label>
                </div>

                <button class="btn btn-primary w-100"><i class="fas fa-save me-2"></i>Lưu cấu hình</button>
            </form>

            <div class="alert alert-<?= $hasKey ? 'success' : 'warning' ?> mt-3 mb-0 small">
                <i class="fas fa-circle-info me-1"></i>
                Trạng thái: <b><?= $hasKey ? 'Đã có API key' : 'Chưa có API key' ?></b>
            </div>
        </div>

        <div class="panel-card mt-4">
            <h3><i class="fas fa-coins text-gold me-2"></i>Số dư ví đối tác</h3>
            <?php if (!empty($balance['ok'])): ?>
                <div class="convert-result">
                    <i class="fas fa-wallet text-gold fs-3"></i>
                    <div>
                        <div class="small text-muted-2">Số dư khả dụng</div>
                        <div class="fw-bold text-gold" style="font-size:1.4rem">
                            <?= e((string)($balance['balanceText'] ?: money((float)$balance['balance']))) ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-danger mb-0 small">
                    <b><?= e((string)($balance['errorCode'] ?? '')) ?></b> — <?= e((string)($balance['message'] ?? '')) ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="panel-card mt-4">
            <h3><i class="fas fa-chart-simple text-gold me-2"></i>Thống kê</h3>
            <div class="row g-3 text-center">
                <div class="col-6">
                    <div class="stat-card">
                        <div class="stat-value"><?= (int)$stats['orders'] ?></div>
                        <div class="stat-label">Đơn hàng</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="stat-card">
                        <div class="stat-value"><?= (int)$stats['delivered'] ?></div>
                        <div class="stat-label">Tài khoản giao</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="stat-card">
                        <div class="stat-value"><?= number_format((float)$stats['revenue'], 0, ',', '.') ?></div>
                        <div class="stat-label">Doanh thu (đ)</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="stat-card">
                        <div class="stat-value"><?= (int)$stats['products'] ?></div>
                        <div class="stat-label">Sản phẩm</div>
                    </div>
                </div>
            </div>
            <?php if ((int)$stats['out_stock'] > 0): ?>
                <div class="alert alert-warning small mt-3 mb-0">
                    <i class="fas fa-triangle-exclamation me-1"></i>
                    Có <b><?= (int)$stats['out_stock'] ?></b> sản phẩm đang hết hàng.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="panel-card">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h3 class="mb-0"><i class="fas fa-box text-gold me-2"></i>Danh mục sản phẩm</h3>
                <form method="POST" action="<?= url('/admin/ai-accounts/sync') ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-outline-gold"><i class="fas fa-rotate me-1"></i>Đồng bộ</button>
                </form>
            </div>

            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>Sản phẩm</th><th>Mã</th><th>Giá</th><th>Tồn</th><th></th></tr></thead>
                    <tbody>
                    <?php if (!$products): ?>
                        <tr><td colspan="5" class="text-center text-muted-2 py-4">Chưa có sản phẩm. Bấm "Đồng bộ" để tải từ API.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($products as $p): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold small"><?= e((string)$p['name']) ?></div>
                                <div class="form-hint"><?= e((string)($p['type_label'] ?: $p['type'])) ?></div>
                            </td>
                            <td class="small font-numeric"><?= e((string)$p['product_id']) ?></td>
                            <td class="small text-gold"><?= money((float)$p['price']) ?></td>
                            <td>
                                <span class="badge-status st-<?= (int)$p['available'] > 0 ? 'active' : 'closed' ?>">
                                    <?= (int)$p['available'] ?>
                                </span>
                            </td>
                            <td class="text-end small text-muted-2"><?= e((string)$p['updated_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel-card mt-4">
            <h3><i class="fas fa-receipt text-gold me-2"></i>Đơn hàng gần đây</h3>
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>Mã</th><th>Khách</th><th>Sản phẩm</th><th>SL</th><th>Tiền</th><th>Trạng thái</th></tr></thead>
                    <tbody>
                    <?php if (!$orders): ?>
                        <tr><td colspan="6" class="text-center text-muted-2 py-4">Chưa có đơn hàng.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($orders as $o): ?>
                        <tr>
                            <td class="small font-numeric"><?= e((string)($o['order_code'] ?: '#' . $o['id'])) ?></td>
                            <td class="small"><?= e((string)($o['username'] ?? '—')) ?></td>
                            <td class="small"><?= e((string)($o['product_name'] ?: $o['product_id'])) ?></td>
                            <td class="small"><?= (int)$o['quantity'] ?></td>
                            <td class="small text-gold"><?= money((float)$o['amount']) ?></td>
                            <td>
                                <span class="badge-status st-<?= $o['status'] === 'success' ? 'active' : ($o['status'] === 'failed' ? 'closed' : 'pending') ?>">
                                    <?= $o['status'] === 'success' ? 'OK' : ($o['status'] === 'failed' ? 'Lỗi' : 'Chờ') ?>
                                </span>
                                <?php if ((int)$o['replay'] === 1): ?>
                                    <span class="badge-status st-pending">Replay</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel-card mt-4">
            <h3><i class="fas fa-book text-gold me-2"></i>Tài liệu API đối tác</h3>
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>Method</th><th>Endpoint</th><th>Mô tả</th></tr></thead>
                    <tbody>
                    <tr><td>GET</td><td><code>/products.php</code></td><td class="text-secondary">Danh sách sản phẩm + giá đại lý + tồn kho</td></tr>
                    <tr><td>GET</td><td><code>/balance.php</code></td><td class="text-secondary">Số dư ví của tài khoản gắn với key</td></tr>
                    <tr><td>POST</td><td><code>/purchase.php</code></td><td class="text-secondary">Mua hàng, trả về deliveredAccounts</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="alert alert-info small mt-3 mb-0">
                <i class="fas fa-circle-info me-1"></i>
                Mỗi đơn dùng một <code>idempotency_key</code> riêng. Gửi lại cùng key → API trả về đơn cũ
                kèm <code>replay: true</code> và <b>không</b> trừ tiền lần hai.
            </div>
        </div>
    </div>
</div>
