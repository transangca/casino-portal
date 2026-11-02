<?php /** @var string $title */ ?>
<div class="auth-wrapper">
    <div class="auth-card">
        <?php $loginAds = \App\Services\AdService::forSlot('login', 1); ?>
        <?php if (!empty($loginAds)): ?>
            <div class="ad-slot mb-3">
                <?php foreach ($loginAds as $ad): ?>
                    <?= \App\Services\AdService::render($ad) ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="text-center mb-4">
            <i class="fas fa-dice fs-1 text-gold mb-3"></i>
            <h1 class="h4 fw-bold mb-1">Đăng nhập</h1>
            <p class="text-secondary small mb-0">Chào mừng trở lại với <?= e(setting('site_name', 'Casino Portal')) ?></p>
        </div>

        <form method="POST" action="<?= url('/login') ?>">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label" for="login">Tên đăng nhập hoặc Email</label>
                <input type="text" class="form-control" id="login" name="login"
                       value="<?= e(old('login')) ?>" required autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">Mật khẩu</label>
                <div class="input-group">
                    <input type="password" class="form-control" id="password" name="password" required>
                    <button class="btn btn-outline-secondary" type="button" onclick="togglePw('password', this)">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>
            <button class="btn btn-primary w-100 py-2 mb-3">Đăng nhập</button>
        </form>

        <p class="text-center text-secondary small mb-0">
            Chưa có tài khoản? <a href="<?= url('/register') ?>" class="text-gold text-decoration-none fw-semibold">Đăng ký ngay</a>
        </p>
    </div>
</div>
<script>
function togglePw(id, btn) {
    var el = document.getElementById(id);
    var icon = btn.querySelector('i');
    if (el.type === 'password') { el.type = 'text'; icon.className = 'fas fa-eye-slash'; }
    else { el.type = 'password'; icon.className = 'fas fa-eye'; }
}
</script>
