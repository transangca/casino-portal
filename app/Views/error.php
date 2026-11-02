<?php
/** @var int $errorCode */
/** @var string $errorMessage */
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Lỗi <?= (int)$errorCode ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center;
               background:#07060b; color:#f8f5ef; font-family:Inter,system-ui,sans-serif; text-align:center; }
        .box { padding:48px; }
        .code { font-size:5rem; font-weight:800; color:#f5c542; line-height:1; }
        .msg { color:#9a92a8; margin:12px 0 28px; }
        a.btn { display:inline-block; padding:12px 28px; border-radius:999px; background:#f5c542;
                color:#14100a; font-weight:700; text-decoration:none; }
    </style>
</head>
<body>
    <div class="box">
        <div class="code"><?= (int)$errorCode ?></div>
        <div class="msg"><?= e($errorMessage ?: 'Đã xảy ra lỗi.') ?></div>
        <a class="btn" href="<?= url('/') ?>">Về trang chủ</a>
    </div>
</body>
</html>
