<?php
namespace App\Core;

use App\Core\Database;

/**
 * Ví tiền — nguồn sự thật duy nhất (single source of truth).
 *
 * Mọi thay đổi số dư đều đi qua đây và luôn ghi 1 dòng vào
 * `wallet_transactions` để đối soát (ledger bất biến).
 */
class Wallet
{
    /**
     * Cộng/trừ số dư trong transaction, khoá dòng user để tránh race condition.
     *
     * @param string $type  deposit|withdraw|bet|win|refund|bonus|adjustment|giftcode|vps_order|vps_refund|vps_renew|vps_addon
     * @return float balance_after
     */
    public static function apply(
        int $userId,
        string $type,
        float $amount,
        string $description = '',
        ?string $refType = null,
        ?string $refId = null,
        array $metadata = []
    ): float {
        if ($amount === 0.0) {
            return (float)Database::fetchColumn('SELECT balance FROM users WHERE id = ?', [$userId]);
        }

        return Database::transaction(function () use ($userId, $type, $amount, $description, $refType, $refId, $metadata) {
            // FOR UPDATE: chặn 2 request đồng thời ghi cùng balance
            $row = Database::fetch('SELECT balance FROM users WHERE id = ? FOR UPDATE', [$userId]);
            if (!$row) {
                throw new \RuntimeException('Không tìm thấy người dùng #' . $userId);
            }

            $before = (float)$row['balance'];
            $after  = $before + $amount;

            if ($after < 0) {
                throw new \RuntimeException('Số dư không đủ. Hiện có: ' . number_format($before) . ' đ');
            }

            Database::update('users', ['balance' => $after], 'id = :id', ['id' => $userId]);

            Database::insert('wallet_transactions', [
                'user_id'       => $userId,
                'type'          => $type,
                'amount'        => $amount,
                'balance_after' => $after,
                'bonus_after'   => 0,
                'ref_type'      => $refType,
                'ref_id'        => $refId,
                'description'   => $description,
                'metadata'      => $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
            ]);

            return $after;
        });
    }

    public static function balance(int $userId): float
    {
        return (float)Database::fetchColumn('SELECT balance FROM users WHERE id = ?', [$userId]);
    }

    /**
     * Trừ tiền khi cược (nếu đủ) — trả về balance sau.
     */
    public static function debit(int $userId, float $amount, string $desc = 'Đặt cược', ?string $refType = null, ?string $refId = null): float
    {
        return self::apply($userId, 'bet', -abs($amount), $desc, $refType, $refId);
    }

    public static function credit(int $userId, float $amount, string $desc = 'Thắng cược', ?string $refType = null, ?string $refId = null): float
    {
        return self::apply($userId, 'win', abs($amount), $desc, $refType, $refId);
    }

    /** Lịch sử giao dịch của user */
    public static function history(int $userId, int $limit = 50, int $offset = 0): array
    {
        return Database::fetchAll(
            'SELECT * FROM wallet_transactions WHERE user_id = ? ORDER BY id DESC LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset,
            [$userId]
        );
    }

    public static function totalDeposited(int $userId): float
    {
        return (float)Database::fetchColumn(
            'SELECT COALESCE(SUM(amount),0) FROM wallet_transactions WHERE user_id = ? AND type IN ("deposit","giftcode")',
            [$userId]
        );
    }

    /**
     * Thống kê tổng quan cho dashboard admin.
     */
    public static function stats(): array
    {
        return [
            'total_balance' => (float)Database::fetchColumn('SELECT COALESCE(SUM(balance),0) FROM users'),
            'total_deposit' => (float)Database::fetchColumn('SELECT COALESCE(SUM(amount),0) FROM wallet_transactions WHERE type = "deposit"'),
            'total_bet'     => (float)Database::fetchColumn('SELECT COALESCE(SUM(amount),0) FROM wallet_transactions WHERE type = "bet"'),
            'total_win'     => (float)Database::fetchColumn('SELECT COALESCE(SUM(amount),0) FROM wallet_transactions WHERE type = "win"'),
            'pending_deposits' => (int)Database::fetchColumn('SELECT COUNT(*) FROM deposits WHERE status = "pending"'),
            'pending_withdrawals' => (int)Database::fetchColumn('SELECT COUNT(*) FROM withdrawals WHERE status = "pending"'),
        ];
    }
}
