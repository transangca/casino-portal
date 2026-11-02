<?php /** @var array $settings */ $adminPage = 'settings'; ?>
<h1 class="page-title">Cấu hình hệ thống</h1>
<p class="page-sub">Các tham số vận hành của website.</p>

<div class="panel-card">
    <form method="POST" action="<?= url('/admin/settings/save') ?>">
        <?= csrf_field() ?>
        <div class="row g-3">
            <?php foreach ($settings as $s): ?>
                <div class="col-md-6">
                    <label class="form-label" for="s_<?= e($s['name']) ?>"><?= e($s['name']) ?></label>
                    <input type="text" class="form-control" id="s_<?= e($s['name']) ?>"
                           name="settings[<?= e($s['name']) ?>]" value="<?= e($s['value']) ?>">
                </div>
            <?php endforeach; ?>
        </div>
        <button class="btn btn-primary mt-4">Lưu cấu hình</button>
    </form>
</div>

<div class="panel-card">
    <h3><i class="fas fa-terminal text-gold me-2"></i>Operator API endpoints</h3>
    <p class="text-secondary small">Các endpoint này được game server gọi để đồng bộ số dư hai chiều.</p>
    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>Endpoint</th><th>Method</th><th>Mô tả</th></tr></thead>
            <tbody>
                <tr><td><code><?= e(url('/operator/api/verifySession')) ?></code></td><td>POST</td><td class="text-secondary">Xác thực token người chơi</td></tr>
                <tr><td><code><?= e(url('/operator/api/wallet/get')) ?></code></td><td>POST</td><td class="text-secondary">Lấy số dư hiện tại</td></tr>
                <tr><td><code><?= e(url('/operator/api/wallet/change')) ?></code></td><td>POST</td><td class="text-secondary">Cập nhật số dư (cược/thắng)</td></tr>
                <tr><td><code><?= e(url('/operator/api/game/info')) ?></code></td><td>POST</td><td class="text-secondary">Thông tin game</td></tr>
                <tr><td><code><?= e(url('/operator/api/game/list')) ?></code></td><td>GET</td><td class="text-secondary">Danh sách game theo sảnh</td></tr>
            </tbody>
        </table>
    </div>
</div>
