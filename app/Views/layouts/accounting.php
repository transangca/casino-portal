<?php
/**
 * Layout fullscreen cho phần mềm kế toán (MISA-style).
 * Thoát bằng nút "Thoát" hoặc phím ESC.
 *
 * @var string $content
 */
$siteName = setting('site_name', 'Toolduc');
$backUrl  = (string)setting('accounting_exit_url', url('/'));
$section  = $section ?? 'dashboard';
$nav = [
    'dashboard' => ['Tổng quan', '/services/accounting', 'fa-chart-pie'],
    'documents' => ['Phiếu kế toán', '/services/accounting/documents', 'fa-file-invoice-dollar'],
    'create'    => ['Lập phiếu', '/services/accounting/create', 'fa-file-circle-plus'],
    'ledger'    => ['Sổ cái', '/services/accounting/ledger', 'fa-book'],
    'reports'   => ['Báo cáo', '/services/accounting/reports', 'fa-chart-column'],
    'backup'    => ['Sao lưu', '/services/accounting/backup', 'fa-database'],
];
?>
<!DOCTYPE html>
<html lang="vi" data-theme="<?= e(current_theme()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title><?= e($title ?? 'Phần mềm kế toán') ?></title>
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
                <i class="fas fa-calculator"></i>
                <span>Phần mềm kế toán</span>
            </div>

            <nav class="pos-nav">
                <?php foreach ($nav as $key => $n): ?>
                    <a href="<?= url($n[1]) ?>" class="<?= $section === $key ? 'active' : '' ?>">
                        <i class="fas <?= $n[2] ?>"></i><?= e($n[0]) ?>
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
