<?php /** @var string $input, array $result */ ?>
<div class="panel-card">
    <form method="POST">
        <?= csrf_field() ?>
        <label class="form-label">Chuỗi cần hash</label>
        <textarea name="input" rows="4" class="form-control font-monospace" placeholder="Nhập nội dung..."><?= e($input) ?></textarea>
        <button class="btn btn-gold mt-3"><i class="fas fa-calculator me-2"></i>Tính hash</button>
    </form>

    <?php if (!empty($result)): ?>
        <div class="mt-4">
            <?php foreach ($result as $algo => $hash): ?>
                <div class="hash-row">
                    <span class="hash-algo"><?= e($algo) ?></span>
                    <code class="hash-value"><?= e($hash) ?></code>
                    <button type="button" class="btn btn-sm btn-outline-gold copy-btn" data-copy="<?= e($hash) ?>">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
