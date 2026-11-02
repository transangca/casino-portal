<?php /** @var array $types,$docs, string $currentType */ ?>
<div class="pos-header">
    <div class="pos-header-left">
        <h1>Phiếu kế toán</h1>
    </div>
</div>

<div class="p-3 p-lg-4">
    <div class="d-flex flex-wrap gap-2 mb-3">
        <a href="<?= url('/services/accounting/documents') ?>" class="filter-chip <?= $currentType === '' ? 'active' : '' ?>">Tất cả</a>
        <?php foreach ($types as $code => $label): ?>
            <a href="<?= url('/services/accounting/documents?type=' . urlencode($code)) ?>" class="filter-chip <?= $currentType === $code ? 'active' : '' ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </div>

    <div class="panel-card">
        <table class="table table-sm small mb-0">
            <thead>
                <tr>
                    <th>Mã phiếu</th>
                    <th>Loại</th>
                    <th>Ngày</th>
                    <th>Đối tượng</th>
                    <th class="text-end">Tổng Nợ</th>
                    <th>Trạng thái</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($docs as $d): ?>
                    <tr>
                        <td class="font-monospace"><?= e((string)$d['doc_no']) ?></td>
                        <td><?= e($types[$d['doc_type']] ?? (string)$d['doc_type']) ?></td>
                        <td><?= e(date('d/m/Y', strtotime((string)$d['doc_date']))) ?></td>
                        <td><?= e((string)($d['partner'] ?: '—')) ?></td>
                        <td class="text-end"><?= money((float)$d['total_debit']) ?></td>
                        <td><span class="badge bg-success bg-opacity-25 text-success"><?= e((string)$d['status']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$docs): ?>
                    <tr><td colspan="6" class="text-muted-2 text-center py-4">Chưa có phiếu nào.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
