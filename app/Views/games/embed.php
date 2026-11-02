<?php
/** @var array $game,$session,$launchUrl,$balance,$csrf */
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1, user-scalable=no">
    <title><?= e($game['name']) ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        html, body { margin:0; padding:0; height:100%; background:#000; overflow:hidden;
                     font-family:Inter,system-ui,sans-serif; }
        .wrap { position:relative; height:100%; }
        iframe { width:100%; height:100%; border:0; display:block; }
        .loading { position:absolute; inset:0; background:#000; display:flex; flex-direction:column;
                   align-items:center; justify-content:center; gap:1rem; z-index:9; color:#f8f5ef; }
        .loading.hide { display:none; }
        .spin { width:46px; height:46px; border:3px solid rgba(245,197,66,.2); border-top-color:#f5c542;
                border-radius:50%; animation:s .8s linear infinite; }
        @keyframes s { to { transform:rotate(360deg); } }
        .err { color:#ff8f9c; font-size:.9rem; max-width:420px; text-align:center; line-height:1.6; }
    </style>
</head>
<body>
<div class="wrap">
    <iframe id="gf" src="<?= e($launchUrl) ?>" allow="autoplay; fullscreen; gamepad; clipboard-write"
            allowfullscreen sandbox="allow-scripts allow-same-origin allow-popups allow-forms allow-pointer-lock allow-top-navigation"></iframe>
    <div class="loading" id="ld">
        <div class="spin"></div>
        <div style="font-size:.9rem;color:#9a92a8">Đang tải <?= e($game['name']) ?>...</div>
    </div>
</div>
<script>
    document.getElementById('gf').addEventListener('load', function () {
        setTimeout(function () { document.getElementById('ld').classList.add('hide'); }, 1200);
    });
    // Nếu game không load sau 12s → báo lỗi
    setTimeout(function () {
        var ld = document.getElementById('ld');
        if (!ld.classList.contains('hide')) {
            ld.innerHTML = '<div class="err"><i class="fas fa-triangle-exclamation" style="font-size:2rem"></i>' +
                '<div class="mt-2">Không thể tải game. Vui lòng kiểm tra kết nối mạng hoặc thử lại.</div>' +
                '<button onclick="location.reload()" style="margin-top:14px;padding:.5rem 1.2rem;border-radius:8px;' +
                'border:1px solid #f5c542;background:transparent;color:#f5c542;cursor:pointer">Tải lại</button></div>';
        }
    }, 12000);
</script>
</body>
</html>
