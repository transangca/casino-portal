<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\CSRF;
use App\Core\Database;

/**
 * Hỗ trợ khách hàng: gửi ticket, theo dõi, trao đổi với admin.
 *
 * - Người dùng tạo ticket, xem danh sách, xem chi tiết và trả lời.
 * - Admin xem toàn bộ ticket, trả lời và đóng ticket.
 */
class TicketController
{
    private const CATEGORIES = [
        'deposit'    => 'Nạp tiền',
        'withdraw'   => 'Rút tiền',
        'game'       => 'Vấn đề khi chơi game',
        'account'    => 'Tài khoản / đăng nhập',
        'promotion'  => 'Khuyến mãi / giftcode',
        'api'        => 'Đấu API cho đối tác',
        'other'      => 'Vấn đề khác',
    ];

    private const PRIORITIES = [
        'low'    => 'Thấp',
        'normal' => 'Bình thường',
        'high'   => 'Cao',
        'urgent' => 'Khẩn cấp',
    ];

    // ------------------------------------------------------------------
    // Người dùng
    // ------------------------------------------------------------------

    public function index(): void
    {
        $userId = (int)Auth::id();

        $tickets = Database::fetchAll(
            'SELECT * FROM tickets WHERE user_id = ? ORDER BY updated_at DESC LIMIT 100',
            [$userId]
        );

        view('tickets/index', [
            'tickets'    => $tickets,
            'categories' => self::CATEGORIES,
            'priorities' => self::PRIORITIES,
        ]);
    }

    /**
     * Tạo ticket mới.
     */
    public function store(): void
    {
        CSRF::verifyOrFail();

        $userId   = (int)Auth::id();
        $subject  = trim((string)($_POST['subject'] ?? ''));
        $message  = trim((string)($_POST['message'] ?? ''));
        $category = (string)($_POST['category'] ?? 'general');
        $priority = (string)($_POST['priority'] ?? 'normal');

        if ($subject === '' || mb_strlen($subject) < 5) {
            flash('error', 'Vui lòng nhập tiêu đề tối thiểu 5 ký tự.');
            redirect(url('/tickets'));
        }
        if ($message === '' || mb_strlen($message) < 10) {
            flash('error', 'Vui lòng mô tả vấn đề của bạn tối thiểu 10 ký tự.');
            redirect(url('/tickets'));
        }
        if (!isset(self::CATEGORIES[$category])) {
            $category = 'general';
        }
        if (!isset(self::PRIORITIES[$priority])) {
            $priority = 'normal';
        }

        // Mã ticket dễ tra cứu: TK + timestamp ngắn
        $code = 'TK' . date('ymd') . strtoupper(bin2hex(random_bytes(3)));

        $ticketId = Database::insert('tickets', [
            'user_id'  => $userId,
            'code'     => $code,
            'category' => $category,
            'subject'  => mb_substr($subject, 0, 200),
            'message'  => $message,
            'priority' => $priority,
            'status'   => 'open',
        ]);

        Database::insert('ticket_messages', [
            'ticket_id' => $ticketId,
            'user_id'   => $userId,
            'is_admin'  => 0,
            'message'   => $message,
        ]);

        audit('ticket.create', 'tickets', (string)$ticketId, ['code' => $code]);

        flash('success', 'Đã gửi ticket ' . $code . '. Chúng tôi sẽ phản hồi sớm nhất.');
        redirect(url('/tickets/' . $code));
    }

    /**
     * Chi tiết ticket.
     */
    public function show(string $code): void
    {
        $userId = (int)Auth::id();

        $ticket = Database::fetch(
            'SELECT * FROM tickets WHERE code = ? AND user_id = ? LIMIT 1',
            [$code, $userId]
        );

        if (!$ticket) {
            abort(404, 'Không tìm thấy ticket');
        }

        $messages = Database::fetchAll(
            'SELECT tm.*, u.username, u.full_name
             FROM ticket_messages tm
             LEFT JOIN users u ON u.id = tm.user_id
             WHERE tm.ticket_id = ? ORDER BY tm.id ASC',
            [(int)$ticket['id']]
        );

        view('tickets/show', [
            'ticket'    => $ticket,
            'messages'  => $messages,
            'categories'=> self::CATEGORIES,
            'priorities'=> self::PRIORITIES,
        ]);
    }

