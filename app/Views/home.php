<?php
/**
 * Trang chủ.
 *
 * @var array $featured,$hot,$new,$providers,$stats,$adsTop,$adsMid,$adsSidebar
 * @var array $slides
 * @var array $serviceCategories
 */
?>

<!-- ================= CAROUSEL QUẢNG CÁO / GIỚI THIỆU ================= -->
<?php if (!empty($slides)): ?>
<section class="home-carousel-wrap">
    <div class="container">
        <div id="homeCarousel" class="carousel slide promo-carousel" data-bs-ride="carousel" data-bs-interval="6000">
            <div class="carousel-indicators">
                <?php foreach ($slides as $i => $s): ?>
                    <button type="button"
                            data-bs-target="#homeCarousel"
                            data-bs-slide-to="<?= $i ?>"
                            class="<?= $i === 0 ? 'active' : '' ?>"
                            aria-label="Slide <?= $i + 1 ?>"
                            <?= $i === 0 ? 'aria-current="true"' : '' ?>></button>
                <?php endforeach; ?>
            </div>

            <div class="carousel-inner">
                <?php foreach ($slides as $i => $s): ?>
                    <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
                        <?php if ($s['type'] === 'ad'): ?>
                            <?= \App\Services\AdService::render($s['ad']) ?>
                        <?php else: ?>
                            <div class="promo-slide">
                                <div class="promo-copy">
                                    <span class="promo-badge">
                                        <i class="fas fa-star me-1"></i>Giới thiệu
                                    </span>
                                    <h2 class="promo-title"><?= e((string)$s['title']) ?></h2>
                                    <p class="promo-text"><?= e((string)$s['desc']) ?></p>
                                    <a href="<?= e((string)$s['link']) ?>" class="btn btn-gold">
                                        <i class="fas fa-play me-1"></i>Xem ngay
                                    </a>
                                </div>
                                <div class="promo-media">
                                    <?php if (!empty($s['image'])): ?>
                                        <img src="<?= e((string)$s['image']) ?>" alt="<?= e((string)$s['title']) ?>" loading="lazy">
                                    <?php else: ?>
                                        <i class="fas fa-crown fa-3x text-gold"></i>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (count($slides) > 1): ?>
                <button class="carousel-control-prev" type="button" data-bs-target="#homeCarousel" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Trước</span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#homeCarousel" data-bs-slide="next">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Sau</span>
                </button>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ================= HERO (đã gỡ) ================= -->

<!-- ================= PGSOFT SHOWCASE ================= -->
<?php include BASE_PATH . '/app/Views/partials/pgsoft-showcase.php'; ?>

<!-- ================= DENO CASINO SHOWCASE ================= -->
<?php include BASE_PATH . '/app/Views/partials/deno-showcase.php'; ?>

<div class="container py-4">

    <!-- ================= DỊCH VỤ THEO DANH MỤC ================= -->
    <section class="mb-5">
        <div class="section-head">
            <h2><i class="fas fa-layer-group"></i>Dịch vụ của hệ thống</h2>
            <span class="form-hint">Chọn danh mục → bấm vào dịch vụ để xem chi tiết</span>
        </div>

        <ul class="svc-tabs" role="tablist">
            <?php foreach ($serviceCategories as $ci => $cat): ?>
                <li class="nav-item" role="presentation">
                    <button class="svc-tab<?= $ci === 0 ? ' active' : '' ?>"
                            data-bs-toggle="pill"
                            data-bs-target="#cat<?= (int)$ci ?>"
                            type="button" role="tab"
                            aria-controls="cat<?= (int)$ci ?>"
                            aria-selected="<?= $ci === 0 ? 'true' : 'false' ?>">
                        <i class="fas <?= e((string)$cat['icon']) ?>"></i>
                        <?= e((string)$cat['title']) ?>
                        <span class="text-muted-2">(<?= count($cat['items']) ?>)</span>
                    </button>
                </li>
            <?php endforeach; ?>
        </ul>

        <div class="tab-content">
            <?php foreach ($serviceCategories as $ci => $cat): ?>
                <div class="tab-pane fade<?= $ci === 0 ? ' show active' : '' ?>" id="cat<?= (int)$ci ?>" role="tabpanel">
                    <div class="row g-3">
                        <?php foreach ($cat['items'] as $item): ?>
                            <div class="col-sm-6 col-lg-4 col-xl-3">
                                <a href="<?= url((string)$item['url']) ?>" class="svc-card">
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="brand-icon">
                                            <i class="fas <?= e((string)$item['icon']) ?>"></i>
                                        </span>
                                        <div class="min-w-0 flex-fill">
                                            <div class="svc-card-title text-truncate"><?= e((string)$item['name']) ?></div>
                                            <div class="svc-card-desc"><?= e((string)$item['desc']) ?></div>
                                        </div>
                                    </div>
                                    <div class="svc-card-meta">
                                        <span>Xem chi tiết</span>
                                        <span class="text-gold"><i class="fas fa-arrow-right"></i></span>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ================= NHÀ PHÁT HÀNH (đã gỡ) ================= -->

    <?php if ($featured): ?>
    <div class="mb-5">
        <div class="section-head">
            <h2><i class="fas fa-star"></i>Game nổi bật</h2>
            <a href="<?= url('/games?featured=1') ?>" class="btn btn-sm btn-outline-gold">Xem tất cả →</a>
        </div>
        <?php $gameList = $featured; include BASE_PATH . '/app/Views/partials/game-grid.php'; ?>
    </div>
    <?php endif; ?>

    <?php if ($hot): ?>
    <div class="mb-5">
        <div class="section-head">
            <h2><i class="fas fa-fire"></i>Game hot</h2>
            <a href="<?= url('/games?hot=1') ?>" class="btn btn-sm btn-outline-gold">Xem tất cả →</a>
        </div>
        <?php $gameList = $hot; include BASE_PATH . '/app/Views/partials/game-grid.php'; ?>
    </div>
    <?php endif; ?>

    <?php if ($new): ?>
    <div class="mb-5">
        <div class="section-head">
            <h2><i class="fas fa-sparkles"></i>Mới nhất</h2>
            <a href="<?= url('/games?new=1') ?>" class="btn btn-sm btn-outline-gold">Xem tất cả →</a>
        </div>
        <?php $gameList = $new; include BASE_PATH . '/app/Views/partials/game-grid.php'; ?>
    </div>
    <?php endif; ?>

    <div class="panel-card">
        <div class="row align-items-center g-4">
            <div class="col-lg-8">
                <h3 class="mb-2"><i class="fas fa-bolt text-gold me-2"></i>Ví đồng bộ hai chiều</h3>
                <p class="text-muted-2 small mb-0">
                    Mọi giao dịch nạp tiền trên website đều được phản ánh tức thì trong game.
                    Ngược lại, tiền thắng cược cũng được ghi nhận về ví và lịch sử giao dịch của bạn.
                    Hệ thống dùng cơ chế Operator API với token phiên bảo mật và heartbeat tự động.
                </p>
            </div>
            <div class="col-lg-4 text-lg-end">
                <a href="<?= url('/wallet/deposit') ?>" class="btn btn-play">
                    <i class="fas fa-plus me-2"></i>Nạp tiền ngay
                </a>
            </div>
        </div>
    </div>

</div>
