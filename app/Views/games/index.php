<?php /** @var array $games,$providers,$types,$total,$page,$pages,$q,$providerFilter,$typeFilter,$adsSidebar */ ?>
<div class="container py-4">
    <h1 class="page-title">Kho Game</h1>
    <p class="page-sub">Tổng <?= (int)$total ?> game từ các nhà cung cấp uy tín.</p>

    <div class="row g-4">
        <div class="col-lg-8 col-xl-9">
    <div class="filter-panel">
        <div class="mb-3">
            <span class="game-provider d-block mb-2">Loại</span>
            <a href="<?= url('/games') ?>" class="filter-chip <?= $typeFilter === '' ? 'active' : '' ?>">Tất cả</a>
            <?php foreach ($types as $t): ?>
                <a href="<?= url('/games?type=' . $t['game_type']) ?>" class="filter-chip <?= $typeFilter === $t['game_type'] ? 'active' : '' ?>">
                    <?= e(ucfirst($t['game_type'])) ?> (<?= (int)$t['c'] ?>)
                </a>
            <?php endforeach; ?>
        </div>

        <div class="mb-3">
            <span class="game-provider d-block mb-2">Nhà cung cấp</span>
            <a href="<?= url('/games') ?>" class="filter-chip <?= $providerFilter === '' ? 'active' : '' ?>">Tất cả</a>
            <?php foreach ($providers as $p): ?>
                <a href="<?= url('/games?provider=' . urlencode($p['code'])) ?>" class="filter-chip <?= $providerFilter === $p['code'] ? 'active' : '' ?>">
                    <?= e($p['name']) ?> (<?= (int)$p['game_count'] ?>)
                </a>
            <?php endforeach; ?>
        </div>

        <div>
            <span class="game-provider d-block mb-2">Lọc nạp</span>
            <a href="<?= url('/games?free=1') ?>" class="filter-chip">Miễn phí</a>
            <a href="<?= url('/games?hot=1') ?>" class="filter-chip">Hot</a>
            <a href="<?= url('/games?new=1') ?>" class="filter-chip">Mới</a>
            <a href="<?= url('/games?featured=1') ?>" class="filter-chip">Nổi bật</a>
            <?php if ($q !== '' || $providerFilter !== '' || $typeFilter !== ''): ?>
                <a href="<?= url('/games') ?>" class="filter-chip" style="border-color:#ff6b6b;color:#ff8f9c">✕ Xóa bộ lọc</a>
            <?php endif; ?>
        </div>
    </div>

    <?php include BASE_PATH . '/app/Views/partials/game-grid.php'; ?>

    <?php if ($pages > 1): ?>
    <nav class="mt-5">
        <ul class="pagination justify-content-center">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= url('/games?' . http_build_query(array_merge($_GET, ['page' => $page - 1]))) ?>">«</a>
            </li>
            <?php for ($i = 1; $i <= $pages; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= url('/games?' . http_build_query(array_merge($_GET, ['page' => $i]))) ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= url('/games?' . http_build_query(array_merge($_GET, ['page' => $page + 1]))) ?>">»</a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>
        </div>

        <?php if (!empty($adsSidebar)): ?>
            <aside class="col-lg-4 col-xl-3">
                <div class="ad-slot ad-slot-sidebar">
                    <?php foreach ($adsSidebar as $ad): ?>
                        <?= \App\Services\AdService::render($ad) ?>
                    <?php endforeach; ?>
                </div>
            </aside>
        <?php endif; ?>
    </div>
</div>
