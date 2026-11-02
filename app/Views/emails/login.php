<?php
/**
 * @var string $username
 * @var string $ip
 * @var string $device
 * @var string $time
 */
?>
<p>Xin chào <strong><?= e($username) ?></strong>,</p>
<p>Tài khoản của bạn vừa đăng nhập vào hệ thống.</p>
<table style="width:100%;border-collapse:collapse;font-size:14px">
    <tr><td style="padding:6px 0;color:#7b8194">Thời gian</td><td style="padding:6px 0"><strong><?= e($time ?? '') ?></strong></td></tr>
    <tr><td style="padding:6px 0;color:#7b8194">Địa chỉ IP</td><td style="padding:6px 0"><strong><?= e($ip ?? '') ?></strong></td></tr>
    <tr><td style="padding:6px 0;color:#7b8194">Thiết bị</td><td style="padding:6px 0"><strong><?= e($device ?? '') ?></strong></td></tr>
</table>
<p>Nếu không phải bạn, hãy đổi mật khẩu ngay và bật 2FA.</p>
<p style="text-align:center;margin:22px 0">
    <a class="btn" href="<?= e($site_url ?? '') ?>/security/2fa">Bật bảo mật 2FA</a>
</p>
