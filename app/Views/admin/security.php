<?php /** @var array $logs */ $adminPage = 'security'; ?>
<h1 class="page-title">Nhật ký hoạt động &amp; bảo mật</h1>
<p class="page-sub">Toàn bộ hoạt động của hệ thống: đăng nhập, API, giao dịch, thuê key.</p>

<div class="panel-card">
    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>Thời gian</th><th>Phạm vi</th><th>Hành động</th><th>Đối tượng</th><th>IP</th><th>Chi tiết</th></tr></thead>
            <tbody>
            <?php if (!$logs): ?>
                <tr><td colspan="6" class="text-center text-muted-2 py-4">Chưa có hoạt động nào được ghi nhận.</td></tr>
            <?php endif; ?>
            <?php foreach ($logs as $l): ?>
                <tr>
                    <td class="small text-muted-2"><?= e(date('d/m H:i:s', strtotime($l['created_at']))) ?></td>
                    <td><span class="badge-status st-active"><?= e($l['scope']) ?></span></td>
                    <td class="small"><?= e($l['action']) ?></td>
                    <td class="small"><?= e((string)($l['subject'] ?? '—')) ?></td>
                    <td class="small font-numeric"><?= e((string)($l['ip'] ?? '—')) ?></td>
                    <td class="small text-muted-2" style="max-width:280px;word-break:break-all">
                        <?= e(mb_substr((string)($l['detail'] ?? ''), 0, 120)) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
