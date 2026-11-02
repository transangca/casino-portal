<?php
/** @var array $game,$session,$balance,$check */
$sessionToken = $session['session_token'];
$remaining    = $check['package'] ? max(0, strtotime($check['package']['expires_at']) - time()) : 0;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($game['name']) ?> - Đang chơi</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        :root { --gold:#f5c542; --bg:#07060b; --card:#14111f; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { background:var(--bg); color:#f8f5ef; font-family:Inter,system-ui,sans-serif; overflow:hidden; }
        .toolbar {
            display:flex; align-items:center; gap:.6rem; padding:.5rem .9rem;
            background:var(--card); border-bottom:1px solid rgba(245,197,66,.15);
            height:48px;
        }
        .tb-title { font-weight:700; font-size:.9rem; display:flex; align-items:center; gap:.5rem; }
        .tb-title i { color:var(--gold); }
        .tb-bal { margin-left:auto; background:rgba(245,197,66,.12); border:1px solid rgba(245,197,66,.25);
                  color:var(--gold); padding:.2rem .75rem; border-radius:999px; font-weight:700; font-size:.82rem;
                  font-variant-numeric:tabular-nums; }
        .tb-time { background:rgba(255,255,255,.06); padding:.2rem .7rem; border-radius:999px;
                   font-size:.8rem; font-variant-numeric:tabular-nums; }
        .tb-time.warn { background:rgba(255,61,87,.18); color:#ff8f9c; animation:pulse 1s infinite; }
        @keyframes pulse { 50% { opacity:.5; } }
        .tb-btn { background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.1); color:#e8e2f0;
                  padding:.22rem .7rem; border-radius:8px; font-size:.78rem; cursor:pointer; text-decoration:none; }
        .tb-btn:hover { border-color:var(--gold); color:var(--gold); }
        .frame-wrap { position:relative; height:calc(100vh - 48px); background:#000; }
        iframe { width:100%; height:100%; border:0; display:block; }
        .overlay { position:absolute; inset:0; background:rgba(7,6,11,.95); display:flex; flex-direction:column;
                   align-items:center; justify-content:center; gap:1rem; z-index:20; text-align:center; padding:24px; }
        .overlay.hide { display:none; }
        .spinner { width:44px; height:44px; border:3px solid rgba(245,197,66,.2); border-top-color:var(--gold);
                   border-radius:50%; animation:spin .8s linear infinite; }
        @keyframes spin { to { transform:rotate(360deg); } }
        .toast-area { position:fixed; top:60px; right:16px; z-index:50; display:flex; flex-direction:column; gap:.5rem; }
        .toast { background:var(--card); border:1px solid rgba(245,197,66,.3); border-radius:10px; padding:.6rem 1rem;
                 font-size:.85rem; animation:slideIn .25s ease; }
        @keyframes slideIn { from { transform:translateX(30px); opacity:0; } }
    </style>
</head>
<body>

<div class="toolbar">
    <span class="tb-title"><i class="fas fa-dice"></i><?= e($game['name']) ?></span>
    <span class="tb-time" id="timer" <?= $remaining > 0 ? '' : 'style="display:none"' ?>><i class="fas fa-clock me-1"></i><span id="timerText">--:--</span></span>
    <span class="tb-bal"><i class="fas fa-wallet me-1"></i><span id="balText"><?= money($balance) ?></span></span>
    <a class="tb-btn" href="<?= url('/games/' . $game['slug']) ?>" title="Về trang game"><i class="fas fa-arrow-left"></i></a>
    <a class="tb-btn" href="#" onclick="window.frames.gameFrame && window.frames.gameFrame.location.reload(); return false;" title="Tải lại"><i class="fas fa-rotate"></i></a>
    <a class="tb-btn" href="#" onclick="document.getElementById('gameFrame').requestFullscreen(); return false;" title="Toàn màn hình"><i class="fas fa-expand"></i></a>
</div>

<div class="frame-wrap">
    <iframe id="gameFrame" name="gameFrame" src="<?= e(url('play/embed/' . $sessionToken)) ?>"
            allow="autoplay; fullscreen; gamepad" allowfullscreen></iframe>
    <div class="overlay" id="overlay">
        <div class="spinner"></div>
        <div>Đang tải game...</div>
    </div>
</div>

<div class="toast-area" id="toasts"></div>

<script>
(function () {
    const SESSION_TOKEN = <?= json_encode($sessionToken) ?>;
    const BASE = <?= json_encode(url('/')) ?>;
    const CSRF = <?= json_encode(csrf_token()) ?>;
    const HAS_TIMER = <?= $remaining > 0 ? 'true' : 'false' ?>;

    let remaining = <?= (int)$remaining ?>;
    let stopped = false;

    // Ẩn overlay khi iframe load xong
    document.getElementById('gameFrame').addEventListener('load', function () {
        setTimeout(function () { document.getElementById('overlay').classList.add('hide'); }, 800);
    });

    function toast(msg, kind) {
        const el = document.createElement('div');
        el.className = 'toast';
        el.textContent = msg;
        if (kind === 'err') el.style.borderColor = 'rgba(255,61,87,.5)';
        document.getElementById('toasts').appendChild(el);
        setTimeout(function () { el.remove(); }, 4000);
    }

    // Đồng hồ đếm ngược
    function tick() {
        if (!HAS_TIMER || stopped) return;
        remaining = Math.max(0, remaining - 1);
        const m = Math.floor(remaining / 60), s = remaining % 60;
        const el = document.getElementById('timerText');
        const box = document.getElementById('timer');
        if (el) {
            el.textContent = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
            if (remaining <= 60) box.classList.add('warn');
        }
        if (remaining <= 0) {
            stopped = true;
            toast('Hết thời gian chơi! Vui lòng mua thêm gói.', 'err');
            showEndOverlay();
        }
    }
    if (HAS_TIMER) setInterval(tick, 1000);

    function showEndOverlay() {
        const ov = document.getElementById('overlay');
        ov.innerHTML = '<div style="font-size:2.5rem">&#9203;</div>' +
            '<div class="fw-bold">Phiên chơi đã kết thúc</div>' +
            '<div style="color:#9a92a8;font-size:.9rem">Gói chơi của bạn đã hết thời gian.</div>' +
            '<a href="' + BASE + 'packages" class="btn btn-play mt-2">Mua thêm gói</a>' +
            '<a href="' + BASE + 'games" class="btn btn-outline-light">Về kho game</a>';
        ov.classList.remove('hide');
    }

    // Heartbeat: giữ phiên + đồng bộ số dư
    function beat() {
        const fd = new URLSearchParams();
        fd.append('session_token', SESSION_TOKEN);
        fd.append('_csrf_token', CSRF);

        fetch(BASE + 'play/heartbeat', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: fd.toString()
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (!res || !res.success) return;
            if (typeof res.balance !== 'undefined') {
                document.getElementById('balText').textContent = fmt(res.balance);
            }
            if (res.expired) {
                stopped = true;
                showEndOverlay();
            }
        })
        .catch(function () {});
    }

    function fmt(n) {
        return Number(n).toLocaleString('vi-VN') + ' đ';
    }

    setInterval(beat, 15000);
    setTimeout(beat, 2000);

    // Khi đóng tab → báo server
    window.addEventListener('beforeunload', function () {
        const fd = new URLSearchParams();
        fd.append('session_token', SESSION_TOKEN);
        fd.append('_csrf_token', CSRF);
        navigator.sendBeacon(BASE + 'play/close', fd);
    });
})();
</script>
</body>
</html>
