<?php
namespace App\Services;

use App\Core\Database;

/**
 * CSO / Affiliate: đối tác giới thiệu, nhận hoa hồng từ người được giới thiệu.
 */
class CsoService
{
    public static function accountFor(int $userId): ?array
    {
        return Database::fetch('SELECT * FROM cso_accounts WHERE user_id = ? LIMIT 1', [$userId]);
    }

    public static function findByCode(string $code): ?array
    {
        return Database::fetch('SELECT * FROM cso_accounts WHERE code = ? AND is_active = 1 LIMIT 1', [$code]);
    }

    /** Tạo tài khoản CSO cho user */
    public static function create(int $userId, string $name, ?float $rate = null): array
    {
        if (self::accountFor($userId)) {
            return ['success' => false, 'message' => 'Bạn đã có tài khoản CSO'];
        }

        $code = self::generateCode();
        $rate = $rate ?? (float)setting('default_cso_rate', 0.02);

        $id = Database::insert('cso_accounts', [
            'user_id'         => $userId,
            'code'            => $code,
            'name'            => $name,
            'commission_rate' => $rate,
            'is_active'       => 1,
        ]);

        return ['success' => true, 'message' => 'Tạo mã CSO thành công: ' . $code, 'id' => $id, 'code' => $code];
    }

    private static function generateCode(): string
    {
        do {
            $code = strtoupper(bin2hex(random_bytes(4)));
            $exists = Database::fetchColumn('SELECT id FROM cso_accounts WHERE code = ?', [$code]);
        } while ($exists);
        return $code;
    }

    /**
     * Ghi nhận giới thiệu khi user đăng ký.
     */
    public static function applyReferral(int $newUserId, string $code): bool
    {
        $cso = self::findByCode($code);
        if (!$cso) {
            return false;
        }

        $exists = Database::fetchColumn('SELECT id FROM referrals WHERE referred_id = ?', [$newUserId]);
        if ($exists) {
            return false;
        }

        Database::insert('referrals', [
            'referrer_id'   => (int)$cso['user_id'],
            'referred_id'   => $newUserId,
            'bonus_amount'  => 0,
        ]);
        return true;
    }

    /**
     * Cộng hoa hồng cho CSO khi người được giới thiệu nạp/cược.
     */
    public static function accrue(int $sourceUserId, float $amount, string $type = 'deposit'): void
    {
        $ref = Database::fetch('SELECT * FROM referrals WHERE referred_id = ? LIMIT 1', [$sourceUserId]);
        if (!$ref) {
            return;
        }

        $cso = Database::fetch('SELECT * FROM cso_accounts WHERE user_id = ? LIMIT 1', [$ref['referrer_id']]);
        if (!$cso || !$cso['is_active']) {
            return;
        }

        $rate = (float)$cso['commission_rate'];
        if ($rate <= 0) {
            return;
        }

        $commission = round($amount * $rate, 2);
        if ($commission <= 0) {
            return;
        }

        Database::insert('cso_commissions', [
            'cso_id'        => (int)$cso['id'],
            'user_id'       => (int)$cso['user_id'],
            'source_user_id'=> $sourceUserId,
            'amount'        => $commission,
            'rate'          => $rate,
            'type'          => $type,
            'status'        => 'pending',
        ]);

        Database::update('cso_accounts', [
            'total_revenue'    => (float)$cso['total_revenue'] + $amount,
            'total_commission' => (float)$cso['total_commission'] + $commission,
        ], 'id = :id', ['id' => $cso['id']]);
    }

    /** Tổng quan cho trang CSO của user */
    public static function summary(int $userId): array
    {
        $cso = self::accountFor($userId);
        if (!$cso) {
            return [];
        }

        $pending = (float)Database::fetchColumn(
            'SELECT COALESCE(SUM(amount),0) FROM cso_commissions WHERE cso_id = ? AND status = "pending"',
            [$cso['id']]
        );
        $paid = (float)Database::fetchColumn(
            'SELECT COALESCE(SUM(amount),0) FROM cso_commissions WHERE cso_id = ? AND status = "paid"',
            [$cso['id']]
        );
        $referred = (int)Database::fetchColumn(
            'SELECT COUNT(*) FROM referrals WHERE referrer_id = ?',
            [$userId]
        );

        return [
            'account'    => $cso,
            'pending'    => $pending,
            'paid'       => $paid,
            'referred'   => $referred,
            'commissions'=> Database::fetchAll(
                'SELECT * FROM cso_commissions WHERE cso_id = ? ORDER BY id DESC LIMIT 50',
                [$cso['id']]
            ),
        ];
    }

    /** Trả hoa hồng (admin duyệt) */
    public static function pay(int $csoId): void
    {
        $total = (float)Database::fetchColumn(
            'SELECT COALESCE(SUM(amount),0) FROM cso_commissions WHERE cso_id = ? AND status = "approved"',
            [$csoId]
        );
        if ($total <= 0) {
            return;
        }

        $cso = Database::fetch('SELECT * FROM cso_accounts WHERE id = ?', [$csoId]);
        if (!$cso) {
            return;
        }

        \App\Core\Wallet::apply(
            (int)$cso['user_id'],
            'deposit',
            $total,
            'Thanh toán hoa hồng CSO',
            'cso_commission',
            (string)$csoId
        );

        Database::update('cso_commissions', ['status' => 'paid'], 'cso_id = :c AND status = "approved"', ['c' => $csoId]);
    }
}
