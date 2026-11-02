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
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <a href="<?= url('/admin/tickets') ?>" class="text-muted-2 small">
            <i class="fas fa-arrow-left me-1"></i>Quay lại danh sách
        </a>
        <h1 class="h4 fw-bold mb-1 mt-2"><?= e($ticket['subject']) ?></h1>
        <p class="text-muted-2 mb-0">
            Mã <code class="text-gold"><?= e($ticket['code']) ?></code> ·
            <span class="badge-status <?= e($cls) ?>"><?= e($label) ?></span>
        </p>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="casino-card">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="fas fa-comments text-gold"></i>
                <span>Nội dung trao đổi</span>
            </div>
            <div class="card-body">
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

        <div class="casino-card mt-4">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="fas fa-reply text-gold"></i>
                <span>Trả lời khách hàng</span>
            </div>
            <div class="card-body">
                <form method="POST" action="<?= url('/admin/tickets/' . $ticket['code'] . '/reply') ?>">
                    <?= csrf_field() ?>
                    <textarea class="form-control mb-3" name="message" rows="5"
                              placeholder="Nhập nội dung trả lời cho khách hàng..."><?= e($ticket['admin_reply'] ?? '') ?></textarea>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <label class="form-label mb-0 me-2 small text-muted-2" for="status">Trạng thái sau khi trả lời</label>
                        <select class="form-select form-select-sm w-auto" id="status" name="status">
                            <?php foreach ($statusMap as $key => [$txt, $c]): ?>
                                <option value="<?= e($key) ?>" <?= $ticket['status'] === $key ? 'selected' : '' ?>><?= e($txt) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-gold ms-auto"><i class="fas fa-paper-plane me-2"></i>Gửi trả lời</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="casino-card">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="fas fa-user text-gold"></i>
                <span>Khách hàng</span>
            </div>
            <div class="card-body">
                <table class="data-table">
                    <tbody>
                        <tr><td class="text-secondary">Tài khoản</td><td class="text-end"><?= e($ticket['username']) ?></td></tr>
                        <tr><td class="text-secondary">Họ tên</td><td class="text-end"><?= e($ticket['full_name'] ?: '—') ?></td></tr>
                        <tr><td class="text-secondary">Email</td><td class="text-end"><?= e($ticket['email']) ?></td></tr>
                        <tr><td class="text-secondary">Số dư</td><td class="text-end text-gold"><?= money((float)$ticket['balance']) ?></td></tr>
                        <tr><td class="text-secondary">Loại vấn đề</td><td class="text-end"><?= e($categories[$ticket['category']] ?? $ticket['category']) ?></td></tr>
                        <tr><td class="text-secondary">Ưu tiên</td><td class="text-end"><?= e($priorities[$ticket['priority']] ?? $ticket['priority']) ?></td></tr>
                        <tr><td class="text-secondary">Ngày tạo</td><td class="text-end"><?= e(date('d/m/Y H:i', strtotime($ticket['created_at']))) ?></td></tr>
                    </tbody>
                </table>
                <a href="<?= url('/admin/users?q=' . urlencode($ticket['email'])) ?>" class="btn btn-outline-light btn-sm w-100 mt-3">
                    <i class="fas fa-users me-2"></i>Xem hồ sơ người dùng
                </a>
            </div>
        </div>
    </div>
</div>
