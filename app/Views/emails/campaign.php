<?php
/**
 * Template email chiến dịch broadcast.
 *
 * @var string $username
 * @var string $message
 * @var string $banner
 */
?>
<?php if (!empty($banner)): ?>
    <p style="text-align:center;margin:0 0 18px">
        <img src="<?= e($banner) ?>" alt="" style="max-width:100%;border-radius:10px">
    </p>
<?php endif; ?>

<?php if (!empty($username)): ?>
    <p>Xin chào <strong><?= e($username) ?></strong>,</p>
<?php endif; ?>

<p><?= nl2br(e((string)($message ?? ''))) ?></p>

<p style="text-align:center;margin:22px 0">
    <a class="btn" href="<?= e($site_url ?? '') ?>">Truy cập ngay</a>
</p>
