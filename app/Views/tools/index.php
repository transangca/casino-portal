<?php /** @var array $groups */ ?>
<div class="row g-3">
    <?php foreach ($groups as $group => $tools): ?>
        <div class="col-md-6 col-xl-4">
            <div class="panel-card h-100">
                <h6 class="mb-3"><i class="fas fa-layer-group me-2 text-gold"></i><?= e($group) ?></h6>
                <?php foreach ($tools as $t): ?>
                    <a href="<?= url((string)$t['route']) ?>" class="tool-tile">
                        <i class="fas <?= e((string)($t['icon'] ?: 'fa-tools')) ?>"></i>
                        <span>
                            <strong><?= e((string)$t['name']) ?></strong>
                            <small><?= e((string)($t['description'] ?: '')) ?></small>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
