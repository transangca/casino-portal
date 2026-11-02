<?php
namespace App\Services;

use App\Core\Database;
use App\Core\Wallet;

/**
 * Chuyển tiền nội bộ giữa các thành viên.
 */
class TransferService
{
    public static function feeRate(): float
    {
        return (float)setting('transfer_fee_rate', 0);
    }

    /**
     * Thực hiện chuyển tiền. Trả về ['ok'=>bool,'message'=>string].
     */
    public static function send(int $fromUserId, string $recipient, float $amount, string $note = ''): array
    {
        $recipient = trim($recipient);
        if ($recipient === '') {
            return ['ok' => false, 'message' => 'Vui lòng nhập tài khoản hoặc email người nhận.'];
        }
        if ($amount <= 0) {
            return ['ok' => false, 'message' => 'Số tiền phải lớn hơn 0.'];
        }

        $target = Database::fetch(
            'SELECT id, username, email, full_name FROM users
             WHERE (username = ? OR email = ?) AND status = "active" LIMIT 1',
            [$recipient, $recipient]
        );

        if (!$target) {
            return ['ok' => false, 'message' => 'Không tìm thấy người nhận.'];
        }
        if ((int)$target['id'] === $fromUserId) {
            return ['ok' => false, 'message' => 'Bạn không thể chuyển tiền cho chính mình.'];
        }

        $min = (float)setting('transfer_min', 10000);
        if ($amount < $min) {
            return ['ok' => false, 'message' => 'Số tiền chuyển tối thiểu là ' . money($min) . '.'];
        }

        $fee    = round($amount * self::feeRate(), 2);
        $total  = $amount + $fee;

        if (Wallet::balance($fromUserId) < $total) {
            return ['ok' => false, 'message' => 'Số dư không đủ để thực hiện giao dịch.'];
        }

        $toUserId = (int)$target['id'];

        try {
            Database::transaction(function () use ($fromUserId, $toUserId, $amount, $fee, $note, $target) {
                Wallet::apply(
                    $fromUserId,
                    'adjustment',
                    -$total,
                    'Chuyển tiền cho ' . $target['username'] . ($note !== '' ? ' - ' . $note : ''),
                    'transfer',
                    (string)$toUserId
                );

                Wallet::apply(
                    $toUserId,
                    'adjustment',
                    $amount,
                    'Nhận tiền từ thành viên #' . $fromUserId . ($note !== '' ? ' - ' . $note : ''),
                    'transfer',
                    (string)$fromUserId
                );

                Database::insert('transfers', [
                    'from_user_id' => $fromUserId,
                    'to_user_id'   => $toUserId,
                    'amount'       => $amount,
                    'fee'          => $fee,
                    'note'         => $note !== '' ? mb_substr($note, 0, 255) : null,
                    'status'       => 'success',
                ]);
            });
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        ActivityLogService::log(ActivityLogService::SCOPE_WALLET, 'transfer.send', $fromUserId, $target['username'], [
            'amount' => $amount,
            'fee'    => $fee,
        ]);

        // Thông báo email cho cả hai bên
        \App\Services\MailerService::notifyUser($toUserId, 'transfer', [
            'amount'    => $amount,
            'fee'       => 0,
            'recipient' => 'Bạn',
            'from'      => $target['username'],
            'note'      => $note,
            'subject'   => 'Bạn vừa nhận được ' . money($amount) . ' từ thành viên khác',
        ]);
        \App\Services\MailerService::notifyUser($fromUserId, 'transfer', [
            'amount'    => $amount,
            'fee'       => $fee,
            'recipient' => $target['username'],
            'from'      => $target['username'],
            'note'      => $note,
            'subject'   => 'Chuyển tiền thành công ' . money($amount),
        ]);

        return ['ok' => true, 'message' => 'Chuyển tiền thành công cho ' . $target['username'] . '.'];
    }

    public static function history(int $userId, int $limit = 50): array
    {
        return Database::fetchAll(
            'SELECT t.*, uf.username AS from_username, ut.username AS to_username
             FROM transfers t
             JOIN users uf ON uf.id = t.from_user_id
             JOIN users ut ON ut.id = t.to_user_id
             WHERE t.from_user_id = ? OR t.to_user_id = ?
             ORDER BY t.id DESC LIMIT ' . (int)$limit,
            [$userId, $userId]
        );
    }
}
