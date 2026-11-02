<?php
/**
 * @var string $username
 */
?>
<p>Xin chào <strong><?= e($username) ?></strong>,</p>
<p>Bảo mật hai lớp (2FA) đã được <strong style="color:#1a9b5c">bật thành công</strong> cho tài khoản của bạn.</p>
<p>Từ giờ, ngoài mật khẩu, bạn sẽ cần mã 6 chữ số từ ứng dụng xác thực khi đăng nhập.</p>
<p style="text-align:center;margin:22px 0">
    <a class="btn" href="<?= e($site_url ?? '') ?>/security/2fa">Quản lý bảo mật</a>
</p>