    /**
     * Người dùng gửi thêm nội dung vào ticket.
     */
    public function reply(string $code): void
    {
        CSRF::verifyOrFail();

        $userId  = (int)Auth::id();
        $message = trim((string)($_POST['message'] ?? ''));

        $ticket = Database::fetch(
            'SELECT * FROM tickets WHERE code = ? AND user_id = ? LIMIT 1',
            [$code, $userId]
        );

        if (!$ticket) {
            abort(404, 'Không tìm thấy ticket');
        }
        if ($message === '') {
            flash('error', 'Vui lòng nhập nội dung trả lời.');
            redirect(url('/tickets/' . $code));
        }
        if ($ticket['status'] === 'closed') {
            flash('error', 'Ticket đã đóng. Vui lòng tạo ticket mới.');
            redirect(url('/tickets/' . $code));
        }

        Database::insert('ticket_messages', [
            'ticket_id' => (int)$ticket['id'],
            'user_id'   => $userId,
            'is_admin'  => 0,
            'message'   => $message,
        ]);

        Database::update('tickets', ['status' => 'open'], 'id = :id', ['id' => $ticket['id']]);

        redirect(url('/tickets/' . $code));
    }

    // ------------------------------------------------------------------
    // Admin
    // ------------------------------------------------------------------

    public function adminIndex(): void
    {
        $status = (string)($_GET['status'] ?? '');
        $where  = '1=1';
        $params = [];

        if ($status !== '' && in_array($status, ['open', 'processing', 'resolved', 'closed'], true)) {
            $where = 't.status = ?';
            $params[] = $status;
        }

        $tickets = Database::fetchAll(
            "SELECT t.*, u.username, u.full_name
             FROM tickets t JOIN users u ON u.id = t.user_id
             WHERE $where ORDER BY
                FIELD(t.status,'open','processing','resolved','closed'),
                FIELD(t.priority,'urgent','high','normal','low'),
                t.updated_at DESC
             LIMIT 200",
            $params
        );

        $counts = [];
        foreach (['open', 'processing', 'resolved', 'closed'] as $s) {
            $counts[$s] = (int)Database::fetchColumn('SELECT COUNT(*) FROM tickets WHERE status = ?', [$s]);
        }

        view('admin/tickets', [
            'tickets'    => $tickets,
            'status'     => $status,
            'counts'     => $counts,
            'categories' => self::CATEGORIES,
            'priorities' => self::PRIORITIES,
        ]);
    }

    public function adminShow(string $code): void
    {
        $ticket = Database::fetch(
            'SELECT t.*, u.username, u.full_name, u.email, u.balance
             FROM tickets t JOIN users u ON u.id = t.user_id
             WHERE t.code = ? LIMIT 1',
            [$code]
        );

        if (!$ticket) {
            abort(404, 'Không tìm thấy ticket');
        }

        $messages = Database::fetchAll(
            'SELECT tm.*, u.username, u.full_name
             FROM ticket_messages tm
             LEFT JOIN users u ON u.id = tm.user_id
             WHERE tm.ticket_id = ? ORDER BY tm.id ASC',
            [(int)$ticket['id']]
        );

        view('admin/ticket_detail', [
            'ticket'    => $ticket,
            'messages'  => $messages,
            'categories'=> self::CATEGORIES,
            'priorities'=> self::PRIORITIES,
        ]);
    }

    /**
     * Admin trả lời ticket.
     */
    public function adminReply(string $code): void
    {
        CSRF::verifyOrFail();

        $adminId = (int)Auth::id();
        $message = trim((string)($_POST['message'] ?? ''));
        $status  = (string)($_POST['status'] ?? 'processing');

        $ticket = Database::fetch('SELECT * FROM tickets WHERE code = ? LIMIT 1', [$code]);
        if (!$ticket) {
            abort(404, 'Không tìm thấy ticket');
        }
        if (!in_array($status, ['open', 'processing', 'resolved', 'closed'], true)) {
            $status = 'processing';
        }

        if ($message !== '') {
            Database::insert('ticket_messages', [
                'ticket_id' => (int)$ticket['id'],
                'user_id'   => $adminId,
                'is_admin'  => 1,
                'message'   => $message,
            ]);

            Database::update('tickets', [
                'admin_reply' => $message,
                'replied_by'  => $adminId,
                'replied_at'  => now(),
            ], 'id = :id', ['id' => $ticket['id']]);
        }

        Database::update('tickets', ['status' => $status], 'id = :id', ['id' => $ticket['id']]);

        flash('success', 'Đã cập nhật ticket.');
        redirect(url('/admin/tickets/' . $code));
    }
}
