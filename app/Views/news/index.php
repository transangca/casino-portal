<?php
/**
 * Tin tức / sự kiện / khuyến mãi.
 *
 * @var array  $posts
 * @var array  $pinned
 * @var string $type
 * @var int    $page
 * @var int    $pages
 * @var array  $types
 * @var array  $ads
 */
?>
<div class="container py-4">
    <h1 class="page-title"><i class="fas fa-bullhorn text-gold me-2"></i><?= t('site.news') ?></h1>
    <p class="page-sub">Sự kiện, khuyến mãi và thông báo mới nhất từ hệ thống.</p>

    <!-- Banner quảng cáo -->
    <?php if (!empty($ads)): ?>
        <div class="ad-slot mb-4">
            <?php foreach ($ads as $ad): ?>
                <?= \App\Services\AdService::render($ad) ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Bài ghim -->
    <?php if (!empty($pinned)): ?>
        <div class="mb-4">
            <?php foreach ($pinned as $p): ?>
                <a href="<?= url('/news/' . $p['slug']) ?>" class="news-card mb-2">
                    <?php if (!empty($p['banner'])): ?>
                        <img src="<?= e($p['banner']) ?>" alt="" loading="lazy"
                             onerror="this.onerror=null;this.remove();">
                    <?php endif; ?>
                    <div class="flex-grow-1">
                        <span class="news-tag"><?= e(\App\Services\NewsService::typeLabel((string)$p['type'])) ?></span>
                        <h5 class="mt-1"><?= e($p['title']) ?></h5>
                        <p><?= e(mb_substr((string)($p['excerpt'] ?: $p['content'] ?: ''), 0, 140)) ?></p>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Bộ lọc -->
    <div class="d-flex flex-wrap mb-4">
        <a href="<?= url('/news') ?>" class="filter-chip <?= $type === '' ? 'active' : '' ?>">Tất cả</a>
        <?php foreach ($types as $code => $label): ?>
            <a href="<?= url('/news?type=' . urlencode($code)) ?>"
               class="filter-chip <?= $type === $code ? 'active' : '' ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </div>

    <?php if (!$posts): ?>
        <div class="empty-state">
            <i class="fas fa-newspaper fs-1 d-block mb-3"></i>
            <p class="mb-0">Chưa có bài viết nào.</p>
        </div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($posts as $p): ?>
                <div class="col-md-6 col-lg-4">
                    <a href="<?= url('/news/' . $p['slug']) ?>" class="news-card h-100" style="flex-direction:column">
                        <?php if (!empty($p['banner'])): ?>
                            <img src="<?= e($p['banner']) ?>" alt="" loading="lazy" style="width:100%;height:150px"
                                 onerror="this.onerror=null;this.remove();">
                        <?php endif; ?>
                        <div>
                            <span class="news-tag"><?= e(\App\Services\NewsService::typeLabel((string)$p['type'])) ?></span>
                            <h5 class="mt-2"><?= e($p['title']) ?></h5>
                            <p><?= e(mb_substr((string)($p['excerpt'] ?: $p['content'] ?: ''), 0, 110)) ?></p>
                            <p class="form-hint mb-0">
                                <i class="fas fa-clock me-1"></i><?= e(date('d/m/Y', strtotime($p['published_at']))) ?>
                            </p>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($pages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination">
                    <?php for ($i = 1; $i <= $pages; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="<?= url('/news?type=' . urlencode($type) . '&page=' . $i) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>
