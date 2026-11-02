<?php /** @var array $project,$platforms */ ?>
<div class="container py-4">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= url('/services/website-builder') ?>">Thiết kế website</a></li>
            <li class="breadcrumb-item active"><?= e($project['name']) ?></li>
        </ol>
    </nav>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="panel-card">
                <h5 class="mb-3"><?= e($project['name']) ?></h5>
                <table class="table table-sm small">
                    <tr><td class="text-muted-2" style="width:160px">Nền tảng</td><td><?= e($platforms[$project['platform']] ?? $project['platform']) ?></td></tr>
                    <tr><td class="text-muted-2">Domain</td><td><?= e((string)($project['domain'] ?: '—')) ?></td></tr>
                    <tr><td class="text-muted-2">Email</td><td><?= e((string)($project['email'] ?: '—')) ?></td></tr>
                    <tr><td class="text-muted-2">Telegram</td><td><?= e((string)($project['telegram'] ?: '—')) ?></td></tr>
                    <tr><td class="text-muted-2">Giá</td><td class="text-gold fw-semibold"><?= money((float)$project['price']) ?></td></tr>
                    <tr><td class="text-muted-2">Trạng thái</td><td><?= e(\App\Services\WebsiteBuilderService::statusLabel((string)$project['status'])) ?></td></tr>
                </table>

                <?php if ($project['status'] === 'draft'): ?>
                    <form method="POST" action="<?= url('/services/website-builder/pay') ?>" class="mt-3">
                        <?= csrf_field() ?>
                        <input type="hidden" name="project_id" value="<?= (int)$project['id'] ?>">
                        <button class="btn btn-gold w-100">
                            <i class="fas fa-wallet me-1"></i>Thanh toán <?= money((float)$project['price']) ?> để tiếp tục
                        </button>
                    </form>
                <?php elseif (!empty($project['demo_url'])): ?>
                    <button class="btn btn-gold w-100 mt-3" data-bs-toggle="modal" data-bs-target="#demoModal">
                        <i class="fas fa-display me-1"></i>Xem demo dự án
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="panel-card">
                <h6 class="mb-3"><i class="fas fa-address-card me-2"></i>Liên hệ admin</h6>
                <?php
                $tg = (string)setting('support_telegram', '');
                $zl = (string)setting('support_zalo', '');
                $hl = (string)setting('support_hotline', '');
                ?>
                <table class="table table-sm small mb-0">
                    <?php if ($tg !== ''): ?>
                        <tr><td class="text-muted-2">Telegram</td><td><a href="<?= e($tg) ?>" target="_blank" rel="noopener"><?= e($tg) ?></a></td></tr>
                    <?php endif; ?>
                    <?php if ($zl !== ''): ?>
                        <tr><td class="text-muted-2">Zalo</td><td><?= e($zl) ?></td></tr>
                    <?php endif; ?>
                    <?php if ($hl !== ''): ?>
                        <tr><td class="text-muted-2">Hotline</td><td><?= e($hl) ?></td></tr>
                    <?php endif; ?>
                </table>
                <?php if ($tg === '' && $zl === '' && $hl === ''): ?>
                    <p class="small text-muted-2 mb-0">Admin chưa cập nhật thông tin liên hệ.</p>
                <?php endif; ?>
            </div>

            <?php if (!empty($project['description'])): ?>
                <div class="panel-card mt-4">
                    <h6 class="mb-2"><i class="fas fa-align-left me-2"></i>Yêu cầu</h6>
                    <p class="small mb-0"><?= e((string)$project['description']) ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!empty($project['demo_url'])): ?>
<div class="modal fade" id="demoModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Demo — <?= e($project['name']) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body p-0">
                <iframe src="<?= e((string)$project['demo_url']) ?>" style="width:100%;height:70vh;border:0"
                        title="Demo dự án" allowfullscreen></iframe>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
