<?php /** @var array $groups, string $group, string $from, string $to, float $value, float|null $result */ ?>
<div class="panel-card">
    <form method="POST">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Loại đơn vị</label>
                <select name="group" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($groups as $code => $units): ?>
                        <option value="<?= e($code) ?>" <?= $group === $code ? 'selected' : '' ?>><?= e(ucfirst($code)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Từ</label>
                <select name="from" class="form-select">
                    <?php foreach ($groups[$group] ?? [] as $code => $u): ?>
                        <option value="<?= e($code) ?>" <?= $from === $code ? 'selected' : '' ?>><?= e($u['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Giá trị</label>
                <input type="number" step="any" name="value" class="form-control" value="<?= e((string)$value) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Đến</label>
                <select name="to" class="form-select">
                    <?php foreach ($groups[$group] ?? [] as $code => $u): ?>
                        <option value="<?= e($code) ?>" <?= $to === $code ? 'selected' : '' ?>><?= e($u['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button class="btn btn-gold w-100"><i class="fas fa-right-left me-2"></i>Đổi</button>
            </div>
        </div>
    </form>

    <?php if ($result !== null): ?>
        <div class="convert-result mt-4">
            <i class="fas fa-equals fs-3 text-gold"></i>
            <div>
                <div class="small text-muted-2">Kết quả</div>
                <strong class="fs-4"><?= e(number_format($result, 8, ',', '.')) ?></strong>
            </div>
        </div>
    <?php endif; ?>
</div>
