<?php
/**
 * Trang bảo trì (503).
 *
 * @var string $title
 * @var string $message
 * @var string $contact
 */
$contact = $contact ?? '';
?>
<!DOCTYPE html>
<html lang="vi" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'Đang bảo trì') ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            background: radial-gradient(900px 480px at 15% 0%, rgba(245,197,66,.10), transparent 60%),
                        radial-gradient(800px 420px at 90% 100%, rgba(120,80,220,.12), transparent 60%),
                        #0a0912;
            color: #e8e6f2; font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif;
            padding: 24px;
        }
        .maint-card {
            max-width: 560px; width: 100%; text-align: center;
            background: rgba(255,255,255,.035); border: 1px solid rgba(255,255,255,.09);
            border-radius: 20px; padding: 40px 32px; box-shadow: 0 30px 70px rgba(0,0,0,.5);
        }
        .maint-icon {
            width: 86px; height: 86px; margin: 0 auto 22px; border-radius: 24px;
            background: linear-gradient(135deg, #f5c542, #d99a1c);
            display: flex; align-items: center; justify-content: center;
            font-size: 2.1rem; color: #14100a;
            animation: pulse 2.4s ease-in-out infinite;
        }
        @keyframes pulse {
            0%,100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(245,197,66,.35); }
            50% { transform: scale(1.05); box-shadow: 0 0 0 16px rgba(245,197,66,0); }
        }
        h1 { margin: 0 0 10px; font-size: 1.6rem; font-weight: 800; }
        p { margin: 0 0 8px; color: #b7b1c8; line-height: 1.65; }
        .maint-code { font-size: .78rem; color: #6f6a82; margin-top: 18px; }
        .maint-contact {
            display: inline-flex; align-items: center; gap: 8px; margin-top: 18px;
            padding: 10px 18px; border-radius: 999px;
            background: rgba(245,197,66,.12); color: #f5c542; text-decoration: none; font-weight: 600; font-size: .9rem;
        }
        .maint-contact:hover { background: rgba(245,197,66,.2); color: #ffd873; }
        .maint-spinner {
            margin: 22px auto 0; width: 26px; height: 26px; border-radius: 50%;
            border: 3px solid rgba(255,255,255,.12); border-top-color: #f5c542;
            animation: spin .9s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="maint-card">
        <div class="maint-icon"><i class="fas fa-tools"></i></div>
        <h1><?= e($title ?? 'Đang bảo trì') ?></h1>
        <p><?= e($message ?? 'Hệ thống đang được nâng cấp. Vui lòng quay lại sau ít phút.') ?></p>

        <?php if ($contact !== ''): ?>
            <a class="maint-contact" href="<?= e($contact) ?>" target="_blank" rel="noopener">
                <i class="fab fa-telegram-plane"></i> Liên hệ hỗ trợ
            </a>
        <?php endif; ?>

        <div class="maint-spinner"></div>
        <div class="maint-code">Mã lỗi 503 · Trang trả về sau 15 phút</div>
    </div>
</body>
</html>
