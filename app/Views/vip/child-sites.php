<?php
/**
 * Quản lý website con (whitelabel).
 *
 * @var array  $sites
 * @var int    $level
 * @var int    $minVip
 * @var float  $maxShare
 * @var float  $myShare
 */
$canCreate = $level >= $minVip;
?>
<div class="container py-4">
    <h1 class="page-title"><i class="fas fa-sitemap text-gold me-2"></i>Website con</h1>
    <p class="page-sub">Tạo website con bán hàng của riêng bạn, ăn chia tối đa <?= number_format($maxShare * 100, 1) ?>%. Cần VIP <?= (int)$minVip ?> trở lên.</p>

    <div class="row g-4">
        <!-- Danh sách -->
        <div class="col-lg-7">
            <div class="panel-card">
                <h3 class="mb-3" style="font-size:1.05rem"><i class="fas fa-list text-gold me-2"></i>Website của bạn</h3>

                <?php if (!$sites): ?>
                    <div class="empty-state py-4">
                        <i class="fas fa-sitemap fs-1 d-block mb-3"></i>
                        <p class="mb-0">Bạn chưa có website con nào.</p>
                        <p class="form-hint mt-2 mb-0">Dùng form bên cạnh để tạo website con đầu tiên.</p>
                    </div>

                    <!-- Thẻ mẫu minh hoạ giao diện website con -->
                    <div class="row g-3 mt-1">
                        <?php foreach ([
                            ['name' => 'Website của tôi', 'slug' => 'ten-cua-toi', 'share' => 0.10, 'hits' => 0, 'domain' => '', 'status' => 'pending'],
                            ['name' => 'Sảnh game của tôi', 'slug' => 'sanh-cua-toi', 'share' => 0.12, 'hits' => 0, 'domain' => '', 'status' => 'pending'],
                        ] as $tpl): ?>
                            <div class="col-sm-6">
                                <div class="child-site-card child-site-card-empty h-100">
                                    <div class="cs-head">
                                        <span class="cs-logo">
                                            <i class="fas fa-sitemap"></i>
                                        </span>
                                        <span class="cs-title">
                                            <b><?= e($tpl['name']) ?></b>
                                            <span class="form-hint">/<?= e($tpl['slug']) ?></span>
                                        </span>
                                        <span class="badge-status st-pending">Mẫu</span>
                                    </div>
                                    <div class="cs-meta">
                                        <div><span>Ăn chia</span><b class="text-gold"><?= number_format($tpl['share'] * 100, 1) ?>%</b></div>
                                        <div><span>Lượt truy cập</span><b>0</b></div>
                                        <div><span>Nạp tối thiểu</span><b>Không yêu cầu</b></div>
                                        <div><span>Tên miền</span><b>Chưa gán</b></div>
                                    </div>
                                    <div class="cs-actions">
                                        <span class="form-hint ms-auto">
                                            <i class="fas fa-circle-info me-1"></i>Thẻ mẫu — tạo website để thay thế
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($sites as $s):
                            $st = (string)($s['status'] ?? 'pending');
                            $stMap = [
                                'active'    => ['Chạy', 'active'],
                                'pending'   => ['Chờ duyệt', 'pending'],
                                'suspended' => ['Tạm khoá', 'closed'],
                                'rejected'  => ['Từ chối', 'closed'],
                            ];
                            $label = $stMap[$st] ?? [ucfirst($st), 'pending'];
                            $logo  = trim((string)($s['logo'] ?? ''));
                            $banner= trim((string)($s['banner'] ?? ''));
                            ?>
                            <div class="col-sm-6">
                                <div class="child-site-card h-100">
                                    <div class="cs-head">
                                        <?php if ($banner !== ''): ?>
                                            <span class="cs-banner" style="background-image:url('<?= e($banner) ?>')"></span>
                                        <?php endif; ?>
                                        <span class="cs-logo">
                                            <?php if ($logo !== ''): ?>
                                                <img src="<?= e($logo) ?>" alt="" loading="lazy" decoding="async"
                                                     onerror="this.remove()">
                                            <?php else: ?>
                                                <i class="fas fa-sitemap"></i>
                                            <?php endif; ?>
                                        </span>
                                        <span class="cs-title">
                                            <b><?= e((string)($s['name'] ?: 'Website chưa đặt tên')) ?></b>
                                            <span class="form-hint">/<?= e((string)($s['slug'] ?: '')) ?></span>
                                        </span>
                                        <span class="badge-status st-<?= e($label[1]) ?>"><?= e($label[0]) ?></span>
                                    </div>

                                    <div class="cs-meta">
                                        <div>
                                            <span>Ăn chia</span>
                                            <b class="text-gold"><?= number_format((float)$s['rev_share'] * 100, 1) ?>%</b>
                                        </div>
                                        <div>
                                            <span>Lượt truy cập</span>
                                            <b><?= number_format((int)$s['hits']) ?></b>
                                        </div>
                                        <div>
                                            <span>Nạp tối thiểu</span>
                                            <b><?= $s['require_deposit'] ? money((float)$s['min_deposit']) : 'Không yêu cầu' ?></b>
                                        </div>
                                        <div>
                                            <span>Tên miền</span>
                                            <b class="text-truncate" style="max-width:140px">
                                                <?= e((string)($s['domain'] ?: 'Chưa gán')) ?>
                                            </b>
                                        </div>
                                    </div>

                                    <div class="cs-actions">
                                        <button class="btn btn-sm btn-outline-gold"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editSite<?= (int)$s['id'] ?>">
                                            <i class="fas fa-pen me-1"></i>Sửa
                                        </button>
                                        <?php if (!empty($s['slug'])): ?>
                                            <a class="btn btn-sm btn-outline-light" target="_blank" rel="noopener"
                                               href="<?= e(url('/' . (string)$s['slug'])) ?>">
                                                <i class="fas fa-arrow-up-right-from-square me-1"></i>Mở
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Modal sửa -->
                    <?php foreach ($sites as $s): ?>
                        <div class="modal fade" id="editSite<?= (int)$s['id'] ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content" style="background:var(--casino-card);border-color:var(--casino-line)">
                                    <form method="POST" action="<?= url('/child-sites/update') ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                        <div class="modal-header">
                                            <h5 class="modal-title" style="font-size:1rem">Sửa website con</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label class="form-label">Tên</label>
                                                <input type="text" class="form-control" name="name" value="<?= e($s['name']) ?>">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Tên miền riêng</label>
                                                <input type="text" class="form-control" name="domain" value="<?= e($s['domain'] ?? '') ?>" placeholder="VD: tenmien.com">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Logo (URL)</label>
                                                <input type="text" class="form-control" name="logo" value="<?= e($s['logo'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Banner (URL)</label>
                                                <input type="text" class="form-control" name="banner" value="<?= e($s['banner'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Giao diện</label>
                                                <select class="form-select" name="theme">
                                                    <option value="dark"   <?= $s['theme'] === 'dark' ? 'selected' : '' ?>>Tối</option>
                                                    <option value="light"  <?= $s['theme'] === 'light' ? 'selected' : '' ?>>Sáng</option>
                                                    <option value="system" <?= $s['theme'] === 'system' ? 'selected' : '' ?>>Theo hệ thống</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Đóng</button>
                                            <button class="btn btn-gold">Lưu</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tạo mới -->
        <div class="col-lg-5">
            <div class="panel-card">
                <h3 class="mb-3" style="font-size:1.05rem"><i class="fas fa-plus-circle text-gold me-2"></i><?= t('vip.create_child') ?></h3>

                <?php if (!$canCreate): ?>
                    <div class="alert alert-warning mb-0">
                        <i class="fas fa-lock me-2"></i><?= t('vip.child_site_locked') ?>
                        (hiện tại VIP <?= (int)$level ?>)
                    </div>
                <?php else: ?>
                    <form method="POST" action="<?= url('/child-sites') ?>">
                        <?= csrf_field() ?>

                        <div class="mb-3">
                            <label class="form-label" for="name">Tên website</label>
                            <input type="text" class="form-control" id="name" name="name" required
                                   placeholder="VD: Nhà cái của tôi">
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="slug">Mã định danh (URL)</label>
                            <input type="text" class="form-control" id="slug" name="slug"
                                   placeholder="VD: nha-cai-cua-toi">
                            <div class="form-hint mt-1">Để trống sẽ tự sinh từ tên.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="domain">Tên miền riêng</label>
                            <input type="text" class="form-control" id="domain" name="domain"
                                   placeholder="VD: tenmien.com">
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label" for="logo">Logo (URL)</label>
                                <input type="text" class="form-control" id="logo" name="logo">
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="banner">Banner (URL)</label>
                                <input type="text" class="form-control" id="banner" name="banner">
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label" for="theme">Giao diện</label>
                                <select class="form-select" id="theme" name="theme">
                                    <option value="dark">Tối</option>
                                    <option value="light">Sáng</option>
                                    <option value="system">Theo hệ thống</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="rev_share">% ăn chia</label>
                                <input type="number" class="form-control font-numeric" id="rev_share" name="rev_share"
                                       step="0.1" min="0" max="<?= $maxShare ?>" value="<?= $myShare ?>">
                                <div class="form-hint mt-1">Tối đa <?= number_format($myShare * 100, 1) ?>% theo cấp VIP của bạn.</div>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label" for="min_deposit">Nạp tối thiểu</label>
                                <input type="number" class="form-control font-numeric" id="min_deposit" name="min_deposit"
                                       step="1000" min="0" value="0">
                            </div>
                            <div class="col-6 d-flex align-items-end">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="require_deposit" name="require_deposit" value="1" checked>
                                    <label class="form-check-label" for="require_deposit">Bắt buộc nạp tiền</label>
                                </div>
                            </div>
                        </div>

                        <button class="btn btn-gold w-100 py-2 fw-bold">
                            <i class="fas fa-sitemap me-2"></i>Tạo website con
                        </button>
                        <p class="form-hint mt-2 mb-0">Website con sẽ ở trạng thái chờ duyệt. Admin duyệt và kích hoạt trong quản trị.</p>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
