<?php
/**
 * Trang chi tiết job quét UI/UX (tiến trình + tải ZIP).
 *
 * @var array $job
 * @var array $manifest
 */
$st  = (string)$job['status'];
$cnt = json_decode((string)($job['counts'] ?? ''), true);
$cnt = is_array($cnt) ? $cnt : [];
?>
<div class="container py-4">
    <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
        <span class="brand-icon brand-icon-lg"><i class="fas fa-globe"></i></span>
        <div class="flex-fill min-w-0">
            <h1 class="page-title mb-1 text-truncate"><?= e((string)$job['host']) ?></h1>
            <a href="<?= e((string)$job['url']) ?>" target="_blank" rel="noopener" class="form-hint">
                <?= e((string)$job['url']) ?>
            </a>
        </div>

        <?php if ($st === 'done'): ?>
            <form method="POST" action="<?= url('/services/ui-scraper/download/' . (int)$job['id']) ?>" class="d-inline">
                <?= csrf_field() ?>
                <button class="btn btn-gold"><i class="fas fa-file-zipper me-1"></i>Tải ZIP tài nguyên</button>
            </form>
        <?php elseif ($st === 'running' || $st === 'queued'): ?>
            <form method="POST" action="<?= url('/services/ui-scraper/start/' . (int)$job['id']) ?>" class="d-inline">
                <?= csrf_field() ?>
                <button class="btn btn-outline-gold"><i class="fas fa-play me-1"></i>Chạy nền</button>
            </form>
        <?php else: ?>
            <form method="POST" action="<?= url('/services/ui-scraper/run/' . (int)$job['id']) ?>" class="d-inline">
                <?= csrf_field() ?>
                <button class="btn btn-gold"><i class="fas fa-rotate me-1"></i>Quét lại</button>
            </form>
        <?php endif; ?>

        <form method="POST" action="<?= url('/services/ui-scraper/delete/' . (int)$job['id']) ?>" class="d-inline">
            <?= csrf_field() ?>
            <button class="btn btn-outline-light btn-sm"><i class="fas fa-trash"></i></button>
        </form>
    </div>

    <div class="panel-card mb-4">
        <div class="scraper-progress" id="scraperProgress"
             data-job="<?= (int)$job['id'] ?>"
             data-status="<?= e($st) ?>">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="scraper-step" id="scraperStep"><?= e((string)$job['step']) ?></span>
                <span class="scraper-percent font-numeric" id="scraperPercent"><?= (int)$job['progress'] ?>%</span>
            </div>
            <div class="progress scraper-bar" role="progressbar"
                 aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int)$job['progress'] ?>">
                <div class="progress-bar" id="scraperBar" style="width: <?= (int)$job['progress'] ?>%"></div>
            </div>
            <div class="scraper-meta mt-2" id="scraperMeta">
                <?php if ((int)$job['total_assets'] > 0): ?>
                    Đã tải <?= (int)$job['done_assets'] ?> / <?= (int)$job['total_assets'] ?> tài nguyên
                <?php endif; ?>
            </div>
        </div>

        <?php if ($st === 'failed' && !empty($job['error_msg'])): ?>
            <div class="alert alert-danger small mt-3 mb-0">
                <i class="fas fa-circle-exclamation me-1"></i><?= e((string)$job['error_msg']) ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($st === 'done' && !empty($cnt)): ?>
        <div class="row g-2 text-center mb-4">
            <div class="col-6 col-md-3">
                <div class="stat-card"><div class="stat-value"><?= (int)($cnt['css'] ?? 0) ?></div><div class="stat-label">CSS</div></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card"><div class="stat-value"><?= (int)($cnt['images'] ?? 0) ?></div><div class="stat-label">Ảnh / sprite</div></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card"><div class="stat-value"><?= (int)($cnt['scripts'] ?? 0) ?></div><div class="stat-label">Script</div></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card"><div class="stat-value"><?= (int)($cnt['downloaded'] ?? 0) ?></div><div class="stat-label">Đã tải về</div></div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($st === 'done' && !empty($manifest['assets'])): ?>
        <div class="panel-card mb-4">
            <h6 class="mb-3"><i class="fas fa-box-open me-2"></i>Tài nguyên đã tải về (<?= count($manifest['assets']) ?>)</h6>
            <div class="api-doc-side" style="position:static;max-height:340px">
                <?php foreach (array_slice($manifest['assets'], 0, 200) as $a): ?>
                    <div class="api-side-link d-flex justify-content-between gap-2">
                        <span class="text-truncate"><?= e((string)$a['file']) ?></span>
                        <span class="text-muted-2 flex-shrink-0"><?= number_format((int)$a['size'] / 1024, 1) ?> KB</span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <a href="<?= url('/services/ui-scraper') ?>" class="btn btn-outline-light">
        <i class="fas fa-arrow-left me-1"></i>Quay lại danh sách quét
    </a>
</div>

<script>
(function () {
    'use strict';

    var box = document.getElementById('scraperProgress');
    if (!box) return;

    var status = box.getAttribute('data-status');
    if (status !== 'running' && status !== 'queued') return;

    var jobId  = box.getAttribute('data-job');
    var stepEl = document.getElementById('scraperStep');
    var pctEl  = document.getElementById('scraperPercent');
    var barEl  = document.getElementById('scraperBar');
    var metaEl = document.getElementById('scraperMeta');
    var pollTimer = null;

    function setText(el, txt) { if (el && txt !== null && txt !== undefined) el.textContent = txt; }

    function poll() {
        fetch('/services/ui-scraper/status/' + jobId, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (!json || json.success !== true) return;
                var d = json.data;
                setText(stepEl, d.step);
                setText(pctEl, d.progress + '%');
                if (barEl) barEl.style.width = d.progress + '%';
                if (d.total > 0) setText(metaEl, 'Đã tải ' + d.done + ' / ' + d.total + ' tài nguyên');
                if (d.status === 'done' || d.status === 'failed') {
                    if (pollTimer) clearInterval(pollTimer);
                    setTimeout(function () { window.location.reload(); }, 700);
                }
            })
            .catch(function () {});
    }

    pollTimer = setInterval(poll, 1500);
})();
</script>
