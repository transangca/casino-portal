<?php /** @var array $ads */ $adminPage = 'ads'; ?>
<h1 class="page-title">Quảng cáo / Banner</h1>
<p class="page-sub">Chèn banner quảng cáo vào các vị trí trên website (ảnh hoặc video).</p>

<div class="row g-4">
    <!-- Form -->
    <div class="col-lg-5">
        <div class="panel-card">
            <h3><i class="fas fa-plus-circle text-gold me-2"></i>Thêm / Sửa banner</h3>

            <form method="POST" action="<?= url('/admin/ads/save') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)($_GET['edit'] ?? 0) ?>">

                <div class="mb-3">
                    <label class="form-label" for="title">Tiêu đề</label>
                    <input type="text" class="form-control" id="title" name="title" maxlength="150"
                           placeholder="VD: Khuyến mãi nạp lần đầu">
                </div>

                <div class="mb-3">
                    <label class="form-label" for="slot">Vị trí hiển thị</label>
                    <select class="form-select" id="slot" name="slot" required>
                        <?php foreach (\App\Services\AdService::SLOTS as $code => $label): ?>
                            <option value="<?= e($code) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="image_url">Ảnh (URL)</label>
                    <input type="text" class="form-control" id="image_url" name="image_url"
                           placeholder="https://.../banner.jpg">
                </div>

                <div class="mb-3">
                    <label class="form-label" for="video_url">Video (URL .mp4)</label>
                    <input type="text" class="form-control" id="video_url" name="video_url"
                           placeholder="https://.../intro.mp4">
                    <div class="form-hint mt-1">Ưu tiên video nếu có, video chạy tự động &amp; lặp.</div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label" for="link_url">Liên kết</label>
                        <input type="text" class="form-control" id="link_url" name="link_url"
                               placeholder="https://...">
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="position">Poster video</label>
                        <input type="text" class="form-control" id="position" name="position"
                               placeholder="https://.../poster.jpg">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="description">Mô tả ngắn</label>
                    <input type="text" class="form-control" id="description" name="description" maxlength="255">
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label" for="start_at">Bắt đầu</label>
                        <input type="datetime-local" class="form-control" id="start_at" name="start_at">
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="end_at">Kết thúc</label>
                        <input type="datetime-local" class="form-control" id="end_at" name="end_at">
                    </div>
                </div>

                <div class="row g-2 align-items-end mb-3">
                    <div class="col-6">
                        <label class="form-label" for="sort_order">Thứ tự</label>
                        <input type="number" class="form-control font-numeric" id="sort_order" name="sort_order" value="0">
                    </div>
                    <div class="col-6">
                        <div class="form-check mt-3">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" checked>
                            <label class="form-check-label" for="is_active">Hiển thị ngay</label>
                        </div>
                    </div>
                </div>

                <button class="btn btn-primary w-100"><i class="fas fa-save me-2"></i>Lưu banner</button>
            </form>
        </div>
    </div>

    <!-- Danh sách -->
    <div class="col-lg-7">
        <div class="panel-card">
            <h3><i class="fas fa-images text-gold me-2"></i>Danh sách banner</h3>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                    <tr><th>Banner</th><th>Vị trí</th><th class="text-end">Hiện / Click</th><th>Trạng thái</th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php if (!$ads): ?>
                        <tr><td colspan="5" class="text-center text-muted-2 py-4">Chưa có banner nào.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($ads as $a): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= e($a['title']) ?></div>
                                <div class="form-hint">
                                    <?= !empty($a['video_url']) ? 'Video' : 'Ảnh' ?>
                                    <?php if (!empty($a['link_url'])): ?>
                                        · <a href="<?= e($a['link_url']) ?>" target="_blank" rel="noopener">link</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="small"><?= e(\App\Services\AdService::slotLabel((string)$a['slot'])) ?></td>
                            <td class="text-end font-numeric small"><?= (int)$a['impressions'] ?> / <?= (int)$a['clicks'] ?></td>
                            <td>
                                <span class="badge-status st-<?= $a['is_active'] ? 'active' : 'closed' ?>">
                                    <?= $a['is_active'] ? 'Active' : 'Off' ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <form method="POST" action="<?= url('/admin/ads/delete') ?>"
                                      data-confirm="Xoá banner này?"
                                      class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
