<?php /** @var array $project,$svg */ ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="page-title" style="font-size:1.4rem"><?= e($project['name']) ?></h1>
        <div class="d-flex gap-2">
            <a href="<?= url('/services/design?category=' . urlencode((string)$project['category'])) ?>" class="btn btn-outline-gold btn-sm">
                <i class="fas fa-arrow-left me-1"></i>Quay lại
            </a>
            <button class="btn btn-gold btn-sm" onclick="downloadSvg()">
                <i class="fas fa-download me-1"></i>Tải SVG
            </button>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="design-preview">
                <?= $svg ?>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="panel-card">
                <h6 class="mb-3"><i class="fas fa-circle-info me-2"></i>Thông tin</h6>
                <table class="table table-sm small mb-0">
                    <tr><td class="text-muted-2">Danh mục</td><td><?= e(\App\Services\DesignService::categoryLabel((string)$project['category'])) ?></td></tr>
                    <tr><td class="text-muted-2">Mẫu</td><td><?= e((string)($project['template'] ?? '—')) ?></td></tr>
                    <tr><td class="text-muted-2">Giá</td><td><?= money((float)$project['price']) ?></td></tr>
                    <tr><td class="text-muted-2">Trạng thái</td><td><?= e((string)$project['status']) ?></td></tr>
                    <tr><td class="text-muted-2">Tạo lúc</td><td><?= e(date('d/m/Y H:i', strtotime((string)$project['created_at']))) ?></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function downloadSvg() {
    const svg = document.querySelector('.design-preview svg');
    if (!svg) return;
    const blob = new Blob([svg.outerHTML], { type: 'image/svg+xml;charset=utf-8' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'design-<?= (int)$project['id'] ?>.svg';
    a.click();
    URL.revokeObjectURL(a.href);
}
</script>
