<?php /** @var array $models,$agents,$recent */ ?>
<div class="container py-4">
    <h1 class="page-title">Trung tâm AI</h1>
    <p class="page-sub">Toàn bộ model AI gộp trong 1 trang. Admin thêm API key trong Cấu hình, người dùng dùng ngay tại đây.</p>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="panel-card">
                <h5 class="mb-3"><i class="fas fa-robot me-2"></i>Thử nhanh</h5>

                <?php if (!$models): ?>
                    <div class="empty-state">
                        <i class="fas fa-plug fs-1 d-block mb-3"></i>
                        <p class="mb-0">Chưa có model nào được cấu hình.</p>
                    </div>
                <?php else: ?>
                    <div class="mb-3">
                        <label class="form-label">Model</label>
                        <select id="aiModel" class="form-select">
                            <?php foreach ($models as $m): ?>
                                <option value="<?= (int)$m['id'] ?>">
                                    <?= e($m['label']) ?> — <?= e($m['provider']) ?> / <?= e($m['model']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Nội dung yêu cầu</label>
                        <textarea id="aiPrompt" class="form-control" rows="5"
                                  placeholder="Ví dụ: Viết 5 tiêu đề quảng cáo cho sảnh slot PGSoft..."></textarea>
                    </div>

                    <button class="btn btn-gold" id="aiSend">
                        <i class="fas fa-paper-plane me-1"></i>Gửi
                    </button>
                    <span id="aiStatus" class="ms-2 small text-muted-2"></span>

                    <div id="aiResult" class="ai-result mt-4" style="display:none">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong class="small text-gold">Kết quả</strong>
                            <button class="btn btn-sm btn-outline-gold" onclick="navigator.clipboard.writeText(document.getElementById('aiText').innerText)">
                                <i class="fas fa-copy"></i> Sao chép
                            </button>
                        </div>
                        <div id="aiText" class="ai-text"></div>
                        <div id="aiMeta" class="small text-muted-2 mt-2"></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="panel-card mb-4">
                <h6 class="mb-3"><i class="fas fa-cubes me-2"></i>Model đang có (<?= count($models) ?>)</h6>
                <?php foreach ($models as $m): ?>
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom border-opacity-25">
                        <div class="min-w-0">
                            <div class="small fw-semibold text-truncate"><?= e($m['label']) ?></div>
                            <div class="small text-muted-2 text-truncate"><?= e($m['provider']) ?></div>
                        </div>
                        <span class="badge bg-<?= $m['is_active'] ? 'success' : 'secondary' ?>">
                            <?= $m['is_active'] ? 'Active' : 'Off' ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($recent): ?>
            <div class="panel-card">
                <h6 class="mb-3"><i class="fas fa-user-robot me-2"></i>Agent</h6>
                <?php foreach ($recent as $a): ?>
                    <div class="small py-1">
                        <strong><?= e($a['name']) ?></strong>
                        <span class="text-muted-2"> · <?= e($a['model_label'] ?? '—') ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.getElementById('aiSend')?.addEventListener('click', async function () {
    const btn = this;
    const prompt = document.getElementById('aiPrompt').value.trim();
    if (!prompt) return;

    const status = document.getElementById('aiStatus');
    const result = document.getElementById('aiResult');
    const text = document.getElementById('aiText');
    const meta = document.getElementById('aiMeta');

    btn.disabled = true;
    status.textContent = 'Đang xử lý...';
    result.style.display = 'none';

    try {
        const res = await fetch('<?= url('/services/ai/chat') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams({
                model_id: document.getElementById('aiModel').value,
                prompt: prompt,
                _csrf_token: window.APP?.csrfToken || ''
            })
        });
        const json = await res.json();

        if (json.success) {
            text.textContent = json.data.text;
            const u = json.data.usage || {};
            meta.textContent = `Token: ${u.prompt_tokens || 0} vào / ${u.completion_tokens || 0} ra · Ước tính $${(json.data.cost || 0).toFixed(6)}`;
            result.style.display = 'block';
        } else {
            status.textContent = json.message || 'Lỗi';
        }
    } catch (e) {
        status.textContent = 'Lỗi mạng: ' + e.message;
    } finally {
        btn.disabled = false;
        if (!status.textContent) status.textContent = '';
    }
});
</script>
