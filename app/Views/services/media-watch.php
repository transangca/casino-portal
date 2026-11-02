<?php /** @var array $item,$embed */ ?>
<div class="container py-4">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= url('/services/media') ?>">Xem phim</a></li>
            <li class="breadcrumb-item active"><?= e($item['title']) ?></li>
        </ol>
    </nav>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="media-player">
                <?php if ($embed !== ''): ?>
                    <iframe src="<?= e($embed) ?>" title="<?= e($item['title']) ?>" allowfullscreen allow="autoplay; fullscreen; encrypted-media; picture-in-picture"></iframe>
                <?php else: ?>
                    <video src="<?= e(media_url((string)$item['video_url'])) ?>" controls preload="metadata"
                           <?php if (!empty($item['backdrop'])): ?>poster="<?= e(media_url((string)$item['backdrop'])) ?>"<?php endif; ?>></video>
                <?php endif; ?>
            </div>

            <h1 class="page-title mt-3" style="font-size:1.4rem"><?= e($item['title']) ?></h1>
            <div class="small text-muted-2 mb-3">
                <?= $item['year'] ? e((string)$item['year']) : '' ?>
                <?php if (!empty($item['duration'])): ?> · <?= e((string)$item['duration']) ?><?php endif; ?>
                · <?= (int)$item['views'] ?> lượt xem
            </div>
            <p class="small"><?= e((string)($item['description'] ?? '')) ?></p>
        </div>

        <div class="col-lg-4">
            <div class="panel-card">
                <h6 class="mb-3"><i class="fas fa-info-circle me-2"></i>Thông tin</h6>
                <table class="table table-sm small mb-0">
                    <tr><td class="text-muted-2">Thể loại</td><td><?= e(\App\Services\MediaService::typeLabel((string)$item['type'])) ?></td></tr>
                    <tr><td class="text-muted-2">Năm</td><td><?= e((string)($item['year'] ?? '—')) ?></td></tr>
                    <tr><td class="text-muted-2">Đánh giá</td><td><?= e((string)($item['rating'] ?? '—')) ?></td></tr>
                    <tr><td class="text-muted-2">Thời lượng</td><td><?= e((string)($item['duration'] ?? '—')) ?></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>
