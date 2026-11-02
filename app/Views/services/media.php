<?php /** @var array $items,$total,$page,$pages,$type,$q,$types,$genres,$genre */ ?>
<div class="container py-4">
    <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
        <div>
            <h1 class="page-title mb-1"><i class="fas fa-film me-2 text-gold"></i>Xem phim</h1>
            <p class="page-sub mb-0">Kho phim chiến thức, phim bộ và trailer — cập nhật tự động liên tục.</p>
        </div>
        <a href="<?= url('/services/media/sync') ?>" class="btn btn-outline-gold btn-sm ms-auto">
            <i class="fas fa-rotate me-1"></i>Làm mới danh mục
        </a>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-3">
        <a href="<?= url('/services/media') ?>" class="filter-chip <?= $type === '' ? 'active' : '' ?>">Tất cả</a>
        <?php foreach ($types as $code => $label): ?>
            <a href="<?= url('/services/media?type=' . urlencode($code)) ?>" class="filter-chip <?= $type === $code ? 'active' : '' ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </div>

    <?php if ($genres): ?>
        <div class="d-flex flex-wrap gap-2 mb-3">
            <?php foreach ($genres as $gid => $g): ?>
                <a href="<?= url('/services/media?genre=' . urlencode($gid)) ?>"
                   class="filter-chip <?= $genre === (string)$gid ? 'active' : '' ?>">
                    <?= e($g['label']) ?> <small class="opacity-75">(<?= (int)$g['count'] ?>)</small>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form class="mb-4" action="<?= url('/services/media') ?>" method="GET">
        <div class="input-group" style="max-width:420px">
            <input type="text" name="q" class="form-control" placeholder="Tìm phim..." value="<?= e($q) ?>">
            <button class="btn btn-gold" type="submit"><i class="fas fa-search"></i></button>
        </div>
    </form>

    <?php if (!$items): ?>
        <div class="empty-state">
            <i class="fas fa-film fs-1 d-block mb-3"></i>
            <p class="mb-0">Chưa có nội dung phim. Bấm "Làm mới danh mục" để tải về.</p>
        </div>
    <?php else: ?>
        <div class="media-grid">
            <?php foreach ($items as $m): ?>
                <a href="<?= url('/services/media/watch/' . $m['slug']) ?>" class="media-card">
                    <div class="media-poster">
                        <?php if (!empty($m['poster'])): ?>
                            <img src="<?= e(media_url((string)$m['poster'])) ?>" alt="<?= e($m['title']) ?>" loading="lazy">
                        <?php endif; ?>
                        <span class="media-badge"><?= e($types[$m['type']] ?? $m['type']) ?></span>
                        <?php if (!empty($m['is_vip'])): ?>
                            <span class="media-vip"><i class="fas fa-crown"></i> VIP</span>
                        <?php endif; ?>
                    </div>
                    <div class="media-info">
                        <h3><?= e($m['title']) ?></h3>
                        <div class="small text-muted-2">
                            <?= $m['year'] ? e((string)$m['year']) : '' ?>
                            <?php if (!empty($m['rating'])): ?> · <i class="fas fa-star text-gold"></i> <?= e((string)$m['rating']) ?><?php endif; ?>
                            · <?= (int)$m['views'] ?> lượt xem
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($pages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $pages; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="<?= url('/services/media?' . http_build_query(array_merge($_GET, ['page' => $i]))) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>
