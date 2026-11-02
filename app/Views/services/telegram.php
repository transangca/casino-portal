<?php /** @var array $bots, bool $configured, string $token, string $chatId */ ?>
<div class="container py-4">
    <h1 class="page-title"><i class="fab fa-telegram-plane me-2"></i>Telegram Mini App &amp; Bot</h1>
    <p class="page-sub">Cấu hình bot Telegram để nhận thông báo tự động, và hướng dẫn tạo Mini App theo chuẩn Telegram Web App.</p>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="panel-card h-100">
                <h6 class="mb-3"><i class="fas fa-robot me-2 text-gold"></i>Thiết lập Bot</h6>

                <?php if (!$configured): ?>
                    <div class="alert alert-info small">
                        <i class="fas fa-circle-info me-1"></i>
                        Chưa cấu hình Bot. Hãy tạo bot mới tại <strong>@BotFather</strong> trên Telegram,
                        lấy <em>Bot Token</em> và <em>Chat ID</em> của nhóm/quản trị để nhận thông báo.
                    </div>
                <?php else: ?>
                    <div class="alert alert-success small">
                        <i class="fas fa-circle-check me-1"></i>
                        Bot đang hoạt động — hệ thống tự gửi thông báo khi có dự án mới, giao dịch P2P, đăng ký mới.
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= url('/services/telegram/save') ?>">
                    <?= csrf_field() ?>
                    <div class="mb-2">
                        <label class="form-label">Tên hiển thị</label>
                        <input type="text" name="label" class="form-control form-control-sm" value="Bot chính" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Bot Token</label>
                        <input type="text" name="bot_token" class="form-control form-control-sm font-monospace" value="<?= e($token) ?>" placeholder="123456789:AAE...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Chat ID nhận thông báo</label>
                        <input type="text" name="chat_id" class="form-control form-control-sm font-monospace" value="<?= e($chatId) ?>" placeholder="-1001234567890">
                    </div>
                    <button class="btn btn-gold btn-sm"><i class="fas fa-save me-1"></i>Lưu cấu hình</button>
                </form>

                <hr class="footer-divider">

                <form method="POST" action="<?= url('/services/telegram/test') ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-outline-gold btn-sm"><i class="fas fa-paper-plane me-1"></i>Gửi tin test</button>
                </form>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="panel-card h-100">
                <h6 class="mb-3"><i class="fas fa-code me-2 text-gold"></i>Mini App của bạn</h6>
                <p class="small text-muted-2">
                    Mini App là một website (web app) mở trong Telegram. Bạn chỉ cần điền URL webapp
                    vào <strong>BotFather → Menu Button → Web App</strong> là khách có thể mở từ bot.
                </p>

                <ol class="small" style="line-height:1.9">
                    <li>Mở <strong>@BotFather</strong> → gửi <code>/newbot</code> để tạo bot.</li>
                    <li>Lấy <strong>Bot Token</strong> dán vào ô bên trái.</li>
                    <li>Vào <strong>Menu Button</strong> → chọn bot → <strong>Web App</strong>.</li>
                    <li>Nhập URL webapp (HTTPS bắt buộc), ví dụ <code><?= e(url('/services/telegram')) ?></code>.</li>
                    <li>Bấm <strong>Test Web App</strong> trong BotFather để kiểm tra.</li>
                </ol>

                <hr class="footer-divider">

                <h6 class="mb-2"><i class="fas fa-list me-2 text-gold"></i>Danh sách bot đã đăng ký</h6>
                <table class="table table-sm small mb-0">
                    <thead><tr><th>Tên</th><th>Chat ID</th><th>Mặc định</th><th>Trạng thái</th></tr></thead>
                    <tbody>
                        <?php foreach ($bots as $b): ?>
                            <tr>
                                <td><?= e((string)$b['label']) ?></td>
                                <td class="font-monospace small"><?= e((string)$b['chat_id']) ?></td>
                                <td><?= (int)$b['is_default'] === 1 ? '<i class="fas fa-star text-gold"></i>' : '—' ?></td>
                                <td>
                                    <?php if ((int)$b['is_active'] === 1): ?>
                                        <span class="badge bg-success bg-opacity-25 text-success">Hoạt động</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary bg-opacity-25">Tắt</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$bots): ?>
                            <tr><td colspan="4" class="text-muted-2 text-center py-3">Chưa có bot nào.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
