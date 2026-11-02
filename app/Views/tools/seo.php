<?php /** @var string $url, array $result */ ?>
<div class="panel-card mb-4">
    <form method="POST" class="row g-3 align-items-end">
        <?= csrf_field() ?>
        <div class="col-md-9">
            <label class="form-label">URL website</label>
            <input type="url" name="url" class="form-control font-monospace" placeholder="https://example.com" value="<?= e($url) ?>">
        </div>
        <div class="col-md-3">
            <button class="btn btn-gold w-100"><i class="fas fa-magnifying-glass-chart me-2"></i>Kiểm tra</button>
        </div>
    </form>
</div>

<?php if (!empty($result['ok'])): ?>
    <?php $d = $result['data']; ?>
    <div class="row g-3">
        <div class="col-md-4">
            <div class="panel-card text-center h-100">
                <div class="seo-score"><?= (int)$d['score'] ?></div>
                <div class="text-muted-2 small">/ 100 điểm SEO</div>
                <div class="progress mt-3" style="height:6px">
                    <div class="progress-bar <?= $d['score'] >= 70 ? 'bg-success' : ($d['score'] >= 40 ? 'bg-warning' : 'bg-danger') ?>"
                         style="width:<?= (int)$d['score'] ?>%"></div>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="panel-card">
                <h6 class="mb-3"><i class="fas fa-list-check me-2 text-gold"></i>Chi tiết kiểm tra</h6>
                <?php foreach ($d['checks'] as $c): ?>
                    <div class="seo-check-row">
                        <i class="fas <?= $c['ok'] ? 'fa-circle-check text-success' : 'fa-circle-xmark text-danger' ?>"></i>
                        <span class="flex-grow-1"><?= e((string)$c['name']) ?></span>
                        <small class="text-muted-2 text-end"><?= e((string)$c['detail']) ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="col-12">
            <div class="panel-card">
                <h6 class="mb-3"><i class="fas fa-file-lines me-2 text-gold"></i>Meta đã tìm thấy</h6>
                <table class="table table-sm small mb-0">
                    <tr><td class="text-muted-2" style="width:160px">Title</td><td><?= e((string)($d['title'] ?? '—')) ?></td></tr>
                    <tr><td class="text-muted-2">Description</td><td><?= e((string)($d['desc'] ?? '—')) ?></td></tr>
                    <tr><td class="text-muted-2">Keywords</td><td><?= e((string)($d['keywords'] ?? '—')) ?></td></tr>
                    <tr><td class="text-muted-2">Canonical</td><td class="font-monospace small"><?= e((string)($d['canonical'] ?? '—')) ?></td></tr>
                    <tr><td class="text-muted-2">OG Image</td><td class="font-monospace small"><?= e((string)($d['og_image'] ?? '—')) ?></td></tr>
                </table>
                <?php if (!empty($d['h1'])): ?>
                    <div class="mt-3 small">
                        <strong>H1:</strong>
                        <?php foreach ($d['h1'] as $h): ?>
                            <span class="badge bg-secondary bg-opacity-25 me-1"><?= e($h) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php elseif (!empty($result['message'])): ?>
    <div class="alert alert-warning mb-0"><?= e((string)$result['message']) ?></div>
<?php endif; ?>
