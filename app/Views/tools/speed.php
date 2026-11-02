<?php /** @var string $url, array $result */ ?>
<div class="panel-card mb-4">
    <form method="POST" class="row g-3 align-items-end">
        <?= csrf_field() ?>
        <div class="col-md-9">
            <label class="form-label">URL website</label>
            <input type="url" name="url" class="form-control font-monospace" placeholder="https://example.com" value="<?= e($url) ?>">
        </div>
        <div class="col-md-3">
            <button class="btn btn-gold w-100"><i class="fas fa-gauge-high me-2"></i>Đo tốc độ</button>
        </div>
    </form>
</div>

<?php if (!empty($result['ok'])): ?>
    <?php $d = $result['data']; ?>
    <div class="row g-3">
        <?php
        $metrics = [
            ['Tổng thời gian', $d['total_ms'] . ' ms', 'fa-stopwatch'],
            ['DNS', $d['dns_ms'] . ' ms', 'fa-server'],
            ['Kết nối', $d['connect_ms'] . ' ms', 'fa-plug'],
            ['TTFB', $d['ttfb_ms'] . ' ms', 'fa-hourglass-half'],
            ['Dung lượng', $d['size_kb'] . ' KB', 'fa-database'],
            ['HTTP status', (string)$d['status'], 'fa-signal'],
        ];
        ?>
        <?php foreach ($metrics as $m): ?>
            <div class="col-sm-6 col-lg-4">
                <div class="panel-card h-100">
                    <div class="d-flex align-items-center gap-2 text-muted-2 small">
                        <i class="fas <?= $m[2] ?>"></i><?= e($m[0]) ?>
                    </div>
                    <strong class="fs-4 mt-1"><?= e($m[1]) ?></strong>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php elseif (!empty($result['message'])): ?>
    <div class="alert alert-warning mb-0"><?= e((string)$result['message']) ?></div>
<?php endif; ?>
