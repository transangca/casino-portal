<?php
/**
 * Trang quản trị dịch vụ VPS StoreVPS (Virtualizor API).
 *
 * @var array  $stats
 * @var array  $products
 * @var array  $osList
 * @var array  $locations
 * @var array  $orders
 * @var array  $instances
 * @var array  $partner
 * @var string $baseUrl
 * @var bool   $hasCreds
 * @var string $username
 * @var string $app
 * @var bool   $hasSecret
 * @var float  $markup
 * @var bool   $enabled
 * @var bool   $sandbox
 * @var array  $addonTypes
 */
use App\Services\VirtualizorService;

$adminPage = 'vps-storevps';
?>
<h1 class="page-title">Dịch vụ VPS StoreVPS — Virtualizor API</h1>
<p class="page-sub">
    Tích hợp riêng <code>https://api.xvps.vn/api/virt/*</code> (StoreVPS / Virtualizor):
    cấu hình API, đồng bộ gói &amp; OS, theo dõi đơn và quản lý fleet máy chủ ảo của khách.
</p>

<!-- ===== Thẻ thống kê ===== -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-2">
        <div class="panel-card text-center py-3">
            <div class="form-hint">Tổng đơn</div>
            <div class="fs-4 fw-bold"><?= (int)($stats['orders'] ?? 0) ?></div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="panel-card text-center py-3">
            <div class="form-hint">VPS đang chạy</div>
            <div class="fs-4 fw-bold text-success"><?= (int)($stats['active'] ?? 0) ?></div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="panel-card text-center py-3">
            <div class="form-hint">Đơn đang xử lý</div>
            <div class="fs-4 fw-bold text-warning"><?= (int)($stats['pending'] ?? 0) ?></div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="panel-card text-center py-3">
            <div class="form-hint">Gói đang bán</div>
            <div class="fs-4 fw-bold text-info"><?= (int)($stats['products'] ?? 0) ?></div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="panel-card text-center py-3">
            <div class="form-hint">Doanh thu</div>
            <div class="fs-6 fw-bold text-gold"><?= money((float)($stats['revenue'] ?? 0)) ?></div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="panel-card text-center py-3">
            <div class="form-hint">Số dư đối tác</div>
            <div class="fs-6 fw-bold text-gold"><?= money((float)($partner['balance'] ?? 0)) ?></div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- ===== Cột trái: cấu hình ===== -->
    <div class="col-lg-5">
        <div class="panel-card">
            <h3><i class="fas fa-key text-gold me-2"></i>Cấu hình API Virtualizor</h3>

            <form method="POST" action="<?= url('/admin/vps-storevps/save') ?>">
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label class="form-label" for="base_url">Base URL</label>
                    <input type="text" class="form-control font-numeric" id="base_url" name="base_url"
                           value="<?= e($baseUrl) ?>">
                    <div class="form-hint">
                        Bắt buộc HTTPS. Mặc định: https://api.xvps.vn
                        (sandbox: https://api-sandbox.xvps.vn)
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="api_username">api-username</label>
                        <input type="text" class="form-control font-numeric" id="api_username" name="api_username"
                               value="<?= e($username) ?>" placeholder="Tên đăng nhập API được cấp bởi XVPS">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="api_app">api-app</label>
                        <input type="text" class="form-control font-numeric" id="api_app" name="api_app"
                               value="<?= e($app) ?>" placeholder="Mã ứng dụng API">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="api_secret">api-secret</label>
                        <input type="password" class="form-control font-numeric" id="api_secret" name="api_secret"
                               placeholder="<?= $hasSecret ? 'Đã cấu hình — nhập mới để thay đổi' : 'Secret key' ?>">
                        <div class="form-hint">
                            Secret được mã hoá trước khi lưu và chỉ dùng ở server-side.
                        </div>
                    </div>
                </div>

                <div class="mt-3">
                    <label class="form-label" for="markup">Biên lợi nhuận (%)</label>
                    <input type="number" class="form-control font-numeric" id="markup" name="markup"
                           value="<?= (int)$markup ?>" min="0" max="500" step="1">
                    <div class="form-hint">
                        Giá bán = giá bảng giá đối tác × (1 + biên lợi nhuận). Đặt 0 để bán ngang giá đối tác.
                        Dịch vụ StoreVPS không cộng VAT.
                    </div>
                </div>

                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="service_on" name="service_on" value="1"
                        <?= $enabled ? 'checked' : '' ?>>
                    <label class="form-check-label" for="service_on">Bật dịch vụ cho người dùng</label>
                </div>

                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="auto_sync" name="auto_sync" value="1"
                        <?= setting('vps_vz_auto_sync', '1') === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="auto_sync">Tự đồng bộ danh mục (mỗi 10 phút)</label>
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="sandbox" name="sandbox" value="1"
                        <?= $sandbox ? 'checked' : '' ?>>
                    <label class="form-check-label" for="sandbox">
                        Dùng môi trường Sandbox
                        <span class="form-hint d-block">Đổi Base URL sang https://api-sandbox.xvps.vn</span>
                    </label>
                </div>

                <button class="btn btn-primary w-100"><i class="fas fa-save me-2"></i>Lưu cấu hình</button>
            </form>

            <div class="alert alert-<?= $hasCreds ? 'success' : 'warning' ?> mt-3 mb-0 small">
                <i class="fas fa-circle-info me-1"></i>
                Trạng thái: <b><?= $hasCreds ? 'Đã cấu hình đầy đủ' : 'Thiếu thông tin API' ?></b>
                <?php if ($hasCreds): ?>
                    <br><span class="form-hint">
                        JWT token lấy tự động từ <code>POST /api/virt/get-token</code> và cache 7 giờ.
                        Mọi request gửi kèm <code>auth-token</code> + 3 header xác thực.
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <div class="panel-card mt-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h3 class="mb-0"><i class="fas fa-coins text-gold me-2"></i>Số dư ví đối tác</h3>
                <a href="<?= url('/admin/vps-storevps/balance') ?>" class="btn btn-sm btn-outline-gold">
                    <i class="fas fa-rotate"></i>
                </a>
            </div>

            <?php if (!empty($partner['ok'])): ?>
                <div class="convert-result">
                    <i class="fas fa-wallet text-gold fs-3"></i>
                    <div>
                        <div class="small text-muted-2">Số dư khả dụng</div>
                        <div class="fw-bold text-gold" style="font-size:1.4rem">
                            <?= money((float)$partner['balance']) ?>
                        </div>
                        <?php if ((string)($partner['user']['name'] ?? '') !== ''): ?>
                            <div class="form-hint">Tài khoản: <?= e((string)$partner['user']['name']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-danger mb-0 small">
                    <?= e((string)($partner['message'] ?? 'Không đọc được số dư.')) ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="panel-card mt-4">
            <h3><i class="fas fa-arrows-rotate text-gold me-2"></i>Đồng bộ danh mục</h3>
            <p class="form-hint">
                Tải gói VPS, addon, datacenter và hệ điều hành từ
                <code>get-product</code> / <code>get-list-os</code>.
                Gói không còn trên API sẽ tự động bị ẩn khỏi danh mục.
            </p>
            <form method="POST" action="<?= url('/admin/vps-storevps/sync') ?>">
                <?= csrf_field() ?>
                <button class="btn btn-outline-gold w-100"><i class="fas fa-cloud-arrow-down me-2"></i>Đồng bộ ngay</button>
            </form>
        </div>
    </div>

    <!-- ===== Cột phải: dữ liệu ===== -->
    <div class="col-lg-7">
        <!-- Fleet -->
        <div class="panel-card mb-4">
            <h3><i class="fas fa-server text-gold me-2"></i>Fleet VPS của khách</h3>

            <?php if (!$instances): ?>
                <p class="text-muted-2 small mb-0">Chưa có VPS nào được ghi nhận.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>VPS ID</th>
                                <th>Khách</th>
                                <th>IP</th>
                                <th>Trạng thái</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($instances as $v): ?>
                                <tr>
                                    <td class="font-numeric small"><?= e((string)$v['vps_id']) ?></td>
                                    <td class="small"><?= e((string)($v['username'] ?? '')) ?></td>
                                    <td class="font-numeric small"><?= e((string)($v['ip'] ?: '—')) ?></td>
                                    <td>
                                        <span class="badge-status st-<?= e(VirtualizorService::vpsStatusBadge((string)$v['vps_status'])) ?>">
                                            <?= e(VirtualizorService::vpsStatusLabel((string)$v['vps_status'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <?php if (in_array((string)$v['vps_status'], ['running', 'on'], true)): ?>
                                                <form method="POST" action="<?= url('/admin/vps-storevps/instance/action') ?>">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="vps_id" value="<?= e((string)$v['vps_id']) ?>">
                                                    <input type="hidden" name="action" value="restart">
                                                    <button class="btn btn-outline-light" title="Khởi động lại">
                                                        <i class="fas fa-rotate"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" action="<?= url('/admin/vps-storevps/instance/action') ?>">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="vps_id" value="<?= e((string)$v['vps_id']) ?>">
                                                    <input type="hidden" name="action" value="stop">
                                                    <button class="btn btn-outline-danger" title="Tắt máy">
                                                        <i class="fas fa-stop"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" action="<?= url('/admin/vps-storevps/instance/action') ?>">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="vps_id" value="<?= e((string)$v['vps_id']) ?>">
                                                    <input type="hidden" name="action" value="start">
                                                    <button class="btn btn-outline-success" title="Khởi động">
                                                        <i class="fas fa-play"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Đơn hàng -->
        <div class="panel-card mb-4">
            <h3><i class="fas fa-receipt text-gold me-2"></i>Đơn hàng gần đây</h3>

            <?php if (!$orders): ?>
                <p class="text-muted-2 small mb-0">Chưa có đơn nào.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Khách</th>
                                <th>Gói</th>
                                <th>Tiền</th>
                                <th>Trạng thái</th>
                                <th>Thời gian</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $o): ?>
                                <tr>
                                    <td class="font-numeric small"><?= (int)$o['id'] ?></td>
                                    <td class="small"><?= e((string)($o['username'] ?? '')) ?></td>
                                    <td class="small"><?= e((string)($o['product_name'] ?: '—')) ?></td>
                                    <td class="font-numeric small"><?= money((float)$o['amount']) ?></td>
                                    <td>
                                        <span class="badge-status st-<?= e(VirtualizorService::statusBadge((string)$o['status'])) ?>">
                                            <?= e(VirtualizorService::statusLabel((string)$o['status'])) ?>
                                        </span>
                                    </td>
                                    <td class="font-numeric small"><?= e(date('d/m H:i', strtotime((string)$o['created_at']))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Danh mục gói -->
        <div class="panel-card mb-4">
            <h3><i class="fas fa-boxes-stacked text-gold me-2"></i>Danh mục gói VPS</h3>

            <?php if (!$products): ?>
                <p class="text-muted-2 small mb-0">
                    Chưa có gói nào. Bấm <b>Đồng bộ ngay</b> để tải danh mục từ API Virtualizor.
                </p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Tên gói</th>
                                <th>Nhóm</th>
                                <th>Cấu hình</th>
                                <th>Tháng</th>
                                <th>Quý</th>
                                <th>Năm</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $p): ?>
                                <tr>
                                    <td class="small fw-semibold">
                                        <?= e((string)$p['name']) ?>
                                        <?php if ((int)$p['is_addon'] === 1): ?>
                                            <span class="badge-status st-active">Addon</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small"><?= e((string)($p['group_name'] ?: '—')) ?></td>
                                    <td class="font-numeric small">
                                        <?= (int)$p['cpu'] ?>C / <?= (int)$p['ram'] ?>M / <?= (int)$p['disk'] ?>G
                                    </td>
                                    <td class="font-numeric small"><?= money((float)$p['price_monthly']) ?></td>
                                    <td class="font-numeric small"><?= money((float)$p['price_quarterly']) ?></td>
                                    <td class="font-numeric small"><?= money((float)$p['price_annually']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Datacenter -->
        <div class="panel-card mb-4">
            <h3><i class="fas fa-location-dot text-gold me-2"></i>Datacenter</h3>

            <?php if (!$locations): ?>
                <p class="text-muted-2 small mb-0">Chưa đồng bộ datacenter.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Mã</th>
                                <th>Tên</th>
                                <th>Phí cộng thêm</th>
                                <th>Gói</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($locations as $l): ?>
                                <tr>
                                    <td class="font-numeric small"><?= e((string)$l['location_id']) ?></td>
                                    <td class="small"><?= e((string)($l['name'] ?: '—')) ?></td>
                                    <td class="font-numeric small"><?= money((float)$l['surcharge']) ?></td>
                                    <td class="small text-muted-2"><?= e((string)($l['product_id'] ?: '—')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- OS -->
        <div class="panel-card">
            <h3><i class="fab fa-linux text-gold me-2"></i>Hệ điều hành</h3>

            <?php if (!$osList): ?>
                <p class="text-muted-2 small mb-0">Chưa đồng bộ hệ điều hành.</p>
            <?php else: ?>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($osList as $os): ?>
                        <span class="badge-status st-active">
                            <i class="fas fa-microchip me-1"></i><?= e((string)$os['os_name']) ?>
                            <span class="form-hint ms-1">#<?= e((string)$os['os_id']) ?></span>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
