<?php
/**
 * Dịch vụ bán tài khoản & nick.
 *
 * @var array  $products
 * @var array  $categories
 * @var array  $orders
 * @var bool   $configured
 * @var bool   $enabled
 * @var float  $markup
 * @var string $q
 * @var int    $categoryId
 * @var int    $page
 */
?>
<div class="container py-4">

    <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
        <div>
            <h1 class="page-title mb-1"><i class="fas fa-user-secret me-2 text-gold"></i>Tài khoản &amp; Nick</h1>
            <p class="page-sub mb-0">
                Mua tài khoản, nick, clone tài khoản mạng xã hội —
                giao hàng tự động ngay sau khi thanh toán.
            </p>
        </div>
        <div class="ms-auto d-flex gap-2">
            <a href="<?= url('/services/vuavia-accounts/sync') ?>" class="btn btn-outline-gold btn-sm">
                <i class="fas fa-rotate me-1"></i>Làm mới danh mục
            </a>
        </div>
    </div>

    <?php if (!$enabled): ?>
        <div class="alert alert-secondary">
            <i class="fas fa-pause-circle me-1"></i>
            Dịch vụ tài khoản &amp; nick hiện đang tạm dừng. Vui lòng quay lại sau.
        </div>
    <?php elseif (!$configured): ?>
        <div class="alert alert-warning">
            <i class="fas fa-triangle-exclamation me-1"></i>
            Dịch vụ chưa được cấu hình. Vui lòng liên hệ hỗ trợ để được cấp quyền sử dụng.
        </div>
    <?php endif; ?>

    <!-- ===== Bộ lọc ===== -->
    <div class="panel-card mb-3">
        <form method="GET" action="<?= url('/services/vuavia-accounts') ?>" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label" for="q">Tìm kiếm</label>
                <input type="text" class="form-control" id="q" name="q"
                       value="<?= e($q) ?>" placeholder="Tên sản phẩm...">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="category_id">Danh mục</label>
                <select class="form-select" id="category_id" name="category_id">
                    <option value="">Tất cả danh mục</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int)$c['category_id'] ?>"
                            <?= $categoryId === (int)$c['category_id'] ? 'selected' : '' ?>>
                            <?= e((string)$c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <button class="btn btn-gold w-100"><i class="fas fa-filter me-1"></i>Lọc</button>
            </div>
        </form>
    </div>

    <div class="row g-4">
        <!-- ===== Danh sách sản phẩm ===== -->
        <div class="col-lg-8">
            <?php if (!$products): ?>
                <div class="empty-state">
                    <i class="fas fa-box-open fs-1 d-block mb-3"></i>
                    <p class="mb-0">Chưa có sản phẩm nào. Bấm "Làm mới danh mục" để tải.</p>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($products as $p): ?>
                        <?php
                        $out   = (int)$p['stock_count'] <= 0;
                        $cost  = (float)$p['price'];
                        $sell  = $markup > 0 ? (int)ceil($cost * (1 + $markup / 100)) : (int)$cost;
                        $manual = ($p['delivery_type'] ?? 'AUTO') === 'MANUAL';
                        ?>
                        <div class="col-sm-6">
                            <div class="aic-product <?= $out ? 'is-out' : '' ?>">
                                <div class="d-flex align-items-start gap-3">
                                    <span class="aic-icon">
                                        <i class="fas fa-user-secret"></i>
                                    </span>
                                    <div class="min-w-0 flex-fill">
                                        <div class="fw-semibold"><?= e((string)$p['name']) ?></div>
                                        <div class="small text-muted-2">
                                            <?= e((string)($p['category_name'] ?: '—')) ?>
                                            · <?= $manual ? 'Giao thủ công' : 'Giao tự động' ?>
                                        </div>
                                    </div>
                                    <span class="badge-status st-<?= $out ? 'closed' : 'active' ?>">
                                        <?= $out ? 'Hết hàng' : 'Còn ' . (int)$p['stock_count'] ?>
                                    </span>
                                </div>

                                <div class="aic-price-row">
                                    <div>
                                        <span class="aic-label">Giá bán</span>
                                        <b class="text-gold"><?= money($sell) ?></b>
                                    </div>
                                    <div class="ms-auto">
                                        <span class="aic-label">Mã</span>
                                        <b class="small font-numeric"><?= e((string)$p['product_id']) ?></b>
                                    </div>
                                </div>

                                <form method="POST" action="<?= url('/services/vuavia-accounts/purchase') ?>" class="mt-3">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="product_id" value="<?= e((string)$p['product_id']) ?>">
                                    <div class="d-flex gap-2">
                                        <input type="number" name="quantity" class="form-control form-control-sm font-numeric"
                                               value="1" min="1" max="1000" style="max-width:90px" <?= $out ? 'disabled' : '' ?>>
                                        <button class="btn btn-gold btn-sm text-nowrap" <?= $out ? 'disabled' : '' ?>>
                                            <i class="fas fa-bag-shopping me-1"></i>Mua
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($page > 0 || count($products) >= 60): ?>
                    <div class="d-flex justify-content-center gap-2 mt-4">
                        <?php if ($page > 0): ?>
                            <a class="btn btn-outline-light btn-sm"
                               href="<?= url('/services/vuavia-accounts?q=' . urlencode($q) . '&category_id=' . $categoryId . '&page=' . ($page - 1)) ?>">
                                <i class="fas fa-chevron-left me-1"></i>Trước
                            </a>
                        <?php endif; ?>
                        <span class="btn btn-sm btn-outline-gold disabled">Trang <?= $page + 1 ?></span>
                        <?php if (count($products) >= 60): ?>
                            <a class="btn btn-outline-light btn-sm"
                               href="<?= url('/services/vuavia-accounts?q=' . urlencode($q) . '&category_id=' . $categoryId . '&page=' . ($page + 1)) ?>">
                                Sau<i class="fas fa-chevron-right ms-1"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- ===== Lịch sử đơn ===== -->
        <div class="col-lg-4">
            <div class="panel-card">
                <h6 class="mb-3"><i class="fas fa-receipt me-2"></i>Đơn hàng của tôi</h6>

                <?php if (!$orders): ?>
                    <p class="text-muted-2 small mb-0">Chưa có đơn hàng nào.</p>
                <?php else: ?>
                    <div class="aic-order-list">
                        <?php foreach ($orders as $o): ?>
                            <a href="<?= url('/services/vuavia-accounts/order/' . (int)$o['id']) ?>" class="aic-order-item">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fas fa-user-secret text-gold"></i>
                                    <div class="min-w-0 flex-fill">
                                        <div class="small fw-semibold text-truncate">
                                            <?= e((string)($o['product_name'] ?: ('Sản phẩm #' . $o['product_id']))) ?>
                                        </div>
                                        <div class="form-hint">
                                            #<?= (int)$o['id'] ?> · <?= (int)$o['quantity'] ?> sp
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <div class="small text-gold fw-semibold"><?= money((float)$o['total_amount']) ?></div>
                                        <span class="badge-status st-<?= $o['status'] === 'DELIVERED' ? 'active' : ($o['status'] === 'FAILED' ? 'closed' : 'pending') ?>">
                                            <?= $o['status'] === 'DELIVERED' ? 'Đã giao' : ($o['status'] === 'FAILED' ? 'Lỗi' : 'Chờ giao') ?>
                                        </span>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="panel-card mt-4">
                <h6 class="mb-2"><i class="fas fa-shield-halved me-2"></i>Cam kết</h6>
                <ul class="small text-muted-2 mb-0" style="padding-left:1.1rem">
                    <li>Giao hàng tự động ngay sau khi thanh toán thành công.</li>
                    <li>Hoàn tiền 100% nếu hệ thống lỗi khi đặt hàng.</li>
                    <li>Mật khẩu được mã hoá khi lưu, chỉ hiện khi bạn mở đơn.</li>
                    <li>Sau khi giao hàng thủ công, hệ thống sẽ cập nhật trong thời gian ngắn.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
