<?php
/**
 * CV Builder — nhiều mẫu, avatar, mục tùy chỉnh.
 *
 * @var array $profiles,$editing,$templates,$sections,$fonts
 */
$selTemplate = (string)($editing['template'] ?? 'modern');
$selSections = json_decode((string)($editing['sections'] ?? '[]'), true) ?: ['summary', 'experience', 'education', 'skills', 'languages'];
$selLinks    = json_decode((string)($editing['links'] ?? '[]'), true) ?: [];
?>
<div class="container py-4">
    <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
        <div>
            <h1 class="page-title mb-1"><i class="fas fa-file-signature me-2 text-gold"></i>Tạo CV</h1>
            <p class="page-sub mb-0">Chọn mẫu, thêm ảnh đại diện, điền thông tin và tinh chỉnh theo ý bạn.</p>
        </div>
        <?php if ($editing): ?>
            <a href="<?= url('/services/cv/view/' . (int)$editing['id']) ?>" class="btn btn-outline-gold btn-sm ms-auto">
                <i class="fas fa-eye me-1"></i>Xem trước CV
            </a>
        <?php endif; ?>
    </div>

    <form method="POST" action="<?= url('/services/cv/save') ?>"
          enctype="multipart/form-data" class="row g-4" id="cvForm">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">

        <!-- ============ Cột trái: form ============ -->
        <div class="col-lg-8">
            <!-- Chọn mẫu -->
            <div class="panel-card mb-4">
                <h6 class="mb-3"><i class="fas fa-layer-group me-2 text-gold"></i>Chọn mẫu CV</h6>
                <div class="cv-template-grid">
                    <?php foreach ($templates as $code => $tpl): ?>
                        <label class="cv-template-item">
                            <input type="radio" name="template" value="<?= e($code) ?>"
                                   class="d-none" <?= $selTemplate === $code ? 'checked' : '' ?>
                                   data-accent="<?= e($tpl['accent']) ?>">
                            <span class="cv-template-box">
                                <i class="fas <?= e($tpl['icon']) ?>"></i>
                                <span class="cv-template-name"><?= e($tpl['name']) ?></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Thông tin cơ bản -->
            <div class="panel-card mb-4">
                <h6 class="mb-3"><i class="fas fa-user me-2 text-gold"></i>Thông tin cá nhân</h6>

                <div class="row g-3 mb-3">
                    <div class="col-md-7">
                        <label class="form-label" for="full_name">Họ và tên</label>
                        <input type="text" name="full_name" id="full_name" class="form-control"
                               value="<?= e($editing['full_name'] ?? '') ?>" placeholder="Nguyễn Văn A">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label" for="headline">Chức danh / Tiêu đề</label>
                        <input type="text" name="headline" id="headline" class="form-control"
                               value="<?= e($editing['headline'] ?? '') ?>" placeholder="Frontend Developer">
                    </div>
                </div>

                <!-- Avatar -->
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label" for="avatar">Ảnh đại diện</label>
                        <input type="file" name="avatar" id="avatar" class="form-control"
                               accept="image/png,image/jpeg,image/webp">
                        <div class="form-hint mt-1">JPG/PNG/WEBP, tối đa 3MB</div>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="cv-avatar-preview">
                            <?php if (!empty($editing['avatar'])): ?>
                                <img src="<?= e($editing['avatar']) ?>" alt="Avatar" id="avatarPreview">
                            <?php else: ?>
                                <span id="avatarPreview" class="cv-avatar-empty">
                                    <i class="fas fa-user"></i>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="title">Tên CV</label>
                        <input type="text" name="title" id="title" class="form-control"
                               value="<?= e($editing['title'] ?? 'CV ' . date('Y')) ?>">
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label" for="email">Email</label>
                        <input type="email" name="email" id="email" class="form-control" value="<?= e($editing['email'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="phone">Điện thoại</label>
                        <input type="text" name="phone" id="phone" class="form-control" value="<?= e($editing['phone'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="address">Địa chỉ</label>
                        <input type="text" name="address" id="address" class="form-control" value="<?= e($editing['address'] ?? '') ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="links">
                        Liên kết
                        <span class="text-secondary">- mỗi dòng: Nhãn | URL</span>
                    </label>
                    <textarea name="links" id="links" class="form-control" rows="3"
                              placeholder="Website | https://example.com&#10;GitHub | https://github.com/username"><?= e(implode("\n", array_map(
                                  fn($l) => trim(($l['label'] ?? '') . ' | ' . ($l['url'] ?? '')),
                                  $selLinks
                              ))) ?></textarea>
                </div>
            </div>

            <!-- Nội dung -->
            <div class="panel-card mb-4">
                <h6 class="mb-3"><i class="fas fa-file-lines me-2 text-gold"></i>Nội dung CV</h6>

                <div class="mb-3">
                    <label class="form-label" for="summary">Tóm tắt</label>
                    <textarea name="summary" id="summary" class="form-control" rows="4"
                              placeholder="Giới thiệu ngắn về bản thân, kinh nghiệm và mục tiêu nghề nghiệp..."><?= e($editing['summary'] ?? '') ?></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="experience">Kinh nghiệm làm việc</label>
                    <textarea name="experience" id="experience" class="form-control" rows="5"
                              placeholder="Mỗi dòng: Vị trí | Công ty | 2021-2024 | Mô tả công việc"><?= e(implode("\n", array_map(
                                  fn($x) => trim(($x['position'] ?? '') . ' | ' . ($x['company'] ?? '') . ' | ' . ($x['period'] ?? '') . ' | ' . ($x['detail'] ?? '')),
                                  json_decode((string)($editing['experience'] ?? '[]'), true) ?: []
                              ))) ?></textarea>
                    <div class="form-text">Format: <code>Vị trí | Công ty | Thời gian | Mô tả</code></div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="education">Học vấn</label>
                    <textarea name="education" id="education" class="form-control" rows="3"
                              placeholder="Mỗi dòng: Bậc học | Trường | Năm | Chi tiết"><?= e(implode("\n", array_map(
                                  fn($x) => trim(($x['position'] ?? '') . ' | ' . ($x['company'] ?? '') . ' | ' . ($x['period'] ?? '') . ' | ' . ($x['detail'] ?? '')),
                                  json_decode((string)($editing['education'] ?? '[]'), true) ?: []
                              ))) ?></textarea>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="skills">Kỹ năng</label>
                        <input type="text" name="skills" id="skills" class="form-control"
                               placeholder="HTML, CSS, JavaScript, React"
                               value="<?= e(implode(', ', json_decode((string)($editing['skills'] ?? '[]'), true) ?: [])) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="languages">Ngôn ngữ</label>
                        <input type="text" name="languages" id="languages" class="form-control"
                               placeholder="Tiếng Việt, Tiếng Anh"
                               value="<?= e(implode(', ', json_decode((string)($editing['languages'] ?? '[]'), true) ?: [])) ?>">
                    </div>
                </div>
            </div>

            <!-- Tùy chỉnh -->
            <div class="panel-card mb-4">
                <h6 class="mb-3"><i class="fas fa-sliders me-2 text-gold"></i>Tùy chỉnh giao diện</h6>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label" for="accent">Màu chủ đạo</label>
                        <div class="d-flex align-items-center gap-2">
                            <input type="color" name="accent" id="accent" class="form-control form-control-color"
                                   value="<?= e($editing['accent'] ?? '#f5c542') ?>">
                            <span class="form-hint">Đổi màu nhấn của CV</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="font_family">Font chữ</label>
                        <select name="font_family" id="font_family" class="form-select">
                            <?php foreach ($fonts as $code => $label): ?>
                                <option value="<?= e($code) ?>" <?= (string)($editing['font_family'] ?? 'Inter') === $code ? 'selected' : '' ?>>
                                    <?= e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <label class="form-label d-block">Các mục hiển thị</label>
                <div class="cv-section-picker">
                    <?php foreach ($sections as $key => $label): ?>
                        <label class="cv-section-item">
                            <input type="checkbox" name="sections[]" value="<?= e($key) ?>"
                                   <?= in_array($key, $selSections, true) ? 'checked' : '' ?>>
                            <span><?= e($label) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div class="form-hint mt-2">
                    <i class="fas fa-circle-info me-1"></i>
                    Bỏ chọn những mục bạn không muốn hiển thị. Thứ tự hiển thị theo thứ tự bạn tick.
                </div>
            </div>

            <button type="submit" class="btn btn-gold btn-lg">
                <i class="fas fa-save me-2"></i>Lưu CV
            </button>
            <a href="<?= url('/services/cv') ?>" class="btn btn-outline-secondary btn-lg ms-2">Huỷ</a>
        </div>

        <!-- ============ Cột phải ============ -->
        <div class="col-lg-4">
            <div class="panel-card mb-4">
                <h6 class="mb-3"><i class="fas fa-list me-2"></i>CV của tôi</h6>
                <?php if (!$profiles): ?>
                    <p class="text-muted-2 small mb-0">Chưa có CV nào. Điền form bên trái để tạo.</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($profiles as $p): ?>
                            <div class="list-group-item bg-transparent text-reset d-flex justify-content-between align-items-center gap-2">
                                <div class="min-w-0">
                                    <strong class="small d-block text-truncate"><?= e($p['title']) ?></strong>
                                    <span class="small text-muted-2"><?= e(date('d/m/Y', strtotime((string)$p['created_at']))) ?></span>
                                </div>
                                <div class="d-flex gap-1">
                                    <a href="<?= url('/services/cv/view/' . (int)$p['id']) ?>" class="btn btn-sm btn-outline-gold" title="Xem">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="<?= url('/services/cv?edit=' . (int)$p['id']) ?>" class="btn btn-sm btn-outline-gold" title="Sửa">
                                        <i class="fas fa-pen"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="panel-card">
                <h6 class="mb-2"><i class="fas fa-lightbulb me-2 text-gold"></i>Mẹo để CV đẹp</h6>
                <ul class="small text-muted-2 mb-0" style="padding-left:1.1rem">
                    <li>Ảnh đại diện nền đơn sắc, khuôn mặt rõ nét trông chuyên nghiệp hơn.</li>
                    <li>Mỗi dòng kinh nghiệm nên có thành tích cụ thể (VD: tăng 30% hiệu suất).</li>
                    <li>Chọn 1 mẫu và bật/tắt mục cho vừa — đừi để quá nhiều mục rỗng.</li>
                    <li>Màu chủ đạo nên tương phản với nền trắng khi in ra PDF.</li>
                </ul>
            </div>
        </div>
    </form>
