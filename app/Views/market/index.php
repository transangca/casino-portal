<?php
/**
 * Chợ tài nguyên — danh sách sản phẩm.
 *
 * @var array  $items
 * @var int    $total
 * @var int    $page
 * @var int    $pages
 * @var string $category
 * @var string $q
 * @var array  $cats
 */
?>
<div class="container py-4">
    <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
        <div>
            <h1 class="page-title mb-1"><i class="fas fa-store text-gold me-2"></i><?= t('market.title') ?></h1>
            <p class="page-sub mb-0">Mua bán tài khoản, tool và dịch vụ trong cộng đồng thành viên.</p>
        </div>
        <div class="ms-auto d-flex gap-2">
            <a href="<?= url('/market/my') ?>" class="btn btn-outline-gold">
                <i class="fas fa-list-check me-1"></i>Sàn của tôi
            </a>
            <a href="<?= url('/market/sell') ?>" class="btn btn-gold">
                <i class="fas fa-plus me-1"></i><?= t('market.sell') ?>
            </a>
        </div>
    </div>

    <!-- Bộ lọc -->
    <div class="d-flex flex-wrap mb-4">
        <a href="<?= url('/market') ?>" class="filter-chip <?= $category === '' ? 'active' : '' ?>">Tất cả</a>
        <?php foreach ($cats as $code => $label): ?>
            <a href="<?= url('/market?category=' . urlencode($code)) ?>"
               class="filter-chip <?= $category === $code ? 'active' : '' ?>">
                <?= e($label) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <form class="d-flex mb-4" action="<?= url('/market') ?>" method="GET">
        <?php if ($category !== ''): ?>
            <input type="hidden" name="category" value="<?= e($category) ?>">
        <?php endif; ?>
        <div class="input-group" style="max-width:400px">
            <input type="text" name="q" class="form-control" placeholder="Tìm tài nguyên..." value="<?= e($q) ?>">
            <button class="btn btn-gold" type="submit"><i class="fas fa-search"></i></button>
        </div>
    </form>

    <?php if (!$items): ?>
        <div class="empty-state">
            <i class="fas fa-store fs-1 d-block mb-3"></i>
            <p class="mb-0">Chưa có tài nguyên nào được đăng bán.</p>
        </div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($items as $it): ?>
                <div class="col-6 col-md-4 col-lg-3">
                    <div class="market-card h-100">
                        <a href="<?= url('/market/item/' . $it['slug']) ?>" class="m-thumb text-decoration-none">
                            <?php if (!empty($it['image'])): ?>
                                <img src="<?= e($it['image']) ?>" alt="<?= e($it['title']) ?>" loading="lazy"
                                     onerror="this.onerror=null;this.src='<?= asset('img/placeholder.svg') ?>';">
                            <?php else: ?>
                                <img src="<?= asset('img/placeholder.svg') ?>" alt="" loading="lazy">
                            <?php endif; ?>
                        </a>
                        <div class="m-body">
                            <span class="news-tag mb-2" style="display:inline-block"><?= e(\App\Services\MarketService::categoryLabel((string)$it['category'])) ?></span>
                            <h5><?= e($it['title']) ?></h5>
                            <p class="form-hint mb-2">
                                <i class="fas fa-user me-1"></i><?= e($it['seller_name']) ?>
                                <?php if (!empty($it['seller_verified'])): ?>
                                    <i class="fas fa-check-circle text-info ms-1" title="Đã xác minh"></i>
                                <?php endif; ?>
                                <?php if ((int)$it['sold_count'] > 0): ?>
                                    · <i class="fas fa-shopping-bag me-1"></i><?= (int)$it['sold_count'] ?>
                                <?php endif; ?>
                            </p>
                            <div class="m-price"><?= money((float)$it['price']) ?></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($pages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination">
                    <?php for ($i = 1; $i <= $pages; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="<?= url('/market?category=' . urlencode($category) . '&q=' . urlencode($q) . '&page=' . $i) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>
