<?php
/**
 * Dịch vụ check thông tin qua số điện thoại.
 *
 * @var array $history
 * @var float $price
 * @var float $balance
 */
$single  = $_SESSION['phone_check_result'] ?? null;
$batch   = $_SESSION['phone_check_batch'] ?? null;
unset($_SESSION['phone_check_result'], $_SESSION['phone_check_batch']);
?>
<div class="container py-4">
    <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
        <div class="flex-fill">
            <h1 class="page-title mb-1"><i class="fas fa-mobile-screen-button me-2 text-gold"></i>Check thông tin số điện thoại</h1>
            <p class="page-sub mb-0">
                Nhập số điện thoại → hệ thống tra cứu <strong>tên chủ sở hữu, CCCD, địa chỉ</strong>
                và trích xuất <strong>link Facebook</strong> tìm kiếm từ số đó.
            </p>
        </div>
        <div class="d-flex gap-2">
            <div class="stat-card text-center" style="min-width:130px">
                <div class="stat-value" style="font-size:1.3rem"><?= money($price) ?></div>
                <div class="stat-label">Giá / lần</div>
            </div>
            <div class="stat-card text-center" style="min-width:130px">
                <div class="stat-value" style="font-size:1.3rem"><?= money($balance) ?></div>
                <div class="stat-label">Số dư</div>
            </div>
        </div>
    </div>

    <div class="alert alert-secondary small">
        <i class="fas fa-shield-halved me-1"></i>
        Chỉ tra cứu thông tin công khai của số điện thoại bạn có quyền hợp pháp.
        Mọi lần tra cứu đều được ghi nhật ký để quản trị đối soát.
    </div>

    <div class="row g-4">
        <!-- ===== Form tra cứu 1 số ===== -->
        <div class="col-lg-5">
            <div class="panel-card mb-4">
                <h6 class="mb-3"><i class="fas fa-magnifying-glass me-2"></i>Tra cứu 1 số điện thoại</h6>

                <form method="POST" action="<?= url('/services/phone-check/check') ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="phone">Số điện thoại</label>
                        <input type="tel" id="phone" name="phone" class="form-control font-numeric"
                               placeholder="0912345678" maxlength="15" required>
                        <div class="form-hint mt-1">Nhập 10 số. Hệ thống tự chuẩn hoá (+84, 84…, 0…).</div>
                    </div>
                    <button class="btn btn-gold w-100">
                        <i class="fas fa-magnifying-glass-chart me-1"></i>Tra cứu ngay (<?= money($price) ?>)
                    </button>
                </form>
            </div>

            <div class="panel-card">
                <h6 class="mb-3"><i class="fas fa-layer-group me-2"></i>Tra cứu hàng loạt</h6>
                <form method="POST" action="<?= url('/services/phone-check/batch') ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="target_list">Danh sách số</label>
                        <textarea id="target_list" name="target_list" class="form-control font-numeric"
                                  rows="7" placeholder="0912345678&#10;0987654321&#10;0905112233"></textarea>
                        <div class="form-hint mt-1">
                            Tối đa 100 số / lần. Có thể ghi thêm tên sau dấu <code>|</code>:
                            <code>0912345678|Nguyễn A</code>
                        </div>
                    </div>
                    <button class="btn btn-outline-gold w-100">
                        <i class="fas fa-list me-1"></i>Tra cứu hàng loạt
                    </button>
                </form>
            </div>
        </div>

        <!-- ===== Kết quả ===== -->
        <div class="col-lg-7">
            <?php if ($single): ?>
                <div class="panel-card mb-4">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <span class="brand-icon brand-icon-lg"><i class="fas fa-user-secret"></i></span>
                        <div class="flex-fill min-w-0">
                            <h5 class="mb-1 font-numeric"><?= e((string)$single['phone']) ?></h5>
                            <div class="form-hint">
                                <?= e((string)($single['carrier'] ?: 'Không xác định nhà mạng')) ?>
                            </div>
                        </div>
                        <span class="badge-status <?= ($single['status'] ?? '') === 'found' ? 'st-success' : 'st-warning' ?>">
                            <?= ($single['status'] ?? '') === 'found' ? 'Đã tìm thấy' : 'Chỉ có tin cơ bản' ?>
                        </span>
                    </div>

                    <div class="row g-2">
                        <div class="col-sm-6">
                            <div class="info-row">
                                <span class="info-label"><i class="fas fa-user me-1"></i>Họ và tên</span>
                                <span class="info-value"><?= e((string)($single['full_name'] ?: 'Không có dữ liệu công khai')) ?></span>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="info-row">
                                <span class="info-label"><i class="fas fa-id-card me-1"></i>Số CCCD</span>
                                <span class="info-value font-numeric"><?= e((string)($single['id_card'] ?: 'Không có dữ liệu công khai')) ?></span>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="info-row">
                                <span class="info-label"><i class="fas fa-location-dot me-1"></i>Địa chỉ</span>
                                <span class="info-value"><?= e((string)($single['address'] ?: 'Không có dữ liệu công khai')) ?></span>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="info-row">
                                <span class="info-label"><i class="fas fa-mobile me-1"></i>Nhà mạng</span>
                                <span class="info-value"><?= e((string)($single['carrier'] ?: '—')) ?></span>
                            </div>
                        </div>
                    </div>

                    <hr class="footer-divider">

                    <h6 class="mb-2 small"><i class="fas fa-magnifying-glass me-1"></i>Liên kết tra cứu thêm</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="<?= e((string)$single['fb_url']) ?>" target="_blank" rel="noopener" class="btn btn-soft btn-sm">
                            <i class="fab fa-facebook me-1"></i>Tìm Facebook theo số
                        </a>
                        <a href="<?= e((string)$single['google_url']) ?>" target="_blank" rel="noopener" class="btn btn-soft btn-sm">
                            <i class="fas fa-google me-1"></i>Tìm trên Google
                        </a>
                        <button type="button" class="btn btn-outline-light btn-sm copy-btn"
                                data-copy="<?= e((string)$single['fb_url']) ?>">
                            <i class="fas fa-copy me-1"></i>Sao chép link FB
                        </button>
                    </div>

                    <?php if (!empty($single['sources'])): ?>
                        <hr class="footer-divider">
                        <h6 class="mb-2 small text-muted-2">Nguồn tra cứu đã thử</h6>
                        <ul class="small mb-0 ps-3">
                            <?php foreach ($single['sources'] as $src): ?>
                                <li>
                                    <strong><?= e((string)$src['name']) ?></strong> —
                                    <span class="text-muted-2"><?= e((string)($src['detail'] ?? '')) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($batch): ?>
                <div class="panel-card mb-4">
                    <h6 class="mb-3"><i class="fas fa-layer-group me-2"></i>Kết quả hàng loạt (<?= count($batch) ?>)</h6>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Số</th>
                                    <th>Nhà mạng</th>
                                    <th>Họ tên</th>
                                    <th>CCCD</th>
                                    <th>Facebook</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($batch as $r): ?>
                                    <tr>
                                        <td class="font-numeric"><?= e((string)$r['phone']) ?></td>
                                        <td><?= e((string)($r['carrier'] ?? '—')) ?></td>
                                        <td><?= e((string)($r['full_name'] ?: '—')) ?></td>
                                        <td class="font-numeric"><?= e((string)($r['id_card'] ?: '—')) ?></td>
                                        <td>
                                            <?php if (!empty($r['fb_url'])): ?>
                                                <a href="<?= e((string)$r['fb_url']) ?>" target="_blank" rel="noopener"
                                                   class="text-gold"><i class="fab fa-facebook"></i></a>
                                            <?php else: ?>—<?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Lịch sử -->
            <div class="panel-card">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h6 class="mb-0"><i class="fas fa-clock-rotate-left me-2"></i>Lịch sử tra cứu</h6>
                    <a href="<?= url('/services/phone-check/history') ?>" class="btn btn-outline-light btn-sm">Xem tất cả</a>
                </div>

                <?php if (empty($history)): ?>
                    <div class="empty-state py-4">
                        <i class="fas fa-address-book fs-1 d-block mb-3"></i>
                        <p class="mb-0">Chưa có lần tra cứu nào.</p>
                    </div>
                <?php else: ?>
                    <div class="aic-order-list">
                        <?php foreach (array_slice($history, 0, 20) as $h): ?>
                            <div class="aic-order-item">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fas fa-mobile-screen text-gold"></i>
                                    <div class="min-w-0 flex-fill">
                                        <div class="small fw-semibold font-numeric"><?= e((string)$h['phone']) ?></div>
                                        <div class="form-hint text-truncate">
                                            <?= e((string)($h['full_name'] ?: ($h['carrier'] ?: 'Không có dữ liệu'))) ?>
                                        </div>
                                    </div>
                                    <span class="badge-status <?= $h['status'] === 'found' ? 'st-success' : 'st-warning' ?>">
                                        <?= e((string)$h['status']) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
