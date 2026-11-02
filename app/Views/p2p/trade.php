<?php
/** @var array $trade,$messages,$events,$isBuyer */
use App\Services\P2PService;

$status = (string)$trade['status'];
$buyerId  = (int)$trade['buyer_id'];
$sellerId = (int)$trade['seller_id'];
$tradeId  = (int)$trade['id'];

$done = in_array($status, ['completed', 'cancelled', 'refunded'], true);

/** Hàm render 1 nút hành động */
$actionBtn = function (string $action, string $label, string $icon, string $style, bool $show = true): string {
    if (!$show) {
        return '';
    }
    return '<form method="POST" action="' . url('/p2p/action') . '" class="d-inline">'
        . csrf_field()
        . '<input type="hidden" name="trade_id" value="' . $GLOBALS['tid'] . '">'
        . '<input type="hidden" name="action" value="' . e($action) . '">'
        . '<button type="submit" class="btn btn-' . $style . '"><i class="fas ' . $icon . ' me-1"></i>' . e($label) . '</button>'
        . '</form>';
};
?>
<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= url('/p2p/my') ?>">P2P của tôi</a></li>
            <li class="breadcrumb-item active"><?= e($trade['trade_no']) ?></li>
        </ol>
    </nav>

    <div class="p2p-trade-head">
        <div>
            <h1 class="page-title" style="font-size:1.4rem"><?= e($trade['title']) ?></h1>
            <div class="text-muted-2 small mt-1">
                Mã giao dịch: <strong><?= e($trade['trade_no']) ?></strong> ·
                Số tiền: <strong class="text-gold"><?= money((float)$trade['amount']) ?></strong>
                <?php if ((float)$trade['fee'] > 0): ?>
                    · Phí: <?= money((float)$trade['fee']) ?>
                <?php endif; ?>
            </div>
        </div>
        <span class="badge bg-<?= $status === 'completed' ? 'success' : ($status === 'disputed' ? 'danger' : 'warning') ?> p2 fs-6">
            <?= e(P2PService::statusLabel($status)) ?>
        </span>
    </div>

    <?php if (!$done): ?>
        <div class="p2p-steps">
            <?php
            $steps = [
                'created'          => ['Đã tạo đơn', 'created'],
                'paid'             => ['Người mua đã chuyển khoản', 'paid'],
                'seller_delivered' => ['Người bán đã giao hàng', 'seller_delivered'],
                'buyer_confirmed'  => ['Người mua đã nhận tiền', 'buyer_confirmed'],
                'seller_confirmed' => ['Người bán đã nhận tiền', 'seller_confirmed'],
                'completed'        => ['Hoàn tất - đã chuyển tiền', 'completed'],
            ];
            $order = ['created', 'paid', 'seller_delivered', 'buyer_confirmed', 'seller_confirmed', 'completed'];
            $currentIdx = array_search($status, $order, true);
            $currentIdx = $currentIdx === false ? 0 : (int)$currentIdx;
            foreach ($steps as $key => [$label, $icon]):
                $idx = array_search($key, $order, true);
                $cls = $idx < $currentIdx ? 'done' : ($idx === $currentIdx ? 'current' : '');
                ?>
                <div class="p2p-step <?= $cls ?>">
                    <div class="p2p-step-dot"><i class="fas fa-<?= $idx < $currentIdx ? 'check' : $icon ?>"></i></div>
                    <div class="p2p-step-label"><?= e($label) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="row g-4 mt-1">
        <div class="col-lg-8">
            <!-- Chat -->
            <div class="panel-card mb-4">
                <h6 class="mb-3"><i class="fas fa-comments me-2"></i>Trao đổi</h6>

                <div class="p2p-chat" id="p2pChat">
                    <?php foreach ($messages as $m): ?>
                        <?php $isSystem = !empty($m['is_system']); ?>
                        <div class="p2p-msg <?= $isSystem ? 'system' : ((int)$m['sender_id'] === $buyerId ? 'buyer' : 'seller') ?>">
                            <?php if (!$isSystem): ?>
                                <div class="p2p-msg-author"><?= e($m['username']) ?></div>
                            <?php endif; ?>
                            <div class="p2p-msg-body"><?= nl2br(e((string)$m['body'])) ?></div>
                            <div class="p2p-msg-time"><?= e(date('H:i', strtotime((string)$m['created_at']))) ?></div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$messages): ?>
                        <p class="text-muted-2 small text-center py-3 mb-0">Chưa có tin nhắn nào.</p>
                    <?php endif; ?>
                </div>

                <?php if (!$done): ?>
                    <form method="POST" action="<?= url('/p2p/message') ?>" class="mt-3">
                        <?= csrf_field() ?>
                        <input type="hidden" name="trade_id" value="<?= $tradeId ?>">
                        <div class="input-group">
                            <input type="text" name="body" class="form-control" placeholder="Nhập tin nhắn..." required>
                            <button class="btn btn-gold" type="submit"><i class="fas fa-paper-plane"></i></button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Hành động -->
            <?php if (!$done): ?>
                <div class="panel-card">
                    <h6 class="mb-3"><i class="fas fa-hand-pointer me-2"></i>Hành động</h6>

                    <div class="d-flex flex-wrap gap-2">
                        <?php if ($isBuyer): ?>
                            <?php if ($status === 'created'): ?>
                                <form method="POST" action="<?= url('/p2p/action') ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="trade_id" value="<?= $tradeId ?>">
                                    <input type="hidden" name="action" value="paid">
                                    <button type="submit" class="btn btn-gold">
                                        <i class="fas fa-money-bill-transfer me-1"></i>Tôi đã chuyển khoản
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if (in_array($status, ['seller_delivered', 'paid'], true)): ?>
                                <form method="POST" action="<?= url('/p2p/action') ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="trade_id" value="<?= $tradeId ?>">
                                    <input type="hidden" name="action" value="buyer_confirm">
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-check-double me-1"></i>Tôi đã nhận tiền &amp; hoàn tất
                                    </button>
                                </form>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php if ($status === 'paid'): ?>
                                <form method="POST" action="<?= url('/p2p/action') ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="trade_id" value="<?= $tradeId ?>">
                                    <input type="hidden" name="action" value="delivered">
                                    <button type="submit" class="btn btn-gold">
                                        <i class="fas fa-box-open me-1"></i>Tôi đã giao hàng
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if (in_array($status, ['seller_delivered', 'buyer_confirmed'], true)): ?>
                                <form method="POST" action="<?= url('/p2p/action') ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="trade_id" value="<?= $tradeId ?>">
                                    <input type="hidden" name="action" value="seller_confirm">
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-check-double me-1"></i>Tôi đã nhận tiền
                                    </button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if ($status !== 'disputed'): ?>
                            <button type="button" class="btn btn-outline-danger ms-auto" data-bs-toggle="modal" data-bs-target="#disputeModal">
                                <i class="fas fa-flag me-1"></i>Báo tranh chấp
                            </button>
                        <?php endif; ?>
                    </div>

                    <div class="alert alert-info mt-3 mb-0 small">
                        <i class="fas fa-shield-halved me-1"></i>
                        Tiền của bạn đang được giữ trong <strong>ví dự phòng của hệ thống</strong>.
                        Chỉ khi <strong>cả hai bên xác nhận</strong> "đã nhận tiền / hoàn tất",
                        hệ thống mới mở khoá chuyển tiền tới ví người bán.
                        Nếu quá <?= (int)setting('p2p_auto_release_hours', 24) ?> giờ mà không có khiếu nại,
                        hệ thống sẽ tự động giải ngân.
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <!-- Thông tin 2 bên -->
            <div class="panel-card mb-4">
                <h6 class="mb-3"><i class="fas fa-users me-2"></i>Hai bên</h6>
                <div class="info-box mb-2">
                    <span class="info-label">Người mua</span>
                    <span class="info-value"><?= e($trade['buyer_name']) ?></span>
                </div>
                <div class="info-box">
                    <span class="info-label">Người bán</span>
                    <span class="info-value"><?= e($trade['seller_name']) ?></span>
                </div>
                <?php if (!empty($trade['payment_method'])): ?>
                    <div class="info-box mt-2">
                        <span class="info-label">Thanh toán</span>
                        <span class="info-value"><?= e($trade['payment_method']) ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Lịch sử -->
            <div class="panel-card">
                <h6 class="mb-3"><i class="fas fa-history me-2"></i>Lịch sử</h6>
                <div class="p2p-timeline">
                    <?php foreach (array_reverse($events) as $ev): ?>
                        <div class="p2p-timeline-item">
                            <div class="p2p-timeline-dot"></div>
                            <div class="p2p-timeline-body">
                                <strong><?= e(P2PService::statusLabel((string)$ev['to_status'])) ?></strong>
                                <div class="small text-muted-2">
                                    <?= e($ev['username'] ?? 'Hệ thống') ?> ·
                                    <?= e(date('d/m H:i', strtotime((string)$ev['created_at']))) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!$done): ?>
<div class="modal fade" id="disputeModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="<?= url('/p2p/dispute') ?>" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="trade_id" value="<?= $tradeId ?>">
            <div class="modal-header">
                <h5 class="modal-title">Báo tranh chấp</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted-2">Tiền sẽ vẫn nằm trong ví dự phòng cho tới khi quản trị xử lý.</p>
                <textarea name="reason" class="form-control" rows="4" required
                          placeholder="Mô tả vấn đề của bạn..."></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-gold" data-bs-dismiss="modal">Huỷ</button>
                <button type="submit" class="btn btn-danger">Gửi yêu cầu</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
