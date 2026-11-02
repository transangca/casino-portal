<?php
/**
 * Layout fullscreen cho phần mềm POS / kế toán.
 * Thoát bằng nút "Thoát" hoặc phím ESC.
 *
 * @var string $content
 */
$siteName = setting('site_name', 'Casino Portal');
$backUrl  = (string)setting('pos_exit_url', url('/'));
?>
<!DOCTYPE html>
<html lang="vi" data-theme="<?= e(current_theme()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title><?= e($title ?? 'Phần mềm quản lý') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="<?= asset('css/style.css?v=6') ?>" rel="stylesheet">
    <link href="<?= asset('css/ui.css?v=1') ?>" rel="stylesheet">
    <link href="<?= asset('css/theme.css?v=5') ?>" rel="stylesheet">
</head>
<body class="pos-body">
    <div class="pos-shell">
        <aside class="pos-sidebar">
            <div class="pos-brand">
                <i class="fas fa-cash-register"></i>
                <span>Quản lý bán hàng</span>
            </div>

            <nav class="pos-nav">
                <?php foreach (($categories ?? []) as $code => $label): ?>
                    <a href="<?= url('/services/pos?category=' . urlencode($code)) ?>"
                       class="<?= ($category ?? '') === $code ? 'active' : '' ?>">
                        <i class="fas fa-layer-group"></i><?= e($label) ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <button class="pos-exit" id="posExit" title="Thoát về website (ESC)">
                <i class="fas fa-right-from-bracket"></i> Thoát
            </button>
        </aside>

        <main class="pos-main">
            <?= $content ?>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>window.APP = { baseUrl: '<?= url('/') ?>', csrfToken: '<?= csrf_token() ?>' };</script>
    <script src="<?= asset('js/app.js?v=4') ?>"></script>
    <script>
    document.getElementById('posExit')?.addEventListener('click', function () {
        window.location.href = '<?= e($backUrl) ?>';
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            window.location.href = '<?= e($backUrl) ?>';
        }
    });
    </script>
</body>
</html>
