<?php
/**
 * @var string $username
 * @var float  $amount
 * @var string $method
 * @var string $reference
 */
?>
<p>Xin chào <strong><?= e($username) ?></strong>,</p>
<p>Chúng tôi đã ghi nhận yêu cầu rút tiền của bạn:</p>
<table style="width:100%;border-collapse:collapse;font-size:14px">
    <tr><td style="padding:6px 0;color:#7b8194">Số tiền</td><td style="padding:6px 0"><strong><?= money((float)($amount ?? 0)) ?></strong></td></tr>
    <tr><td style="padding:6px 0;color:#7b8194">Hình thức</td><td style="padding:6px 0"><strong><?= e($method ?? '') ?></strong></td></tr>
    <tr><td style="padding:6px 0;color:#7b8194">Mã đơn</td><td style="padding:6px 0"><strong><?= e($reference ?? '') ?></strong></td></tr>
</table>
<p>Đơn rút tiền đang được xử lý, thời gian xử lý dự kiến từ 5 đến 30 phút.</p>
