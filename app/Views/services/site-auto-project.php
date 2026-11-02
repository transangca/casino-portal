<?php
/**
 * Chi tiết dự án tạo website A-Z.
 *
 * @var array $project
 * @var array $pages
 */
$st = (string)$project['status'];
$cls = match ($st) {
    'done'    => 'st-success',
    'failed'  => 'st-danger',
    'building'=> 'st-processing',
    default   => 'st-closed',
};
$label = match ($st) {
    'done'    => 'Hoàn tất',
    'failed'  => 'Thất bại',
    'building'=> 'Đang tạo',
    default   => 'Đang chờ',
};
?>
<div class="container py-4">
    <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
        <span class="brand-icon brand-icon-lg"><i class="fas fa-wand-sparkles"></i></span>
        <div class="flex-fill min-w-0">
            <h1 class="page-title mb-1 text-truncate"><?= e((string)$project['name']) ?></h1>
            <div class="form-hint">
                <?= e((string)($project['domain'] ?: 'Chưa đặt tên miền')) ?>
                · Chủ đề: <?= e((string)$project['category']) ?>
                · Màu: <?= e((string)$project['theme']) ?>
            </div>
        </div>
        <span class="badge-status <?= $cls ?>"><?= $label ?></span>
        <?php if ($st === 'done'): ?>
            <a href="<?= url('/services/site-auto/download/' . (int)$project['id']) ?>" class="btn btn-gold">
                <i class="fas fa-file-zipper me-1"></i>Tải ZIP
            </a>
        <?php endif; ?>
        <form method="POST" action="<?= url('/services/site-auto/delete/' . (int)$project['id']) ?>" class="d-inline">
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
                <div class="stat-value"><?= count($pages) ?></div>
                <div class="stat-label">Trang</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-value"><?= number_format((int)$project['zip_size'] / 1048576, 2) ?> MB</div>
                <div class="stat-label">Dung lượng ZIP</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-value"><?= e((string)$project['theme']) ?></div>
                <div class="stat-label">Giao diện</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-value"><?= e((string)$project['category']) ?></div>
                <div class="stat-label">Chủ đề</div>
            </div>
        </div>
    </div>

    <?php if (!empty($pages)): ?>
        <div class="panel-card mb-4">
            <h6 class="mb-3"><i class="fas fa-file-code me-2"></i>Trang đã tạo (<?= count($pages) ?>)</h6>
            <div class="api-doc-side" style="position:static;max-height:340px">
                <?php foreach ($pages as $pg): ?>
                    <div class="api-side-link d-flex justify-content-between gap-2">
                        <span class="text-truncate font-numeric"><?= e((string)$pg['path']) ?></span>
                        <span class="text-muted-2 flex-shrink-0"><?= number_format((int)$pg['size'] / 1024, 1) ?> KB</span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="alert alert-success small">
        <i class="fas fa-circle-check me-1"></i>
        Giải nén file ZIP và mở <code>index.html</code> bằng trình duyệt — website hiển thị ngay.
        Upload nguyên thư mục lên host là chạy production.
    </div>
</div>
