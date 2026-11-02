<?php
/**
 * Chi tiết bài viết.
 *
 * @var array $post
 * @var array $related
 */
?>
<div class="container py-4">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb small" style="--bs-breadcrumb-divider:'›'">
            <li class="breadcrumb-item"><a href="<?= url('/news') ?>">Tin tức</a></li>
            <li class="breadcrumb-item active"><?= e(\App\Services\NewsService::typeLabel((string)$post['type'])) ?></li>
        </ol>
    </nav>

    <div class="row g-4">
        <div class="col-lg-8">
            <article class="panel-card">
                <?php if (!empty($post['banner'])): ?>
                    <img src="<?= e($post['banner']) ?>" alt="<?= e($post['title']) ?>"
                         class="w-100 rounded mb-3" style="max-height:320px;object-fit:cover"
                         onerror="this.onerror=null;this.remove();">
                <?php endif; ?>

                <span class="news-tag"><?= e(\App\Services\NewsService::typeLabel((string)$post['type'])) ?></span>
                <h1 class="h3 fw-bold mt-2 mb-2"><?= e($post['title']) ?></h1>
                <p class="form-hint mb-3">
                    <i class="fas fa-clock me-1"></i><?= e(date('d/m/Y H:i', strtotime($post['published_at']))) ?>
                    <?php if (!empty($post['author_name'])): ?>
                        · <i class="fas fa-user me-1"></i><?= e($post['author_name']) ?>
                    <?php endif; ?>
                </p>

                <div class="news-content" style="line-height:1.8">
                    <?= nl2br(e((string)$post['content'])) ?>
                </div>
            </article>
        </div>

        <div class="col-lg-4">
            <div class="panel-card">
                <h3 class="mb-3" style="font-size:1.05rem"><i class="fas fa-newspaper text-gold me-2"></i>Bài mới nhất</h3>
                <?php foreach ($related as $r): ?>
                    <a href="<?= url('/news/' . $r['slug']) ?>" class="d-block text-decoration-none mb-3 pb-3"
                       style="border-bottom:1px solid var(--casino-line-soft)">
                        <span class="news-tag"><?= e(\App\Services\NewsService::typeLabel((string)$r['type'])) ?></span>
                        <div class="fw-semibold mt-1"><?= e($r['title']) ?></div>
                        <div class="form-hint"><?= e(date('d/m/Y', strtotime($r['published_at']))) ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
