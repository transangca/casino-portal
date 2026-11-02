<?php /** @var int $count, array $uuids */ ?>
<div class="panel-card">
    <form method="POST">
        <?= csrf_field() ?>
        <div class="row g-3 align-items-end">
            <div class="col-sm-4">
                <label class="form-label">Số lượng</label>
                <input type="number" name="count" class="form-control" value="<?= (int)$count ?>" min="1" max="50">
            </div>
            <div class="col-sm-4">
                <button class="btn btn-gold w-100"><i class="fas fa-fingerprint me-2"></i>Sinh UUID</button>
            </div>
        </div>
    </form>

    <div class="mt-4">
        <?php foreach ($uuids as $u): ?>
            <div class="hash-row">
                <code class="hash-value flex-grow-1"><?= e($u) ?></code>
                <button type="button" class="btn btn-sm btn-outline-gold copy-btn" data-copy="<?= e($u) ?>">
                    <i class="fas fa-copy"></i>
                </button>
            </div>
        <?php endforeach; ?>
    </div>
</div>
