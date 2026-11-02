<?php
/**
 * Lịch sử tra cứu số điện thoại của tôi.
 *
 * @var array $history
 */
?>
<div class="container py-4">
    <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
        <div class="flex-fill">
            <h1 class="page-title mb-1"><i class="fas fa-clock-rotate-left me-2 text-gold"></i>Lịch sử tra cứu</h1>
            <p class="page-sub mb-0">Mọi lần tra cứu đều được ghi lại để quản trị đối soát.</p>
        </div>
        <a href="<?= url('/services/phone-check') ?>" class="btn btn-gold">
            <i class="fas fa-magnifying-glass me-1"></i>Tra cứu mới
        </a>
    </div>

    <div class="panel-card">
        <?php if (empty($history)): ?>
            <div class="empty-state py-4">
                <i class="fas fa-address-book fs-1 d-block mb-3"></i>
                <p class="mb-0">Chưa có lần tra cứu nào.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Thời gian</th>
                            <th>Số</th>
                            <th>Nhà mạng</th>
                            <th>Họ tên</th>
                            <th>CCCD</th>
                            <th>Địa chỉ</th>
                            <th>Facebook</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $h): ?>
                            <tr>
                                <td class="small text-muted-2 font-numeric">
                                    <?= date('d/m/Y H:i', strtotime((string)$h['created_at'])) ?>
                                </td>
                                <td class="font-numeric small"><?= e((string)$h['phone']) ?></td>
                                <td class="small"><?= e((string)($h['carrier'] ?: '—')) ?></td>
                                <td class="small"><?= e((string)($h['full_name'] ?: '—')) ?></td>
                                <td class="font-numeric small"><?= e((string)($h['id_card'] ?: '—')) ?></td>
                                <td class="small text-truncate" style="max-width:220px">
                                    <?= e((string)($h['address'] ?: '—')) ?>
                                </td>
                                <td>
                                    <?php if (!empty($h['fb_url'])): ?>
                                        <a href="<?= e((string)$h['fb_url']) ?>" target="_blank" rel="noopener" class="text-gold">
                                            <i class="fab fa-facebook"></i>
                                        </a>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
