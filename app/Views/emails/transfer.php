<?php
/**
 * @var string $username
 * @var float  $amount
 * @var float  $fee
 * @var string $recipient
 * @var string $note
 */
?>
<p>Xin chào <strong><?= e($username) ?></strong>,</p>
<p>Bạn đã chuyển tiền thành công trong hệ thống.</p>
<table style="width:100%;border-collapse:collapse;font-size:14px">
    <tr><td style="padding:6px 0;color:#7b8194">Người nhận</td><td style="padding:6px 0"><strong><?= e($recipient ?? '') ?></strong></td></tr>
    <tr><td style="padding:6px 0;color:#7b8194">Số tiền</td><td style="padding:6px 0"><strong><?= money((float)($amount ?? 0)) ?></strong></td></tr>
    <tr><td style="padding:6px 0;color:#7b8194">Phí</td><td style="padding:6px 0"><strong><?= money((float)($fee ?? 0)) ?></strong></td></tr>
</table>
<?php if (!empty($note)): ?>
    <p>Ghi chú: <em><?= e($note) ?></em></p>
<?php endif; ?>
