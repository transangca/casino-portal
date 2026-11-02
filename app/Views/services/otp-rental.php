<?php
/**
 * Dịch vụ thuê số nhận OTP.
 *
 * @var array $catalog  ['items','total','page','perPage','pages','keyword']
 * @var array $popular
 * @var array $rentals
 * @var array $carriers
 * @var bool  $configured
 * @var bool  $enabled
 */
$markup = (float)setting('viotp_markup', '0');
$items  = $catalog['items'] ?? [];
$pages  = (int)($catalog['pages'] ?? 1);
$page   = (int)($catalog['page'] ?? 1);
$total  = (int)($catalog['total'] ?? 0);
$kw     = (string)($catalog['keyword'] ?? '');
?>
<div class="container py-4">

    <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
        <div>
            <h1 class="page-title mb-1"><i class="fas fa-comment-sms me-2 text-gold"></i>Thuê số nhận OTP</h1>
            <p class="page-sub mb-0">
                Thuê số điện thoại tạm để nhận mã xác thực (Facebook, Momo, Zalo, Shopee, TikTok...)
                — giao mã OTP tự động, không cần cài ứng dụng.
            </p>
        </div>
        <div class="ms-auto">
            <button type="button" class="btn btn-outline-gold btn-sm" id="btnPartnerBalance">
                <i class="fas fa-wallet me-1"></i>Số dư hệ thống
            </button>
        </div>
    </div>

    <div id="partnerBalanceBox" class="mb-3" style="display:none">
        <div class="alert alert-info mb-0 d-flex align-items-center gap-2">
            <i class="fas fa-coins text-gold"></i>
            <span>Số dư hệ thống: <b id="partnerBalanceValue">—</b></span>
        </div>
    </div>

    <?php if (!$enabled): ?>
        <div class="alert alert-secondary">
            <i class="fas fa-pause-circle me-1"></i>
            Dịch vụ thuê số OTP hiện đang tạm dừng. Vui lòng quay lại sau.
        </div>
    <?php elseif (!$configured): ?>
        <div class="alert alert-warning">
            <i class="fas fa-triangle-exclamation me-1"></i>
            Dịch vụ chưa được cấu hình token. Vui lòng liên hệ hỗ trợ để được cấp quyền sử dụng.
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- ===== Danh sách dịch vụ ===== -->
        <div class="col-lg-8">
            <!-- Ô tìm kiếm -->
            <form method="GET" action="<?= url('/services/otp-rental') ?>" class="mb-3">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-magnifying-glass"></i></span>
                    <input type="text" name="q" class="form-control" value="<?= e($kw) ?>"
                           placeholder="Tìm dịch vụ (Facebook, Momo, Shopee, Zalo...)">
                    <button class="btn btn-outline-gold">Tìm</button>
                    <?php if ($kw !== ''): ?>
                        <a href="<?= url('/services/otp-rental') ?>" class="btn btn-outline-light">Xoá</a>
                    <?php endif; ?>
                </div>
            </form>

            <?php if ($kw === '' && $popular): ?>
                <div class="mb-4">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="fas fa-fire text-gold"></i>
                        <b class="small">Dịch vụ phổ biến</b>
                    </div>
                    <div class="row g-2">
                        <?php foreach ($popular as $s): ?>
                            <?php $sell = $markup > 0 ? (int)ceil((float)$s['price'] * (1 + $markup / 100)) : (int)$s['price']; ?>
                            <?php $logo = brand_logo((string)$s['name'], 'sim'); ?>
                            <div class="col-6 col-md-4 col-xl-3">
                                <a href="<?= url('/services/otp-rental?q=' . urlencode((string)$s['name'])) ?>"
                                   class="otp-pill">
                                    <span class="otp-pill-logo">
                                        <?php if ($logo !== ''): ?>
                                            <?= $logo ?>
                                        <?php else: ?>
                                            <i class="<?= e((string)($s['icon'] ?? 'fa-solid fa-comment-sms')) ?>"></i>
                                        <?php endif; ?>
                                    </span>
                                    <span class="text-truncate"><?= e((string)$s['name']) ?></span>
                                    <b class="text-gold"><?= money($sell) ?></b>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Danh mục ứng dụng có logo chuẩn -->
            <?php if ($kw === ''): ?>
                <?php
                // Đếm số dịch vụ theo tên ứng dụng (khớp brand nào thì cộng vào brand đó)
                $appCats = [];
                foreach ($items as $s) {
                    $nm = (string)$s['name'];
                    $slug = brand_slug($nm);
                    if ($slug !== null) {
                        $appCats[$slug] = ($appCats[$slug] ?? 0) + 1;
                        continue;
                    }
                    $key = preg_match('/^([A-Za-zÀ-ỹ0-9]+)/u', $nm, $m) ? $m[1] : $nm;
                    $appCats[$key] = ($appCats[$key] ?? 0) + 1;
                }
                $brandGrid = brand_grid_items('sim', $appCats);
                ?>
                <div class="panel-card mb-4">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <i class="fas fa-layer-group text-gold"></i>
                        <b class="small">Danh mục ứng dụng</b>
                        <span class="form-hint ms-auto">Chọn ứng dụng để lọc dịch vụ tương ứng</span>
                    </div>
                    <div class="brand-grid">
                        <?php foreach ($brandGrid as $b): ?>
                            <a href="<?= url('/services/otp-rental?q=' . urlencode($b['label'])) ?>"
                               class="brand-card is-brand">
                                <span class="brand-icon">
                                    <?php if ($b['url'] !== ''): ?>
                                        <img src="<?= e($b['url']) ?>" alt="<?= e($b['label']) ?>"
                                             loading="lazy" decoding="async">
                                    <?php else: ?>
                                        <i class="fas fa-comment-sms"></i>
                                    <?php endif; ?>
                                </span>
                                <span class="brand-name"><?= e($b['label']) ?></span>
                                <span class="brand-sub"><?= (int)$b['count'] ?> dịch vụ</span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="d-flex align-items-center justify-content-between mb-2">
                <b class="small">
                    <?= $kw !== '' ? 'Kết quả cho "' . e($kw) . '"' : 'Tất cả dịch vụ' ?>
                    <span class="text-muted-2">(<?= $total ?>)</span>
                </b>
            </div>

            <?php if (!$items): ?>
                <div class="empty-state">
                    <i class="fas fa-box-open fs-1 d-block mb-3"></i>
                    <p class="mb-0">
                        <?= $kw !== '' ? 'Không tìm thấy dịch vụ phù hợp.' : 'Chưa có dịch vụ nào. Vui lòng quay lại sau.' ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($items as $s): ?>
                        <?php
                        $cost   = (float)$s['price'];
                        $sell   = $markup > 0 ? (int)ceil($cost * (1 + $markup / 100)) : (int)$cost;
                        ?>
                        <div class="col-sm-6 col-lg-4">
                            <div class="otp-service">
                                <span class="otp-icon">
                                    <?php $logo = brand_logo((string)$s['name'], 'sim'); ?>
                                    <?php if ($logo !== ''): ?>
                                        <?= $logo ?>
                                    <?php else: ?>
                                        <i class="<?= e((string)($s['icon'] ?? 'fa-solid fa-comment-sms')) ?>"></i>
                                    <?php endif; ?>
                                </span>
                                <div class="fw-semibold mt-2 small" style="min-height:2.4em"><?= e((string)$s['name']) ?></div>
                                <div class="small text-muted-2">Mã dịch vụ: <?= (int)$s['id'] ?></div>
                                <div class="otp-price">
                                    <span class="aic-label">Giá bán</span>
                                    <b class="text-gold"><?= money($sell) ?></b>
                                </div>

                                <form method="POST" action="<?= url('/services/otp-rental/rent') ?>" class="mt-2">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="service_id" value="<?= (int)$s['id'] ?>">
                                    <button class="btn btn-gold btn-sm w-100" <?= (!$enabled || !$configured) ? 'disabled' : '' ?>>
                                        <i class="fas fa-mobile-screen me-1"></i>Thuê số
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($pages > 1): ?>
                    <nav class="mt-4 d-flex justify-content-center">
                        <ul class="pagination pagination-sm mb-0">
                            <?php
                            $q = $kw !== '' ? '&q=' . urlencode($kw) : '';
                            $start = max(1, $page - 2);
                            $end   = min($pages, $page + 2);
                            ?>
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= url('/services/otp-rental?page=' . max(1, $page - 1) . $q) ?>">&laquo;</a>
                            </li>
                            <?php if ($start > 1): ?>
                                <li class="page-item"><a class="page-link" href="<?= url('/services/otp-rental?page=1' . $q) ?>">1</a></li>
                                <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                            <?php endif; ?>
                            <?php for ($i = $start; $i <= $end; $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= url('/services/otp-rental?page=' . $i . $q) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            <?php if ($end < $pages): ?>
                                <?php if ($end < $pages - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                                <li class="page-item"><a class="page-link" href="<?= url('/services/otp-rental?page=' . $pages . $q) ?>"><?= $pages ?></a></li>
                            <?php endif; ?>
                            <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= url('/services/otp-rental?page=' . min($pages, $page + 1) . $q) ?>">&raquo;</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($carriers): ?>
                <div class="panel-card mt-4">
                    <h6 class="mb-2"><i class="fas fa-tower-broadcast me-2"></i>Nhà mạng hỗ trợ</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($carriers as $c): ?>
                            <span class="filter-chip"><?= e((string)$c['label']) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <div class="form-hint mt-2">
                        Mặc định hệ thống lấy số từ tất cả nhà mạng. Bạn có thể chọn nhà mạng cụ thể
                        và đầu số muốn lấy khi mở form thuê.
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- ===== Lịch sử ===== -->
        <div class="col-lg-4">
            <div class="panel-card">
                <h6 class="mb-3"><i class="fas fa-clock-rotate-left me-2"></i>Lượt thuê của tôi</h6>

                <?php if (!$rentals): ?>
                    <p class="text-muted-2 small mb-0">Chưa có lượt thuê nào.</p>
                <?php else: ?>
                    <div class="aic-order-list">
                        <?php foreach ($rentals as $r): ?>
                            <a href="<?= url('/services/otp-rental/rental/' . (int)$r['id']) ?>" class="aic-order-item">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fas fa-mobile-screen text-gold"></i>
                                    <div class="min-w-0 flex-fill">
                                        <div class="small fw-semibold text-truncate">
                                            <?= e((string)($r['service_name'] ?: 'Dịch vụ')) ?>
                                        </div>
                                        <div class="form-hint font-numeric">
                                            <?= e((string)($r['phone'] ?: '—')) ?>
                                            · #<?= (int)$r['rental_id'] ?>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <div class="small text-gold fw-semibold"><?= money((float)$r['price']) ?></div>
                                        <span class="badge-status st-<?= (int)$r['status'] === 1 ? 'active' : ((int)$r['status'] === 2 ? 'closed' : 'pending') ?>">
                                            <?= (int)$r['status'] === 1 ? 'Có mã' : ((int)$r['status'] === 2 ? 'Hết hạn' : 'Đang chờ') ?>
                                        </span>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="panel-card mt-4">
                <h6 class="mb-2"><i class="fas fa-circle-info me-2"></i>Hướng dẫn</h6>
                <ol class="small text-muted-2 mb-0" style="padding-left:1.1rem">
                    <li>Tìm dịch vụ cần dùng (Facebook, Momo, Zalo...) và bấm "Thuê số".</li>
                    <li>Hệ thống thuê số và chuyển bạn sang trang chờ mã.</li>
                    <li>Trang tự động làm mới mỗi 5 giây, mã hiện ngay khi có.</li>
                    <li>Nếu hết 4–7 phút không có mã, hệ thống sẽ tự hoàn tiền cho bạn.</li>
                    <li>Bạn có thể "Thuê lại" số cũ trong vòng 15–30 phút.</li>
                </ol>
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

    fetch('<?= url('/services/otp-rental/partner-balance') ?>', {
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin'
    })
        .then(function (r) { return r.json(); })
        .then(function (j) {
            val.textContent = j.success ? (j.data.text || j.data.balance) : (j.message || 'Lỗi');
        })
        .catch(function () { val.textContent = 'Không tải được'; });
});
</script>
