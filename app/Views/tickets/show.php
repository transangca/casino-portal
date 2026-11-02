<?php /** @var array $ticket,$messages,$categories,$priorities */ ?>
<?php
$statusMap = [
    'open'       => ['Chờ xử lý', 'st-processing'],
    'processing' => ['Đang xử lý', 'st-active'],
    'resolved'   => ['Đã xử lý', 'st-approved'],
    'closed'     => ['Đã đóng', 'st-closed'],
];
[$label, $cls] = $statusMap[$ticket['status']] ?? ['—', 'st-closed'];
?>
<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= url('/') ?>">Trang chủ</a></li>
            <li class="breadcrumb-item"><a href="<?= url('/tickets') ?>">Hỗ trợ</a></li>
            <li class="breadcrumb-item active"><?= e($ticket['code']) ?></li>
        </ol>
    </nav>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="casino-card mb-4">
                <div class="card-header d-flex align-items-center gap-2">
                    <i class="fas fa-ticket text-gold"></i>
                    <span><?= e($ticket['subject']) ?></span>
                    <span class="badge-status <?= e($cls) ?> ms-auto"><?= e($label) ?></span>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-3 small text-muted-2 mb-3">
                        <span><i class="fas fa-hashtag me-1"></i><b class="text-gold"><?= e($ticket['code']) ?></b></span>
                        <span><i class="fas fa-tag me-1"></i><?= e($categories[$ticket['category']] ?? $ticket['category']) ?></span>
                        <span><i class="fas fa-flag me-1"></i><?= e($priorities[$ticket['priority']] ?? $ticket['priority']) ?></span>
                        <span><i class="fas fa-clock me-1"></i><?= e(date('d/m/Y H:i', strtotime($ticket['created_at']))) ?></span>
                    </div>

                    <div class="ticket-thread">
                        <?php foreach ($messages as $m): ?>
                            <div class="ticket-msg <?= (int)$m['is_admin'] === 1 ? 'from-admin' : 'from-user' ?>">
                                <div class="msg-head">
                                    <i class="fas <?= (int)$m['is_admin'] === 1 ? 'fa-headset' : 'fa-user' ?> me-1"></i>
                                    <b><?= e((int)$m['is_admin'] === 1 ? 'Hỗ trợ' : ($m['full_name'] ?: $m['username'])) ?></b>
                                    <span class="ms-auto small text-muted-2"><?= e(date('d/m/Y H:i', strtotime($m['created_at']))) ?></span>
                                </div>
                                <div class="msg-body"><?= nl2br(e($m['message'])) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <?php if ($ticket['status'] !== 'closed'): ?>
                <div class="casino-card">
                    <div class="card-header d-flex align-items-center gap-2">
                        <i class="fas fa-reply text-gold"></i>
                        <span>Trả lời thêm</span>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="<?= url('/tickets/' . $ticket['code'] . '/reply') ?>">
                            <?= csrf_field() ?>
                            <textarea class="form-control mb-3" name="message" rows="4"
                                      placeholder="Nhập nội dung bổ sung..." required></textarea>
                            <button class="btn btn-gold"><i class="fas fa-paper-plane me-2"></i>Gửi trả lời</button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-secondary">
                    <i class="fas fa-lock me-2"></i>Ticket này đã đóng. Vui lòng tạo yêu cầu mới nếu cần hỗ trợ thêm.
                </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <div class="casino-card">
                <div class="card-header d-flex align-items-center gap-2">
                    <i class="fas fa-circle-info text-gold"></i>
                    <span>Thông tin</span>
                </div>
                <div class="card-body">
                    <table class="data-table">
                        <tbody>
                            <tr><td class="text-secondary">Mã ticket</td><td class="text-end"><code><?= e($ticket['code']) ?></code></td></tr>
                            <tr><td class="text-secondary">Loại</td><td class="text-end"><?= e($categories[$ticket['category']] ?? $ticket['category']) ?></td></tr>
                            <tr><td class="text-secondary">Ưu tiên</td><td class="text-end"><?= e($priorities[$ticket['priority']] ?? $ticket['priority']) ?></td></tr>
                            <tr><td class="text-secondary">Trạng thái</td><td class="text-end"><?= e($label) ?></td></tr>
                            <tr><td class="text-secondary">Ngày tạo</td><td class="text-end"><?= e(date('d/m/Y H:i', strtotime($ticket['created_at']))) ?></td></tr>
                            <?php if ($ticket['replied_at']): ?>
                                <tr><td class="text-secondary">Trả lời lúc</td><td class="text-end"><?= e(date('d/m/Y H:i', strtotime($ticket['replied_at']))) ?></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="casino-card mt-4">
                <div class="card-header d-flex align-items-center gap-2">
                    <i class="fas fa-headset text-gold"></i>
                    <span>Liên hệ nhanh</span>
                </div>
                <div class="card-body d-grid gap-2">
                    <a href="<?= e((string)setting('support_telegram', '')) ?>" class="btn btn-outline-light" target="_blank" rel="noopener">
                        <i class="fab fa-telegram-plane me-2"></i>Chat Telegram
                    </a>
                    <a href="<?= e((string)setting('support_zalo', '')) ?>" class="btn btn-outline-light" target="_blank" rel="noopener">
                        <i class="fas fa-comment-dots me-2"></i>Chat Zalo
                    </a>
                    <a href="tel:<?= e(preg_replace('/\D+/', '', (string)setting('support_hotline', ''))) ?>" class="btn btn-outline-gold">
                        <i class="fas fa-headset me-2"></i><?= e((string)setting('support_hotline', '')) ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
