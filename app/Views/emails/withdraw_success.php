<?php
/**
 * @var string $username
 * @var float  $amount
 * @var string $method
 * @var string $reference
 */
?>
<p>Xin chào <strong><?= e($username) ?></strong>,</p>
<p>Yêu cầu rút tiền của bạn đã được <strong style="color:#1a9b5c">chuyển khoản thành công</strong>.</p>
<table style="width:100%;border-collapse:collapse;font-size:14px">
    <tr><td style="padding:6px 0;color:#7b8194">Số tiền</td><td style="padding:6px 0"><strong><?= money((float)($amount ?? 0)) ?></strong></td></tr>
    <tr><td style="padding:6px 0;color:#7b8194">Hình thức</td><td style="padding:6px 0"><strong><?= e($method ?? '') ?></strong></td></tr>
    <tr><td style="padding:6px 0;color:#7b8194">Mã đơn</td><td style="padding:6px 0"><strong><?= e($reference ?? '') ?></strong></td></tr>
</table>
<p>Cảm ơn bạn đã sử dụng dịch vụ của chúng tôi.</p>
