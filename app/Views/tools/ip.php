<?php /** @var string $ip, array $result */ ?>
<div class="panel-card mb-4">
    <form method="POST" class="row g-3 align-items-end">
        <?= csrf_field() ?>
        <div class="col-md-9">
            <label class="form-label">Địa chỉ IP</label>
            <input type="text" name="ip" class="form-control font-monospace" placeholder="8.8.8.8" value="<?= e($ip) ?>">
        </div>
        <div class="col-md-3">
            <button class="btn btn-gold w-100"><i class="fas fa-magnifying-glass me-2"></i>Tra cứu</button>
        </div>
    </form>
</div>

<?php if (!empty($result['ok'])): ?>
    <?php $d = $result['data']; ?>
    <div class="row g-3">
        <div class="col-md-6">
            <div class="panel-card h-100">
                <h6 class="mb-3"><i class="fas fa-network-wired me-2 text-gold"></i>Thông tin mạng</h6>
                <table class="table table-sm small mb-0">
                    <tr><td class="text-muted-2">IP</td><td class="font-monospace"><?= e((string)$d['ip']) ?></td></tr>
                    <tr><td class="text-muted-2">Phiên bản</td><td>IPv<?= (int)$d['version'] ?></td></tr>
                    <tr><td class="text-muted-2">Loại</td><td><?= $d['private'] ? 'IP nội bộ / riêng tư' : 'IP công cộng' ?></td></tr>
                    <?php if (!empty($d['reverse'])): ?>
                        <tr><td class="text-muted-2">Reverse DNS</td><td class="font-monospace small"><?= e((string)$d['reverse']) ?></td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
        <div class="col-md-6">
            <div class="panel-card h-100">
                <h6 class="mb-3"><i class="fas fa-earth-asia me-2 text-gold"></i>Vị trí &amp; ISP</h6>
                <?php if (!empty($d['geo'])): ?>
                    <table class="table table-sm small mb-0">
                        <tr><td class="text-muted-2">Quốc gia</td><td><?= e((string)$d['geo']['country']) ?></td></tr>
                        <tr><td class="text-muted-2">Tỉnh/TP</td><td><?= e((string)$d['geo']['region']) ?></td></tr>
                        <tr><td class="text-muted-2">Thành phố</td><td><?= e((string)$d['geo']['city']) ?></td></tr>
                        <tr><td class="text-muted-2">ISP</td><td><?= e((string)$d['geo']['isp']) ?></td></tr>
                        <tr><td class="text-muted-2">Tổ chức</td><td><?= e((string)$d['geo']['org']) ?></td></tr>
                        <tr><td class="text-muted-2">AS</td><td class="small"><?= e((string)$d['geo']['as']) ?></td></tr>
                    </table>
                <?php else: ?>
                    <p class="text-muted-2 small mb-0">Không lấy được dữ liệu vị trí từ dịch vụ công cộng.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php elseif (!empty($result['message'])): ?>
    <div class="alert alert-warning mb-0"><?= e((string)$result['message']) ?></div>
<?php endif; ?>
