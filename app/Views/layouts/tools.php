<?php
/**
 * Layout của riêng các trang công cụ.
 * @var string $content
 * @var string $title
 */
?>
<!DOCTYPE html>
<html lang="vi" data-theme="<?= e(current_theme()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title><?= e($title ?? 'Công cụ') ?> | <?= e(setting('site_name', 'Toolduc')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="<?= asset('css/style.css?v=6') ?>" rel="stylesheet">
    <link href="<?= asset('css/ui.css?v=1') ?>" rel="stylesheet">
    <link href="<?= asset('css/theme.css?v=5') ?>" rel="stylesheet">
</head>
<body>
<?php
$toolKey = $toolKey ?? '';
?>
<div class="container py-4">
    <nav aria-label="breadcrumb" class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= url('/') ?>"><i class="fas fa-house me-1"></i>Trang chủ</a></li>
            <li class="breadcrumb-item"><a href="<?= url('/tools') ?>">Công cụ</a></li>
            <li class="breadcrumb-item active"><?= e($title ?? '') ?></li>
        </ol>
        <a href="<?= url('/') ?>" class="btn btn-outline-gold btn-sm">
            <i class="fas fa-house me-1"></i>Về trang chủ
        </a>
    </nav>
    <div class="row g-4">
        <div class="col-lg-3"><?php $toolKey = $toolKey ?? ''; include BASE_PATH . '/app/Views/tools/_nav.php'; ?></div>
        <div class="col-lg-9">
            <h1 class="page-title"><?= e($title ?? '') ?></h1>
            <?= $content ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>window.APP = { baseUrl: '<?= url('/') ?>', csrfToken: '<?= csrf_token() ?>' };</script>
<script src="<?= asset('js/app.js?v=4') ?>"></script>
</body>
</html>
