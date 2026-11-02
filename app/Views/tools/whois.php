<?php /** @var string $domain, array $result */ ?>
<div class="panel-card mb-4">
    <form method="POST" class="row g-3 align-items-end">
        <?= csrf_field() ?>
        <div class="col-md-9">
            <label class="form-label">Tên miền</label>
            <input type="text" name="domain" class="form-control font-monospace" placeholder="example.com" value="<?= e($domain) ?>">
        </div>
        <div class="col-md-3">
            <button class="btn btn-gold w-100"><i class="fas fa-address-book me-2"></i>Tra cứu</button>
        </div>
    </form>
</div>

<?php if (!empty($result['ok'])): ?>
    <?php $d = $result['data']; ?>
    <div class="panel-card">
        <table class="table table-sm small mb-3">
            <tr><td class="text-muted-2" style="width:180px">Tên miền</td><td class="fw-semibold"><?= e((string)$d['domain']) ?></td></tr>
            <tr><td class="text-muted-2">Registrar</td><td><?= e((string)($d['registrar'] ?? '—')) ?></td></tr>
            <tr><td class="text-muted-2">Ngày tạo</td><td><?= e((string)($d['created'] ?? '—')) ?></td></tr>
            <tr><td class="text-muted-2">Ngày hết hạn</td><td><?= e((string)($d['expires'] ?? '—')) ?></td></tr>
            <tr><td class="text-muted-2">Cập nhật</td><td><?= e((string)($d['updated'] ?? '—')) ?></td></tr>
            <tr><td class="text-muted-2">Name server</td><td class="small"><?= e((string)($d['nameserver'] ?? '—')) ?></td></tr>
            <tr><td class="text-muted-2">Trạng thái</td><td class="small"><?= e((string)($d['status'] ?? '—')) ?></td></tr>
        </table>
        <details>
            <summary class="text-gold small">Xem dữ liệu WHOIS gốc</summary>
            <pre class="small mt-2 p-3 rounded" style="background:var(--surface-2);max-height:400px;overflow:auto"><?= e((string)$d['raw']) ?></pre>
        </details>
    </div>
<?php elseif (!empty($result['message'])): ?>
    <div class="alert alert-warning mb-0"><?= e((string)$result['message']) ?></div>
<?php endif; ?>
