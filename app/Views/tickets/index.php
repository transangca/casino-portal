<?php /** @var array $tickets,$categories,$priorities */ ?>
<div class="container py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1">Hỗ trợ khách hàng</h1>
            <p class="text-muted-2 mb-0">
                Gửi yêu cầu hỗ trợ và theo dõi tiến độ xử lý. Thời gian phản hồi trung bình dưới 5 phút.
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= e((string)setting('support_telegram', '')) ?>" class="btn btn-outline-light btn-sm" target="_blank" rel="noopener">
                <i class="fab fa-telegram-plane me-1"></i>Telegram
            </a>
            <a href="<?= e((string)setting('support_zalo', '')) ?>" class="btn btn-outline-light btn-sm" target="_blank" rel="noopener">
                <i class="fas fa-comment-dots me-1"></i>Zalo
            </a>
            <a href="tel:<?= e(preg_replace('/\D+/', '', (string)setting('support_hotline', ''))) ?>" class="btn btn-outline-gold btn-sm">
                <i class="fas fa-headset me-1"></i>Hotline
            </a>
        </div>
    </div>

    <div class="row g-4">
        <!-- ============ Form tạo ticket ============ -->
        <div class="col-lg-5">
            <div class="casino-card">
                <div class="card-header d-flex align-items-center gap-2">
                    <i class="fas fa-plus-circle text-gold"></i>
                    <span>Gửi yêu cầu hỗ trợ mới</span>
                </div>
                <div class="card-body">
                    <form method="POST" action="<?= url('/tickets') ?>" id="ticketForm">
                        <?= csrf_field() ?>

                        <div class="mb-3">
                            <label class="form-label" for="category">Loại vấn đề</label>
                            <select class="form-select" id="category" name="category">
                                <?php foreach ($categories as $key => $label): ?>
                                    <option value="<?= e($key) ?>"><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="priority">Mức độ ưu tiên</label>
                            <select class="form-select" id="priority" name="priority">
                                <?php foreach ($priorities as $key => $label): ?>
                                    <option value="<?= e($key) ?>"><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="subject">Tiêu đề</label>
                            <input type="text" class="form-control" id="subject" name="subject"
                                   placeholder="VD: Nạp tiền chưa được cộng vào ví"
                                   minlength="5" maxlength="200" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="message">Nội dung chi tiết</label>
                            <textarea class="form-control" id="message" name="message" rows="6"
                                      placeholder="Mô tả rõ vấn đề, thời gian, số tiền và mã giao dịch (nếu có)..."
                                      minlength="10" required></textarea>
                            <div class="form-hint mt-1">
                                <i class="fas fa-circle-info me-1"></i>
                                Cung cấp mã giao dịch giúp chúng tôi xử lý nhanh hơn.
                            </div>
                        </div>

                        <button type="submit" class="btn btn-gold w-100 py-2 fw-bold">
                            <i class="fas fa-paper-plane me-2"></i>Gửi yêu cầu
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- ============ Danh sách ticket ============ -->
        <div class="col-lg-7">
            <div class="casino-card">
                <div class="card-header d-flex align-items-center gap-2">
                    <i class="fas fa-ticket text-gold"></i>
                    <span>Yêu cầu của tôi</span>
                    <span class="badge-status st-processing ms-auto"><?= count($tickets) ?></span>
                </div>
                <div class="card-body p-0">
                    <?php if (!$tickets): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-inbox fs-1 text-muted-2 mb-3"></i>
                            <p class="text-muted-2 mb-0">Bạn chưa có yêu cầu hỗ trợ nào.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Mã</th>
                                        <th>Tiêu đề</th>
                                        <th>Trạng thái</th>
                                        <th>Cập nhật</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($tickets as $t): ?>
                                    <?php
                                    $statusMap = [
                                        'open'       => ['Chờ xử lý', 'st-processing'],
                                        'processing' => ['Đang xử lý', 'st-active'],
                                        'resolved'   => ['Đã xử lý', 'st-approved'],
                                        'closed'     => ['Đã đóng', 'st-closed'],
                                    ];
                                    [$label, $cls] = $statusMap[$t['status']] ?? ['—', 'st-closed'];
                                    ?>
                                    <tr>
                                        <td><code class="text-gold"><?= e($t['code']) ?></code></td>
                                        <td>
                                            <div class="fw-semibold"><?= e($t['name'] ?? $t['subject']) ?></div>
                                            <div class="small text-muted-2"><?= e($categories[$t['category']] ?? $t['category']) ?></div>
                                        </td>
                                        <td><span class="badge-status <?= e($cls) ?>"><?= e($label) ?></span></td>
                                        <td class="small text-muted-2"><?= e(date('d/m/Y H:i', strtotime($t['updated_at']))) ?></td>
                                        <td class="text-end">
                                            <a href="<?= url('/tickets/' . $t['code']) ?>" class="btn btn-sm btn-outline-gold">
                                                <i class="fas fa-eye me-1"></i>Xem
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('ticketForm');
    if (!form) return;
    form.addEventListener('submit', function (e) {
        var msg = form.querySelector('#message');
        var sub = form.querySelector('#subject');
        if (sub.value.trim().length < 5) {
            e.preventDefault();
            toastErr('Tiêu đề phải có ít nhất 5 ký tự.');
            return;
        }
        if (msg.value.trim().length < 10) {
            e.preventDefault();
            toastErr('Nội dung phải có ít nhất 10 ký tự.');
        }
    });
});
</script>
