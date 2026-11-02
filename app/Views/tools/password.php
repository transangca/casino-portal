<?php /** @var int $length, bool $symbols, bool $numbers, array $passwords */ ?>
<div class="panel-card">
    <form method="POST">
        <?= csrf_field() ?>
        <div class="row g-3 align-items-end">
            <div class="col-sm-4">
                <label class="form-label">Độ dài</label>
                <input type="number" name="length" class="form-control" value="<?= (int)$length ?>" min="8" max="64">
            </div>
            <div class="col-sm-4">
                <label class="form-label">Ký tự đặc biệt</label>
                <div class="form-check form-switch mt-2">
                    <input class="form-check-input" type="checkbox" name="symbols" id="sym" <?= $symbols ? 'checked' : '' ?>>
                    <label class="form-check-label" for="sym">Bật</label>
                </div>
            </div>
            <div class="col-sm-4">
                <label class="form-label">Chữ số</label>
                <div class="form-check form-switch mt-2">
                    <input class="form-check-input" type="checkbox" name="numbers" id="num" <?= $numbers ? 'checked' : '' ?>>
                    <label class="form-check-label" for="num">Bật</label>
                </div>
            </div>
        </div>
        <button class="btn btn-gold mt-3"><i class="fas fa-key me-2"></i>Sinh 10 mật khẩu</button>
    </form>

    <?php if (!empty($passwords)): ?>
        <div class="mt-4">
            <?php foreach ($passwords as $pw): ?>
                <div class="hash-row">
                    <code class="hash-value flex-grow-1"><?= e($pw) ?></code>
                    <button type="button" class="btn btn-sm btn-outline-gold copy-btn" data-copy="<?= e($pw) ?>">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
