<?php
/**
 * @var string $username
 * @var float  $amount
 * @var string $method
 * @var string $reference
 */
?>
<p>Xin chào <strong><?= e($username) ?></strong>,</p>
<p>Yêu cầu nạp tiền của bạn đã được <strong style="color:#1a9b5c">xử lý thành công</strong>.</p>
<table style="width:100%;border-collapse:collapse;font-size:14px">
    <tr><td style="padding:6px 0;color:#7b8194">Số tiền</td><td style="padding:6px 0"><strong><?= money((float)($amount ?? 0)) ?></strong></td></tr>
    <tr><td style="padding:6px 0;color:#7b8194">Hình thức</td><td style="padding:6px 0"><strong><?= e($method ?? '') ?></strong></td></tr>
    <tr><td style="padding:6px 0;color:#7b8194">Mã đơn</td><td style="padding:6px 0"><strong><?= e($reference ?? '') ?></strong></td></tr>
</table>
<p>Trạng thái: <strong>Đã cộng vào ví</strong>. Bạn có thể nạp tiền vào các sảnh đồng bộ ví ngay bây giờ.</p>