</div>

<style>
.cv-template-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(104px, 1fr)); gap: .6rem;
}
.cv-template-item { cursor: pointer; }
.cv-template-box {
    display: flex; flex-direction: column; align-items: center; gap: .3rem;
    padding: .8rem .4rem; border-radius: .8rem;
    border: 1px solid var(--casino-line);
    background: rgba(255, 255, 255, .03);
    transition: all .16s ease; text-align: center;
}
.cv-template-box i { font-size: 1.15rem; color: var(--casino-muted); }
.cv-template-name { font-size: .74rem; font-weight: 600; }
.cv-template-item input:checked + .cv-template-box {
    border-color: var(--casino-gold);
    background: rgba(245, 197, 66, .12);
    box-shadow: 0 0 0 1px var(--casino-gold) inset;
}
.cv-template-item input:checked + .cv-template-box i { color: var(--casino-gold); }
.cv-template-item:hover .cv-template-box { border-color: var(--casino-gold); }

.cv-avatar-preview {
    width: 76px; height: 76px; border-radius: 50%;
    overflow: hidden; border: 2px solid var(--casino-gold);
    background: rgba(255, 255, 255, .05);
    display: flex; align-items: center; justify-content: center;
}
.cv-avatar-preview img { width: 100%; height: 100%; object-fit: cover; }
.cv-avatar-empty { color: var(--casino-muted); font-size: 1.5rem; }

