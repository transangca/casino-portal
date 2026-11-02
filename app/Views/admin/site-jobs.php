<?php
/**
 * Quản trị dự án website (copy 1-1 + tạo A-Z).
 *
 * @var array $clones
 * @var array $builds
 */
$adminPage = 'site-jobs';
?>
<h1 class="page-title">Dự án website</h1>
<p class="page-sub">Theo dõi các dự án copy 1-1 trang web và website tạo tự động A-Z của khách hàng.</p>

<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link active" href="<?= url('/admin/site-jobs') ?>">
            <i class="fas fa-clone me-1"></i>Copy 1-1 (<?= count($clones) ?>)
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="<?= url('/admin/site-jobs?tab=auto') ?>">
            <i class="fas fa-wand-sparkles me-1"></i>Tạo A-Z (<?= count($builds) ?>)
        </a>
    </li>
</ul>

<?php $tab = (string)($_GET['tab'] ?? 'clone'); ?>

<?php if ($tab !== 'auto'): ?>
    <div class="panel-card">
        <h3><i class="fas fa-clone text-gold me-2"></i>Dự án copy 1-1</h3>

        <?php if (empty($clones)): ?>
            <div class="empty-state py-4"><i class="fas fa-inbox"></i><p>Chưa có dự án nào.</p></div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th><th>User</th><th>Website</th><th>Trang</th>
                            <th>Asset</th><th>Dung lượng</th><th>Trạng thái</th><th>Thời gian</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($clones as $c): ?>
                        <?php
                        $st = (string)$c['status'];
                        $cls = match ($st) {
                            'done'     => 'st-success',
                            'failed'   => 'st-danger',
                            'scanning' => 'st-processing',
                            default    => 'st-closed',
                        };
                        $label = match ($st) {
                            'done'     => 'Xong',
                            'failed'   => 'Lỗi',
                            'scanning' => 'Đang chạy',
                            default    => 'Chờ',
                        };
                        ?>
                        <tr>
                            <td class="text-secondary small"><?= (int)$c['id'] ?></td>
                            <td class="small"><?= e((string)($c['username'] ?? '—')) ?></td>
                            <td class="small text-truncate" style="max-width:220px">
                                <?= e((string)($c['title'] ?: $c['host'])) ?>
                            </td>
                            <td class="small font-numeric"><?= (int)$c['pages_total'] ?></td>
                            <td class="small font-numeric"><?= (int)$c['assets_total'] ?></td>
                            <td class="small font-numeric"><?= number_format((int)$c['zip_size'] / 1048576, 2) ?> MB</td>
                            <td><span class="badge-status <?= $cls ?>"><?= $label ?></span></td>
                            <td class="small text-muted-2 font-numeric">
                                <?= date('d/m/Y H:i', strtotime((string)$c['created_at'])) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="panel-card">
        <h3><i class="fas fa-wand-sparkles text-gold me-2"></i>Dự án tạo website A-Z</h3>

        <?php if (empty($builds)): ?>
            <div class="empty-state py-4"><i class="fas fa-inbox"></i><p>Chưa có dự án nào.</p></div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr><th>#</th><th>User</th><th>Tên</th><th>Domain</th><th>Chủ đề</th><th>Màu</th><th>Trang</th><th>Trạng thái</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($builds as $b): ?>
                        <?php
                        $st = (string)$b['status'];
                        $cls = match ($st) {
                            'done'     => 'st-success',
                            'failed'   => 'st-danger',
                            'building' => 'st-processing',
                            default    => 'st-closed',
                        };
                        $label = match ($st) {
                            'done'     => 'Xong',
                            'failed'   => 'Lỗi',
                            'building' => 'Đang tạo',
                            default    => 'Chờ',
                        };
                        $pages = json_decode((string)($b['pages'] ?? ''), true);
                        ?>
                        <tr>
                            <td class="text-secondary small"><?= (int)$b['id'] ?></td>
                            <td class="small"><?= e((string)($b['username'] ?? '—')) ?></td>
                            <td class="small fw-semibold"><?= e((string)$b['name']) ?></td>
                            <td class="small text-muted-2"><?= e((string)($b['domain'] ?: '—')) ?></td>
                            <td class="small"><?= e((string)$b['category']) ?></td>
                            <td class="small"><?= e((string)$b['theme']) ?></td>
                            <td class="small font-numeric"><?= is_array($pages) ? count($pages) : 0 ?></td>
                            <td><span class="badge-status <?= $cls ?>"><?= $label ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
