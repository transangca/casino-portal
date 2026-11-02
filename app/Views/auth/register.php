<?php /** @var string $title */ ?>
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="text-center mb-4">
            <i class="fas fa-user-plus fs-1 text-gold mb-3"></i>
            <h1 class="h4 fw-bold mb-1">Đăng ký tài khoản</h1>
            <p class="text-secondary small mb-0">Nhận gói trải nghiệm miễn phí ngay khi đăng ký</p>
        </div>

        <form method="POST" action="<?= url('/register') ?>">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label" for="full_name">Họ và tên</label>
                <input type="text" class="form-control" id="full_name" name="full_name"
                       value="<?= e(old('full_name')) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="username">Tên đăng nhập</label>
                <input type="text" class="form-control" id="username" name="username"
                       value="<?= e(old('username')) ?>" minlength="3" maxlength="50" required>
                <div class="form-text" style="color:#6c6479">3-50 ký tự: chữ, số, gạch dưới</div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="email">Email</label>
                <input type="email" class="form-control" id="email" name="email"
                       value="<?= e(old('email')) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">Mật khẩu</label>
                <div class="input-group">
                    <input type="password" class="form-control" id="password" name="password"
                           minlength="<?= (int)config('security.password_min', 8) ?>" required>
                    <button class="btn btn-outline-secondary" type="button" onclick="togglePw('password', this)">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="password_confirm">Xác nhận mật khẩu</label>
                <input type="password" class="form-control" id="password_confirm" name="password_confirm" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="ref_code">
                    Mã giới thiệu
                    <?php if (!empty($refCode)): ?>
                        <span class="badge-status st-active ms-1"><i class="fas fa-check me-1"></i>Đã áp dụng</span>
                    <?php else: ?>
                        <span class="text-secondary">- không bắt buộc</span>
                    <?php endif; ?>
                </label>
                <input type="text" class="form-control" id="ref_code" name="ref_code"
                       value="<?= e((string)($refCode ?? '')) ?>" placeholder="VD: A1B2C3D4"
                       <?= !empty($refCode) ? 'readonly' : '' ?>>
                <?php if (!empty($refCode)): ?>
                    <div class="form-text" style="color:#6c6479">
                        <i class="fas fa-gift me-1"></i>Mã giới thiệu đã được ghi nhận từ liên kết bạn truy cập.
                    </div>
                <?php endif; ?>
            </div>
            <button class="btn btn-primary w-100 py-2 mb-3">Tạo tài khoản</button>
        </form>

        <p class="text-center text-secondary small mb-0">
            Đã có tài khoản? <a href="<?= url('/login') ?>" class="text-gold text-decoration-none fw-semibold">Đăng nhập</a>
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