.cv-section-picker {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: .4rem;
}
.cv-section-item {
    display: flex; align-items: center; gap: .45rem;
    padding: .4rem .6rem; border-radius: .55rem;
    border: 1px solid var(--casino-line); cursor: pointer;
    font-size: .82rem; transition: all .14s ease;
}
.cv-section-item:hover { border-color: var(--casino-gold); }
.cv-section-item:has(input:checked) {
    border-color: var(--casino-gold);
    background: rgba(245, 197, 66, .1);
}
</style>

<script>
(function () {
    // Chọn mẫu → đổi màu chủ đạo theo mẫu
    var accent = document.getElementById('accent');
    document.querySelectorAll('input[name="template"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            if (accent && radio.dataset.accent) {
                accent.value = radio.dataset.accent;
            }
        });
    });

    // Xem trước ảnh đại diện
    var avatarInput = document.getElementById('avatar');
    var preview = document.getElementById('avatarPreview');
    avatarInput?.addEventListener('change', function () {
        var file = avatarInput.files && avatarInput.files[0];
        if (!file || !preview) return;
        var reader = new FileReader();
        reader.onload = function (ev) {
            if (preview.tagName === 'IMG') {
                preview.src = ev.target.result;
            } else {
                var img = document.createElement('img');
                img.id = 'avatarPreview';
                img.alt = 'Avatar';
                img.src = ev.target.result;
                preview.replaceWith(img);
            }
        };
        reader.readAsDataURL(file);
    });
})();
</script>
