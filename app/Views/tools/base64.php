<?php /** @var string $input, string $mode, array $result */ ?>
<div class="panel-card">
    <form method="POST">
        <?= csrf_field() ?>
        <div class="btn-group mb-3">
            <button name="mode" value="encode" class="btn <?= $mode === 'encode' ? 'btn-gold' : 'btn-outline-gold' ?>">Encode</button>
            <button name="mode" value="decode" class="btn <?= $mode === 'decode' ? 'btn-gold' : 'btn-outline-gold' ?>">Decode</button>
        </div>
        <textarea name="input" rows="8" class="form-control font-monospace" placeholder="Chuỗi..."><?= e($input) ?></textarea>
        <button class="btn btn-gold mt-3"><i class="fas fa-shuffle me-2"></i>Xử lý</button>
    </form>

    <?php if (!empty($result['ok'])): ?>
        <div class="mt-4">
            <textarea id="b64Out" rows="6" class="form-control font-monospace" readonly><?= e((string)$result['output']) ?></textarea>
            <button type="button" class="btn btn-sm btn-outline-gold mt-2 copy-btn" data-copy="#b64Out">
                <i class="fas fa-copy me-1"></i>Copy
            </button>
        </div>
    <?php elseif (!empty($result['message'])): ?>
        <div class="alert alert-warning mt-3 mb-0"><?= e((string)$result['message']) ?></div>
    <?php endif; ?>
</div>
