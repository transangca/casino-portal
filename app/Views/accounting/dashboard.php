<?php /** @var array $pl,$bs,$docs */ ?>
<div class="pos-header">
    <div class="pos-header-left">
        <h1>Tổng quan</h1>
        <div class="pos-stats">
            <span><i class="fas fa-sack-dollar"></i> Doanh thu: <strong><?= money((float)$pl['revenue']) ?></strong></span>
            <span><i class="fas fa-file-invoice-dollar"></i> Chi phí: <strong><?= money((float)$pl['expense']) ?></strong></span>
            <span><i class="fas fa-chart-line"></i> Lợi nhuận: <strong><?= money((float)$pl['profit']) ?></strong></span>
            <span><i class="fas fa-scale-balanced"></i> Tổng tài sản: <strong><?= money((float)$bs['total_asset']) ?></strong></span>
        </div>
    </div>
</div>

<div class="p-3 p-lg-4">
    <div class="row g-3">
        <div class="col-md-6">
            <div class="panel-card h-100">
                <h6 class="mb-3"><i class="fas fa-scale-balanced me-2 text-gold"></i>Bảng cân đối tài sản – nguồn vốn</h6>
                <div class="row g-3">
                    <div class="col-6">
                        <div class="acc-block-title">Tài sản</div>
                        <table class="table table-sm small mb-0">
                            <?php foreach ($bs['assets'] as $a): ?>
                                <tr>
                                    <td><?= e($a['code']) ?> · <?= e($a['name']) ?></td>
                                    <td class="text-end"><?= money((float)$a['balance']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="fw-semibold">
                                <td>Tổng tài sản</td>
                                <td class="text-end"><?= money((float)$bs['total_asset']) ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-6">
                        <div class="acc-block-title">Nguồn vốn</div>
                        <table class="table table-sm small mb-0">
                            <?php foreach ($bs['liab'] as $a): ?>
                                <tr>
                                    <td><?= e($a['code']) ?> · <?= e($a['name']) ?></td>
                                    <td class="text-end"><?= money((float)$a['balance']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php foreach ($bs['equity'] as $a): ?>
                                <tr>
                                    <td><?= e($a['code']) ?> · <?= e($a['name']) ?></td>
                                    <td class="text-end"><?= money((float)$a['balance']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="fw-semibold">
                                <td>Tổng nguồn vốn</td>
                                <td class="text-end"><?= money((float)$bs['total_liab'] + (float)$bs['total_equity']) ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="panel-card h-100">
                <h6 class="mb-3"><i class="fas fa-receipt me-2 text-gold"></i>Phiếu gần đây</h6>
                <table class="table table-sm small mb-0">
                    <?php foreach ($docs as $d): ?>
                        <tr>
                            <td class="font-monospace"><?= e((string)$d['doc_no']) ?></td>
                            <td><?= e(\App\Services\AccountingService::DOC_TYPES[$d['doc_type']] ?? $d['doc_type']) ?></td>
                            <td class="text-muted-2"><?= e(date('d/m/Y', strtotime((string)$d['doc_date']))) ?></td>
                            <td class="text-end"><?= money((float)$d['total_debit']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$docs): ?>
                        <tr><td colspan="4" class="text-muted-2 text-center py-3">Chưa có phiếu nào.</td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
</div>
