<?php
/**
 * Tổng quan dịch vụ mới (V16).
 *
 * @var array $phone_stats
 * @var array $sms_stats
 * @var int   $clone_count
 * @var int   $build_count
 * @var int   $scrap_count
 * @var int   $scrap_done
 */
$adminPage = 'service-overview';
?>
<h1 class="page-title">Tổng quan dịch vụ mới</h1>
<p class="page-sub">Theo dõi nhanh toàn bộ dịch vụ V16: quét UI/UX, check số điện thoại, copy web, tạo web A-Z và SMS.</p>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-lg-2">
        <div class="stat-card">
            <div class="stat-value"><?= (int)$scrap_count ?></div>
            <div class="stat-label">Lượt quét UI/UX</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="stat-card">
            <div class="stat-value"><?= (int)$scrap_done ?></div>
            <div class="stat-label">Quét hoàn tất</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="stat-card">
            <div class="stat-value"><?= (int)$phone_stats['total'] ?></div>
            <div class="stat-label">Lượt check SĐT</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="stat-card">
            <div class="stat-value"><?= (int)$clone_count ?></div>
            <div class="stat-label">Dự án copy web</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="stat-card">
            <div class="stat-value"><?= (int)$build_count ?></div>
            <div class="stat-label">Website tạo A-Z</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="stat-card">
            <div class="stat-value"><?= (int)$sms_stats['campaigns'] ?></div>
            <div class="stat-label">Chiến dịch SMS</div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="panel-card">
            <h3><i class="fas fa-mobile-screen-button text-gold me-2"></i>Check số điện thoại</h3>
            <div class="info-row">
                <span class="info-label">Tổng lượt tra cứu</span>
                <span class="info-value"><?= (int)$phone_stats['total'] ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Có dữ liệu</span>
                <span class="info-value"><?= (int)$phone_stats['found'] ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Hôm nay</span>
                <span class="info-value"><?= (int)$phone_stats['today'] ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Doanh thu</span>
                <span class="info-value text-gold"><?= money((float)$phone_stats['revenue']) ?></span>
            </div>
            <a href="<?= url('/admin/phone-checks') ?>" class="btn btn-outline-gold btn-sm mt-3">
                <i class="fas fa-arrow-right me-1"></i>Xem nhật ký
            </a>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="panel-card">
            <h3><i class="fas fa-comment-sms text-gold me-2"></i>Chiến dịch SMS</h3>
            <div class="info-row">
                <span class="info-label">Số chiến dịch</span>
                <span class="info-value"><?= (int)$sms_stats['campaigns'] ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Đang chạy</span>
                <span class="info-value"><?= (int)$sms_stats['running'] ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Tin đã gửi</span>
                <span class="info-value"><?= (int)$sms_stats['messages'] ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Doanh thu</span>
                <span class="info-value text-gold"><?= money((float)$sms_stats['revenue']) ?></span>
            </div>
            <a href="<?= url('/admin/sms-campaigns') ?>" class="btn btn-outline-gold btn-sm mt-3">
                <i class="fas fa-arrow-right me-1"></i>Quản lý chiến dịch
            </a>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="panel-card">
            <h3><i class="fas fa-clone text-gold me-2"></i>Dự án website</h3>
            <div class="info-row">
                <span class="info-label">Copy 1-1 trang web</span>
                <span class="info-value"><?= (int)$clone_count ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Tạo website A-Z</span>
                <span class="info-value"><?= (int)$build_count ?></span>
            </div>
            <a href="<?= url('/admin/site-jobs') ?>" class="btn btn-outline-gold btn-sm mt-3">
                <i class="fas fa-arrow-right me-1"></i>Xem dự án
            </a>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="panel-card">
            <h3><i class="fas fa-tags text-gold me-2"></i>Bảng giá dịch vụ</h3>
            <p class="form-hint mb-3">
                Admin quy định giá niêm yết cho từng dịch vụ — khách thấy giá này và bị trừ tự động.
            </p>
            <a href="<?= url('/admin/service-prices') ?>" class="btn btn-outline-gold btn-sm">
                <i class="fas fa-arrow-right me-1"></i>Quản lý bảng giá
            </a>
        </div>
    </div>
</div>
