<?php /** @var array $campaigns @var array $logs */ $adminPage = 'campaigns'; ?>
<h1 class="page-title">Chiến dịch email (Broadcast)</h1>
<p class="page-sub">Gửi thông báo tới toàn bộ khách hàng hoặc nhóm mục tiêu cụ thể.</p>

<?php if (!\App\Services\MailerService::isConfigured()): ?>
    <div class="alert alert-warning">
        <i class="fas fa-triangle-exclamation me-1"></i>
        SMTP chưa được cấu hình. Hãy vào <strong>Admin → Cấu hình</strong> điền
        <code>smtp_host</code>, <code>smtp_port</code>, <code>smtp_username</code>,
        <code>smtp_password</code>, <code>mail_from_email</code> trước khi gửi.
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="panel-card">
            <h3><i class="fas fa-pen-fancy text-gold me-2"></i>Soạn chiến dịch</h3>

            <form method="POST" action="<?= url('/admin/campaigns/save') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="0">

                <div class="mb-3">
                    <label class="form-label" for="title">Tên chiến dịch</label>
                    <input type="text" class="form-control" id="title" name="title" required maxlength="200">
                </div>

                <div class="mb-3">
                    <label class="form-label" for="subject">Tiêu đề email</label>
                    <input type="text" class="form-control" id="subject" name="subject" required maxlength="255">
                </div>

                <div class="mb-3">
                    <label class="form-label" for="body">Nội dung</label>
                    <textarea class="form-control" id="body" name="body" rows="8"
                              placeholder="Nội dung thông báo tới khách hàng..."></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="banner">Banner (URL)</label>
                    <input type="text" class="form-control" id="banner" name="banner" placeholder="https://.../promo.jpg">
                </div>

                <div class="mb-3">
                    <label class="form-label" for="target">Đối tượng</label>
                    <select class="form-select" id="target" name="target">
                        <option value="all">Tất cả thành viên</option>
                        <option value="active">Tài khoản đang hoạt động</option>
                        <option value="vip">Thành viên VIP (từ cấp 1)</option>
                        <option value="deposited">Thành viên đã từng nạp tiền</option>
                    </select>
                </div>

                <button class="btn btn-primary w-100"><i class="fas fa-save me-2"></i>Lưu chiến dịch</button>
            </form>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="panel-card mb-4">
            <h3><i class="fas fa-paper-plane text-gold me-2"></i>Chiến dịch</h3>
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>Chiến dịch</th><th>Đối tượng</th><th class="text-end">Đã gửi</th><th>Trạng thái</th><th></th></tr></thead>
                    <tbody>
                    <?php if (!$campaigns): ?>
                        <tr><td colspan="5" class="text-center text-muted-2 py-4">Chưa có chiến dịch nào.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($campaigns as $c): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= e($c['title']) ?></div>
                                <div class="form-hint"><?= e($c['subject']) ?></div>
                            </td>
                            <td class="small"><?= e(['all' => 'Tất cả', 'active' => 'Hoạt động', 'vip' => 'VIP', 'deposited' => 'Đã nạp'][$c['target']] ?? $c['target']) ?></td>
                            <td class="text-end font-numeric small"><?= (int)$c['sent_count'] ?>/<?= (int)$c['total'] ?></td>
                            <td>
                                <span class="badge-status st-<?= $c['status'] === 'sent' ? 'approved' : ($c['status'] === 'sending' ? 'processing' : 'closed') ?>">
                                    <?= e(['draft' => 'Nháp', 'sending' => 'Đang gửi', 'sent' => 'Đã gửi'][$c['status']] ?? $c['status']) ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <?php if ($c['status'] !== 'sent'): ?>
                                    <form method="POST" action="<?= url('/admin/campaigns/send') ?>"
                                          data-confirm="Gửi chiến dịch này tới khách hàng?"
                                          class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                        <button class="btn btn-sm btn-gold"><i class="fas fa-send"></i></button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" action="<?= url('/admin/campaigns/delete') ?>"
                                      data-confirm="Xoá chiến dịch?" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel-card">
            <h3><i class="fas fa-list text-gold me-2"></i>Lịch sử gửi mail</h3>
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>Người nhận</th><th>Tiêu đề</th><th>Trạng thái</th><th>Thời gian</th></tr></thead>
                    <tbody>
                    <?php if (!$logs): ?>
                        <tr><td colspan="4" class="text-center text-muted-2 py-4">Chưa có mail nào được gửi.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($logs as $l): ?>
                        <tr>
                            <td class="small"><?= e($l['to_email']) ?></td>
                            <td class="small"><?= e($l['subject']) ?></td>
                            <td>
                                <span class="badge-status st-<?= $l['status'] === 'sent' ? 'approved' : 'rejected' ?>">
                                    <?= $l['status'] === 'sent' ? 'Đã gửi' : 'Lỗi' ?>
                                </span>
                            </td>
                            <td class="small text-muted-2"><?= e(date('d/m H:i', strtotime($l['created_at']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
