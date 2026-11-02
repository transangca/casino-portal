<?php /** @var array $posts */ $adminPage = 'news'; ?>
<h1 class="page-title">Tin tức / Sự kiện / Khuyến mãi</h1>
<p class="page-sub">Đăng bài viết kèm banner — bài ghim sẽ hiển thị nổi bật trên trang Tin tức.</p>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="panel-card">
            <h3><i class="fas fa-pen text-gold me-2"></i>Soạn bài viết</h3>

            <form method="POST" action="<?= url('/admin/news/save') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="0">

                <div class="mb-3">
                    <label class="form-label" for="title">Tiêu đề</label>
                    <input type="text" class="form-control" id="title" name="title" required maxlength="250">
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label" for="type">Loại</label>
                        <select class="form-select" id="type" name="type">
                            <?php foreach (\App\Services\NewsService::TYPES as $code => $label): ?>
                                <option value="<?= e($code) ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="published_at">Ngày đăng</label>
                        <input type="datetime-local" class="form-control" id="published_at" name="published_at">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="excerpt">Tóm tắt</label>
                    <input type="text" class="form-control" id="excerpt" name="excerpt" maxlength="500">
                </div>

                <div class="mb-3">
                    <label class="form-label" for="banner">Banner (URL)</label>
                    <input type="text" class="form-control" id="banner" name="banner" placeholder="https://.../event.jpg">
                </div>

                <div class="mb-3">
                    <label class="form-label" for="content">Nội dung</label>
                    <textarea class="form-control" id="content" name="content" rows="7"></textarea>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_pinned" name="is_pinned" value="1">
                            <label class="form-check-label" for="is_pinned">Ghim lên đầu</label>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" checked>
                            <label class="form-check-label" for="is_active">Hiển thị</label>
                        </div>
                    </div>
                </div>

                <button class="btn btn-primary w-100"><i class="fas fa-paper-plane me-2"></i>Đăng bài</button>
            </form>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="panel-card">
            <h3><i class="fas fa-newspaper text-gold me-2"></i>Bài đã đăng</h3>
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>Tiêu đề</th><th>Loại</th><th>Ngày</th><th>Trạng thái</th><th></th></tr></thead>
                    <tbody>
                    <?php if (!$posts): ?>
                        <tr><td colspan="5" class="text-center text-muted-2 py-4">Chưa có bài viết nào.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($posts as $p): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= e($p['title']) ?></div>
                                <?php if ($p['is_pinned']): ?>
                                    <span class="news-tag mt-1">Ghim</span>
                                <?php endif; ?>
                            </td>
                            <td class="small"><?= e(\App\Services\NewsService::typeLabel((string)$p['type'])) ?></td>
                            <td class="small text-muted-2"><?= e(date('d/m/Y', strtotime($p['published_at']))) ?></td>
                            <td>
                                <span class="badge-status st-<?= $p['is_active'] ? 'active' : 'closed' ?>">
                                    <?= $p['is_active'] ? 'Active' : 'Ẩn' ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <form method="POST" action="<?= url('/admin/news/delete') ?>"
                                      data-confirm="Xoá bài viết này?" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
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
