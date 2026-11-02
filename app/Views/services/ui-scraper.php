<?php
/**
 * Dịch vụ đục UI/UX website — form + tiến trình quét thật.
 *
 * @var ?array $job
 * @var array  $jobs
 */
?>
<div class="container py-4">
    <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
        <div>
            <h1 class="page-title mb-1"><i class="fas fa-wand-magic-sparkles me-2 text-gold"></i>Đục UI/UX web</h1>
            <p class="page-sub mb-0">
                Dán link website — hệ thống <strong>tải thật</strong> toàn bộ HTML, CSS, ảnh, sprite,
                font, script về đĩa rồi đóng gói thành 1 file ZIP hoàn chỉnh.
            </p>
        </div>
    </div>

    <div class="alert alert-secondary small">
        <i class="fas fa-circle-info me-1"></i>
        Chỉ quét những website bạn có quyền sử dụng. Tài nguyên của bên thứ ba vẫn thuộc
        bản quyền của họ — bạn chịu trách nhiệm về cách sử dụng.
    </div>

    <div class="row g-4">
        <!-- ===== Form ===== -->
        <div class="col-lg-4">
            <div class="panel-card">
                <h6 class="mb-3"><i class="fas fa-link me-2"></i>Link website cần quét</h6>

                <form method="POST" action="<?= url('/services/ui-scraper/scan') ?>" id="scanForm">
                    <?= csrf_field() ?>

                    <div class="mb-2">
                        <label class="form-label" for="url">Đường dẫn</label>
                        <input type="text" id="url" name="url" class="form-control font-numeric"
                               placeholder="https://kingcasino.eu.cc/" required>
                        <div class="form-hint mt-1">Dán link trang chủ hoặc trang sảnh game.</div>
                    </div>

                    <button class="btn btn-gold w-100" type="submit" id="scanBtn">
                        <i class="fas fa-magnifying-glass-chart me-1"></i>Bắt đầu quét A → Z
                    </button>
                </form>

                <div class="form-hint mt-2">
                    <i class="fas fa-clock me-1"></i>
                    Giới hạn 30 lần quét / ngày. Quét 1 website thường mất 30–120 giây tùy số lượng asset.
                </div>

                <?php if (!empty($jobs)): ?>
                    <hr class="footer-divider">
                    <h6 class="mb-2 small"><i class="fas fa-clock-rotate-left me-1"></i>Lần quét gần đây</h6>
                    <div class="aic-order-list" style="max-height:260px">
                        <?php foreach ($jobs as $j): ?>
                            <?php
                            $st = (string)$j['status'];
                            $cls = match ($st) {
                                'done'   => 'st-active',
                                'failed' => 'st-pending',
                                default  => 'st-processing',
                            };
                            ?>
                            <a class="aic-order-item text-decoration-none d-block"
                               href="<?= url('/services/ui-scraper/job/' . (int)$j['id']) ?>">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fas fa-globe text-gold"></i>
                                    <div class="min-w-0 flex-fill">
                                        <div class="small fw-semibold text-truncate"><?= e((string)$j['host']) ?></div>
                                        <div class="form-hint text-truncate"><?= e((string)$j['step']) ?></div>
                                    </div>
                                    <span class="badge-status <?= $cls ?>"><?= (int)$j['progress'] ?>%</span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ===== Kết quả ===== -->
        <div class="col-lg-8">
            <?php if (!$job): ?>
                <div class="empty-state">
                    <i class="fas fa-wand-magic-sparkles fs-1 d-block mb-3"></i>
                    <p class="mb-0">Chưa có kết quả. Dán link website rồi bấm "Bắt đầu quét".</p>
                    <p class="form-hint mt-2 mb-0">
                        Hệ thống sẽ hiển thị tiến trình tải từng file asset — ZIP chỉ xuất hiện khi quét xong.
                    </p>
                </div>
            <?php else: ?>
                <?php
                $st  = (string)$job['status'];
                $cnt = json_decode((string)($job['counts'] ?? ''), true);
                $cnt = is_array($cnt) ? $cnt : [];
                ?>
                <div class="panel-card mb-4">
                    <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
                        <span class="brand-icon brand-icon-lg">
                            <i class="fas fa-globe"></i>
                        </span>
                        <div class="min-w-0 flex-fill">
                            <h5 class="mb-1 text-truncate"><?= e((string)$job['host']) ?></h5>
                            <div class="form-hint text-truncate">
                                <a href="<?= e((string)$job['url']) ?>" target="_blank" rel="noopener" class="text-gold">
                                    <?= e((string)$job['url']) ?>
                                </a>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <?php if ($st === 'done'): ?>
                                <form method="POST" action="<?= url('/services/ui-scraper/download/' . (int)$job['id']) ?>" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-gold btn-sm">
                                        <i class="fas fa-file-zipper me-1"></i>Tải ZIP tài nguyên
                                    </button>
                                </form>
                            <?php elseif ($st === 'queued' || $st === 'failed'): ?>
                                <form method="POST" action="<?= url('/services/ui-scraper/run/' . (int)$job['id']) ?>" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-gold btn-sm">
                                        <i class="fas fa-play me-1"></i><?= $st === 'failed' ? 'Quét lại' : 'Quét ngay' ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                            <form method="POST" action="<?= url('/services/ui-scraper/delete/' . (int)$job['id']) ?>" class="d-inline">
                                <?= csrf_field() ?>
                                <button class="btn btn-outline-light btn-sm"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </div>

                    <!-- ===== Tiến trình ===== -->
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
                            <?php elseif ($st === 'running'): ?>
                                Đang chuẩn bị...
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($st === 'failed' && !empty($job['error_msg'])): ?>
                        <div class="alert alert-danger small mt-3 mb-0">
                            <i class="fas fa-circle-exclamation me-1"></i><?= e((string)$job['error_msg']) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($st === 'done' && !empty($cnt)): ?>
                        <div class="row g-2 text-center mt-3 mb-0">
                            <div class="col-6 col-md-3">
                                <div class="stat-card">
                                    <div class="stat-value"><?= (int)($cnt['css'] ?? 0) ?></div>
                                    <div class="stat-label">CSS</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="stat-card">
                                    <div class="stat-value"><?= (int)($cnt['images'] ?? 0) ?></div>
                                    <div class="stat-label">Ảnh / sprite</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="stat-card">
                                    <div class="stat-value"><?= (int)($cnt['scripts'] ?? 0) ?></div>
                                    <div class="stat-label">Script</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="stat-card">
                                    <div class="stat-value"><?= (int)($cnt['downloaded'] ?? 0) ?></div>
                                    <div class="stat-label">Đã tải về</div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($st === 'done'): ?>
                    <!-- Danh sách asset đã tải -->
                    <?php
                    $manifest = json_decode((string)($job['result_json'] ?? ''), true);
                    $assets   = is_array($manifest) ? ($manifest['assets'] ?? []) : [];
                    ?>
                    <?php if (!empty($assets)): ?>
                        <div class="panel-card mb-4">
                            <h6 class="mb-3"><i class="fas fa-box-open me-2"></i>Tài nguyên đã tải về (<?= count($assets) ?>)</h6>
                            <div class="api-doc-side" style="position:static;max-height:340px">
                                <?php foreach (array_slice($assets, 0, 200) as $a): ?>
                                    <div class="api-side-link d-flex justify-content-between gap-2">
                                        <span class="text-truncate"><?= e((string)$a['file']) ?></span>
                                        <span class="text-muted-2 flex-shrink-0"><?= number_format((int)$a['size'] / 1024, 1) ?> KB</span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="alert alert-success small">
                        <i class="fas fa-circle-check me-1"></i>
                        Quét hoàn tất. File ZIP chứa website đã được viết lại liên kết nội bộ —
                        giải nén và mở <code>index.html</code> là chạy được ngay.
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    var box = document.getElementById('scraperProgress');
    if (!box) return;

    var jobId  = box.getAttribute('data-job');
    var status = box.getAttribute('data-status');
    if (status !== 'running' && status !== 'queued') return;

    var stepEl    = document.getElementById('scraperStep');
    var pctEl     = document.getElementById('scraperPercent');
    var barEl     = document.getElementById('scraperBar');
    var metaEl    = document.getElementById('scraperMeta');
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
                box.setAttribute('aria-valuenow', d.progress);

                if (d.total > 0) {
                    setText(metaEl, 'Đã tải ' + d.done + ' / ' + d.total + ' tài nguyên');
                }

                if (d.status === 'done' || d.status === 'failed') {
                    if (pollTimer) clearInterval(pollTimer);
                    // Tải lại trang để hiện nút tải ZIP / thông báo lỗi
                    setTimeout(function () { window.location.reload(); }, 700);
                }
            })
            .catch(function () { /* im lặng, thử lại ở vòng sau */ });
    }

    pollTimer = setInterval(poll, 1500);

    // Dừng polling nếu người dùng rời trang
    document.addEventListener('visibilitychange', function () {
        if (document.hidden && pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        } else if (!document.hidden && !pollTimer) {
            pollTimer = setInterval(poll, 1500);
        }
    });
})();
</script>
