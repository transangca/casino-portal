<?php /** @var string $input, array $result */ ?>
<div class="row g-4">
    <div class="col-lg-6">
        <div class="panel-card h-100">
            <h6 class="mb-3"><i class="fas fa-code me-2 text-gold"></i>JSON đầu vào</h6>
            <form method="POST">
                <?= csrf_field() ?>
                <textarea name="input" rows="16" class="form-control font-monospace" placeholder='{"a":1}'><?= e($input) ?></textarea>
                <button class="btn btn-gold mt-3"><i class="fas fa-wand-magic-sparkles me-2"></i>Format</button>
            </form>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="panel-card h-100">
            <h6 class="mb-3"><i class="fas fa-align-left me-2 text-gold"></i>Kết quả</h6>
            <?php if (!empty($result['ok'])): ?>
                <textarea id="jsonOut" rows="14" class="form-control font-monospace" readonly><?= e((string)$result['pretty']) ?></textarea>
                <div class="d-flex gap-2 mt-2">
                    <button type="button" class="btn btn-sm btn-outline-gold copy-btn" data-copy="#jsonOut">
                        <i class="fas fa-copy me-1"></i>Copy
                    </button>
                    <span class="small text-muted-2 align-self-center"><?= (int)$result['size'] ?> bytes</span>
                </div>
            <?php elseif (!empty($result['message'])): ?>
                <div class="alert alert-warning mb-0"><?= e((string)$result['message']) ?></div>
            <?php else: ?>
                <p class="text-muted-2 mb-0">Nhập JSON vào ô bên trái để format.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
