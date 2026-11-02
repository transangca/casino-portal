<?php
/**
 * Dịch vụ bán tài khoản AI.
 *
 * @var array  $products
 * @var array  $orders
 * @var bool   $configured
 * @var bool   $enabled
 */
$types = [];
foreach ($products as $p) {
    $t = $p['type_label'] ?: ($p['type'] ?: 'Khác');
    $types[$t] = $types[$t] ?? 0;
    $types[$t]++;
}
// Đếm theo slug brand để lưới danh mục AI luôn hiển thị đủ logo
$aiCounts = [];
foreach ($products as $p) {
    $slug = brand_slug((string)($p['type'] ?: ''));
    if ($slug === null) {
        $slug = brand_slug((string)($p['type_label'] ?? ''));
    }
    if ($slug !== null) {
        $aiCounts[$slug] = ($aiCounts[$slug] ?? 0) + 1;
    }
}
$aiBrandGrid = brand_grid_items('ai', $aiCounts);
$markup = (float)setting('genzshop_markup', '0');
$brandSlug = trim((string)($_GET['brand'] ?? ''));
?>
<div class="container py-4">

    <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
        <div>
            <h1 class="page-title mb-1"><i class="fas fa-robot me-2 text-gold"></i>Tài khoản AI</h1>
            <p class="page-sub mb-0">
                Mua tài khoản Cursor, ChatGPT, Claude, Gemini và nhiều công cụ AI khác —
                giao tài khoản tự động ngay sau khi thanh toán.
            </p>
        </div>
        <div class="ms-auto d-flex gap-2">
            <button type="button" class="btn btn-outline-gold btn-sm" id="btnPartnerBalance">
                <i class="fas fa-wallet me-1"></i>Số dư hệ thống
            </button>
            <a href="<?= url('/services/ai-accounts/sync') ?>" class="btn btn-outline-gold btn-sm">
                <i class="fas fa-rotate me-1"></i>Làm mới danh mục
            </a>
        </div>
    </div>

    <?php if (!$enabled): ?>
        <div class="alert alert-secondary">
            <i class="fas fa-pause-circle me-1"></i>
            Dịch vụ tài khoản AI hiện đang tạm dừng. Vui lòng quay lại sau.
        </div>
    <?php elseif (!$configured): ?>
        <div class="alert alert-warning">
            <i class="fas fa-triangle-exclamation me-1"></i>
            Dịch vụ chưa được cấu hình. Vui lòng quay lại sau hoặc liên hệ hỗ trợ.
        </div>
    <?php endif; ?>

    <div id="partnerBalanceBox" class="mb-3" style="display:none">
        <div class="alert alert-info mb-0 d-flex align-items-center gap-2">
            <i class="fas fa-coins text-gold"></i>
            <span>Số dư hệ thống: <b id="partnerBalanceValue">—</b></span>
        </div>
    </div>

    <div class="panel-card mb-3">
        <div class="d-flex align-items-center gap-2 mb-3">
            <i class="fas fa-layer-group text-gold"></i>
            <b class="small">Danh mục AI</b>
            <span class="form-hint ms-auto">Chọn nền tảng để lọc sản phẩm</span>
        </div>
        <div class="brand-grid">
            <?php foreach ($aiBrandGrid as $b): ?>
                <a href="<?= url('/services/ai-accounts?brand=' . urlencode($b['slug'])) ?>"
                   class="brand-card is-brand <?= (($brandSlug ?? '') === $b['slug']) ? 'is-active' : '' ?>">
                    <span class="brand-icon">
                        <?php if ($b['url'] !== ''): ?>
                            <img src="<?= e($b['url']) ?>" alt="<?= e($b['label']) ?>"
                                 loading="lazy" decoding="async">
                        <?php else: ?>
                            <i class="fas fa-robot"></i>
                        <?php endif; ?>
                    </span>
                    <span class="brand-name"><?= e($b['label']) ?></span>
                    <span class="brand-sub"><?= (int)$b['count'] ?> sản phẩm</span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="row g-4">
        <!-- ===== Danh sách sản phẩm ===== -->
        <div class="col-lg-8">
            <?php if (!$products): ?>
                <div class="empty-state">
                    <i class="fas fa-box-open fs-1 d-block mb-3"></i>
                    <p class="mb-0">Chưa có sản phẩm nào. Bấm "Làm mới danh mục" để tải danh mục mới nhất.</p>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($products as $p): ?>
                        <?php
                        $out    = (int)$p['available'] <= 0;
                        $cost   = (float)$p['price'];
                        $sell   = $markup > 0 ? (int)ceil($cost * (1 + $markup / 100)) : (int)$cost;
                        ?>
                        <div class="col-sm-6">
                            <div class="aic-product <?= $out ? 'is-out' : '' ?>">
                                <div class="d-flex align-items-start gap-3">
                                    <span class="aic-icon">
                                        <?php $logo = brand_logo((string)$p['name'], 'ai'); ?>
                                        <?php if ($logo !== ''): ?>
                                            <?= $logo ?>
                                        <?php else: ?>
                                            <i class="<?= e((string)($p['icon'] ?? 'fa-solid fa-box')) ?>"></i>
                                        <?php endif; ?>
                                    </span>
                                    <div class="min-w-0 flex-fill">
                                        <div class="fw-semibold"><?= e((string)$p['name']) ?></div>
                                        <div class="small text-muted-2"><?= e((string)($p['type_label'] ?: $p['type'])) ?></div>
                                        <div class="small text-muted-2 mt-1">
                                            <?= e((string)($p['description'] ?: '—')) ?>
                                        </div>
                                    </div>
                                    <span class="badge-status st-<?= $out ? 'closed' : 'active' ?>">
                                        <?= $out ? 'Hết hàng' : 'Còn ' . (int)$p['available'] ?>
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

                                <form method="POST" action="<?= url('/services/ai-accounts/purchase') ?>" class="mt-3">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="product_id" value="<?= e((string)$p['product_id']) ?>">
                                    <input type="hidden" name="idempotency_key" value="<?= e(\App\Services\AiAccountService::makeIdempotencyKey((int)\App\Core\Auth::id(), (string)$p['product_id'])) ?>">
                                    <div class="d-flex gap-2">
                                        <input type="number" name="quantity" class="form-control form-control-sm font-numeric"
                                               value="1" min="1" max="1000" style="max-width:90px" <?= $out ? 'disabled' : '' ?>>
                                        <input type="email" name="customer_email" class="form-control form-control-sm"
                                               placeholder="Email khách (để đối soát)" <?= $out ? 'disabled' : '' ?>>
                                        <button class="btn btn-gold btn-sm text-nowrap" <?= $out ? 'disabled' : '' ?>>
                                            <i class="fas fa-bag-shopping me-1"></i>Mua
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
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
                            <a href="<?= url('/services/ai-accounts/order/' . (int)$o['id']) ?>" class="aic-order-item">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="<?= e((string)($o['icon'] ?? 'fa-solid fa-box')) ?> text-gold"></i>
                                    <div class="min-w-0 flex-fill">
                                        <div class="small fw-semibold text-truncate">
                                            <?= e((string)($o['product_name'] ?: $o['product_id'])) ?>
                                        </div>
                                        <div class="form-hint">
                                            <?= e((string)($o['order_code'] ?: '#' . $o['id'])) ?>
                                            · <?= (int)$o['quantity'] ?> sp
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <div class="small text-gold fw-semibold"><?= money((float)$o['amount']) ?></div>
                                        <span class="badge-status st-<?= $o['status'] === 'success' ? 'active' : ($o['status'] === 'failed' ? 'closed' : 'pending') ?>">
                                            <?= $o['status'] === 'success' ? 'Thành công' : ($o['status'] === 'failed' ? 'Lỗi' : 'Chờ') ?>
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
                    <li>Giao tài khoản tự động ngay sau khi thanh toán thành công.</li>
                    <li>Hoàn tiền 100% nếu hệ thống không còn hàng.</li>
                    <li>Đơn hàng có mã riêng — bấm mua lại không bị trừ tiền 2 lần.</li>
                    <li>Mật khẩu được mã hoá khi lưu, chỉ hiện khi bạn mở đơn.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('btnPartnerBalance')?.addEventListener('click', function () {
    var box = document.getElementById('partnerBalanceBox');
    var val = document.getElementById('partnerBalanceValue');
    box.style.display = 'block';
    val.textContent = 'đang tải...';

    fetch('<?= url('/services/ai-accounts/partner-balance') ?>', {
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin'
    })
        .then(function (r) { return r.json(); })
        .then(function (j) {
            val.textContent = j.success ? (j.data.balanceText || j.data.balance) : (j.message || 'Lỗi');
        })
        .catch(function () { val.textContent = 'Không tải được'; });
});
</script>
