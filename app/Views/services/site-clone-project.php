<?php
/**
 * Chi tiết dự án copy 1-1.
 *
 * @var array $project
 * @var array $meta
 */
$st = (string)$project['status'];
$cls = match ($st) {
    'done'    => 'st-success',
    'failed'  => 'st-danger',
    'scanning'=> 'st-processing',
    default   => 'st-closed',
};
$label = match ($st) {
    'done'    => 'Hoàn tất',
    'failed'  => 'Thất bại',
    'scanning'=> 'Đang sao chép',
    default   => 'Đang chờ',
};
?>
<div class="container py-4">
    <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
        <span class="brand-icon brand-icon-lg"><i class="fas fa-clone"></i></span>
        <div class="flex-fill min-w-0">
            <h1 class="page-title mb-1 text-truncate"><?= e((string)($project['title'] ?: $project['host'])) ?></h1>
            <a href="<?= e((string)$project['source_url']) ?>" target="_blank" rel="noopener" class="form-hint">
                <?= e((string)$project['source_url']) ?>
            </a>
        </div>
        <span class="badge-status <?= $cls ?>"><?= $label ?></span>
        <?php if ($st === 'done'): ?>
            <a href="<?= url('/services/site-clone/download/' . (int)$project['id']) ?>" class="btn btn-gold">
                <i class="fas fa-file-zipper me-1"></i>Tải ZIP
            </a>
        <?php endif; ?>
        <form method="POST" action="<?= url('/services/site-clone/delete/' . (int)$project['id']) ?>" class="d-inline">
            <?= csrf_field() ?>
            <button class="btn btn-outline-light btn-sm"><i class="fas fa-trash"></i></button>
        </form>
    </div>

    <?php if ($st === 'failed' && !empty($project['error_msg'])): ?>
        <div class="alert alert-danger small">
            <i class="fas fa-circle-exclamation me-1"></i><?= e((string)$project['error_msg']) ?>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-value"><?= (int)$project['pages_total'] ?></div>
                <div class="stat-label">Trang</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-value"><?= (int)$project['pages_done'] ?></div>
                <div class="stat-label">Đã clone</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-value"><?= (int)$project['assets_total'] ?></div>
                <div class="stat-label">Tài nguyên</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-value"><?= number_format((int)$project['zip_size'] / 1048576, 2) ?> MB</div>
                <div class="stat-label">Dung lượng ZIP</div>
            </div>
        </div>
    </div>

    <?php if (!empty($meta['pages'])): ?>
        <div class="panel-card mb-4">
            <h6 class="mb-3"><i class="fas fa-file-code me-2"></i>Các trang đã clone (<?= count($meta['pages']) ?>)</h6>
            <div class="api-doc-side" style="position:static;max-height:340px">
                <?php foreach ($meta['pages'] as $pg): ?>
                    <div class="api-side-link d-flex justify-content-between gap-2">
                        <span class="text-truncate font-numeric"><?= e((string)$pg['path']) ?></span>
                        <span class="text-muted-2 flex-shrink-0"><?= number_format((int)$pg['size'] / 1024, 1) ?> KB</span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($meta['assets'])): ?>
        <div class="panel-card">
            <h6 class="mb-3"><i class="fas fa-box-open me-2"></i>Tài nguyên đã tải (<?= count($meta['assets']) ?>)</h6>
            <div class="api-doc-side" style="position:static;max-height:340px">
                <?php foreach (array_slice($meta['assets'], 0, 200) as $a): ?>
                    <div class="api-side-link d-flex justify-content-between gap-2">
                        <span class="text-truncate"><?= e((string)$a['file']) ?></span>
                        <span class="text-muted-2 flex-shrink-0"><?= number_format((int)$a['size'] / 1024, 1) ?> KB</span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
