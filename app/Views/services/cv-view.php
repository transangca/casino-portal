<?php /** @var array $cv,$html */ ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="page-title" style="font-size:1.4rem"><?= e($cv['title']) ?></h1>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-gold btn-sm">
                <i class="fas fa-print me-1"></i>In / Lưu PDF
            </button>
            <a href="<?= url('/services/cv') ?>" class="btn btn-outline-gold btn-sm">Quay lại</a>
        </div>
    </div>

    <div class="cv-paper">
        <?= $html ?>
    </div>
</div>
