<?php /** @var array $list */ $adminPage = 'cso'; ?>
<h1 class="page-title">Quản lý CSO</h1>
<p class="page-sub">Danh sách đối tác và hoá hồng đang chờ thành toán.</p>

<div class="panel-card">
    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>User</th><th>Tên</th><th>Mã</th><th class="text-end">Tỷ lệ</th><th class="text-end">Doanh thu</th><th class="text-end">Hoá hồng</th><th>Thao tác</th></tr></thead>
            <tbody>
            <?php foreach ($list as $c): ?>
                <tr>
                    <td><?= e($c['username']) ?></td>
                    <td><?= e($c['name']) ?></td>
                    <td><code><?= e($c['code']) ?></code></td>
                    <td class="text-end"><?= (float)$c['commission_rate'] * 100 ?>%</td>
                    <td class="text-end"><?= money((float)$c['total_revenue']) ?></td>
                    <td class="text-end text-gold"><?= money((float)$c['total_commission']) ?></td>
                    <td>
                        <form method="POST" action="<?= url('/admin/cso/pay') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                            <button class="btn btn-sm btn-primary" data-confirm="Thanh toan hoa hong cho CSO nay?">Thành toán</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
