<?php /** @var array $providers,$docs, bool $maintenance */ ?>
<div class="container py-4">
    <h1 class="page-title"><i class="fas fa-money-bill-transfer me-2"></i>API thanh toán &amp; API ngân hàng</h1>
    <p class="page-sub">Danh mục API thanh toán, tra cứu ngân hàng và đối soát tự động.</p>

    <div class="alert alert-warning">
        <i class="fas fa-screwdriver-wrench me-1"></i>
        <strong>Đang bảo trì / phát triển.</strong>
        Module này đã có sẵn kiến trúc &amp; tài liệu API. Tính năng sẽ được bật khi có sẵn sàng.
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="panel-card h-100">
                <h6 class="mb-3"><i class="fas fa-plug me-2 text-gold"></i>Cổng thanh toán</h6>
                <?php foreach ($providers as $p): ?>
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <div>
                            <strong><?= e((string)$p['name']) ?></strong>
                            <div class="small text-muted-2"><?= e((string)$p['desc']) ?></div>
                        </div>
                        <span class="badge bg-warning bg-opacity-25 text-warning"><?= e((string)$p['status']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="panel-card h-100">
                <h6 class="mb-3"><i class="fas fa-code me-2 text-gold"></i>Tài liệu endpoint</h6>
                <div class="table-responsive">
                    <table class="table table-sm small mb-0">
                        <thead><tr><th>Method</th><th>Path</th><th>Mô tả</th></tr></thead>
                        <tbody>
                            <?php foreach ($docs as $d): ?>
                                <tr>
                                    <td><span class="badge bg-secondary bg-opacity-25"><?= e((string)$d['method']) ?></span></td>
                                    <td class="font-monospace small"><?= e((string)$d['path']) ?></td>
                                    <td class="small text-muted-2"><?= e((string)$d['desc']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <a href="<?= url('/api/docs') ?>" class="btn btn-outline-gold btn-sm mt-3">
                    <i class="fas fa-book me-1"></i>Xem tài liệu API đầy đủ
                </a>
            </div>
        </div>
    </div>
</div>
