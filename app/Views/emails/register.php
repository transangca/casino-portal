<?php
/**
 * @var string $username
 * @var string $site_name
 * @var string $site_url
 * @var string $email
 */
?>
<p>Xin chào <strong><?= e($username) ?></strong>,</p>
<p>Chúc mừng bạn đã đăng ký tài khoản tại <strong><?= e($site_name) ?></strong>!</p>
<p>Thông tin tài khoản:</p>
<ul>
    <li>Tên đăng nhập: <strong><?= e($username) ?></strong></li>
    <li>Email: <strong><?= e($email ?? '') ?></strong></li>
</ul>
<p>Hãy bật 2FA ngay trong tài khoản để bảo vệ tài khoản tốt hơn.</p>
<p style="text-align:center;margin:22px 0">
    <a class="btn" href="<?= e($site_url) ?>/login">Đăng nhập ngay</a>
</p>
