<?php
namespace App\Services;

use App\Core\Database;
use App\Core\Wallet;

/**
 * Dịch vụ thẻ cào (card) — mệnh giá, mã thẻ, quy đổi vào ví.
 *
 * - Admin quản lý mệnh giá trong /admin/cards
 * - Admin nhập kho mã thẻ (serial + pin)
 * - Người dùng chọn mệnh giá → nhập serial/pin → tiền cộng thẳng vào ví
 */
class CardService
{
    /** Nhóm thẻ cào → logo brand tương ứng */
    public const TYPE_BRANDS = [
        'viettel'   => 'viettel',
        'mobifone'  => 'mobifone',
        'vinaphone' => 'vinaphone',
        'game'      => 'gamecard',
        'telco'     => 'viettel',
        'other'     => 'giftcard',
    ];

    /** Danh sách mệnh giá đang bán */
    public static function denominations(bool $activeOnly = true): array
    {
        $sql = 'SELECT d.*,
                       (SELECT COUNT(*) FROM card_codes c
                         WHERE c.denomination_id = d.id AND c.status = "available") AS stock
                FROM card_denominations d';
        if ($activeOnly) {
            $sql .= ' WHERE d.is_active = 1';
        }
        $sql .= ' ORDER BY d.sort_order, d.amount';
        return Database::fetchAll($sql);
    }

    public static function findDenomination(int $id): ?array
    {
        return Database::fetch('SELECT * FROM card_denominations WHERE id = ? LIMIT 1', [$id]);
    }

    /** Mệnh giá theo mã (VIETTEL_10K…) */
    public static function findDenominationByCode(string $code): ?array
    {
        return Database::fetch('SELECT * FROM card_denominations WHERE code = ? LIMIT 1', [strtoupper(trim($code))]);
    }

    /** Logo thương hiệu của nhóm thẻ */
    public static function typeLogo(string $type): string
    {
        $brand = self::TYPE_BRANDS[$type] ?? 'giftcard';
        return brand_logo_url($brand);
    }

    /**
     * Quy đổi 1 mã thẻ → cộng tiền vào ví.
     *
     * @return array{ok:bool, message:string}
     */
    public static function redeem(int $userId, string $denominationCode, string $serial, string $pin): array
    {
        $serial = trim($serial);
        $pin    = trim($pin);

        if ($serial === '' || $pin === '') {
            return ['ok' => false, 'message' => 'Vui lòng nhập đầy đủ serial và PIN thẻ.'];
        }

        $denom = self::findDenominationByCode($denominationCode);
        if (!$denom || (int)$denom['is_active'] !== 1) {
            return ['ok' => false, 'message' => 'Mệnh giá thẻ không hợp lệ hoặc đã ngừng bán.'];
        }

        $code = Database::fetch(
            'SELECT * FROM card_codes WHERE serial = ? AND pin = ? LIMIT 1',
            [$serial, $pin]
        );

        if (!$code) {
            return ['ok' => false, 'message' => 'Mã thẻ không tồn tại hoặc không khớp.'];
        }
        if ((string)$code['status'] !== 'available') {
            return ['ok' => false, 'message' => 'Mã thẻ đã được sử dụng hoặc không còn hiệu lực.'];
        }
        if (!empty($code['expires_at']) && strtotime((string)$code['expires_at']) < time()) {
            Database::update('card_codes', ['status' => 'expired'], 'id = :id', ['id' => (int)$code['id']]);
            return ['ok' => false, 'message' => 'Mã thẻ đã hết hạn.'];
        }

        $amount = (float)$code['amount'] > 0 ? (float)$code['amount'] : (float)$denom['amount'];

        try {
            Wallet::apply(
                $userId,
                'giftcode',
                $amount,
                'Nạp thẻ cào ' . (string)$denom['name'],
                'card',
                (string)$code['id'],
                ['serial' => $serial, 'denomination' => (string)$denom['code']]
            );
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        Database::update('card_codes', [
            'status'  => 'used',
            'used_by' => $userId,
            'used_at' => now(),
        ], 'id = :id', ['id' => (int)$code['id']]);

        return [
            'ok'      => true,
            'message' => 'Nạp thẻ thành công: +' . money($amount),
            'amount'  => $amount,
        ];
    }

    /** Thống kê kho thẻ cho admin */
    public static function stats(): array
    {
        return [
            'denominations' => (int)Database::fetchColumn('SELECT COUNT(*) FROM card_denominations WHERE is_active = 1'),
            'total'         => (int)Database::fetchColumn('SELECT COUNT(*) FROM card_codes'),
            'available'     => (int)Database::fetchColumn('SELECT COUNT(*) FROM card_codes WHERE status = "available"'),
            'used'          => (int)Database::fetchColumn('SELECT COUNT(*) FROM card_codes WHERE status = "used"'),
            'value'         => (float)Database::fetchColumn(
                'SELECT COALESCE(SUM(amount),0) FROM card_codes WHERE status = "available"'
            ),
        ];
    }

    /**
     * Nhập kho mã thẻ từ danh sách text (mỗi dòng: serial|pin|mệnh giá).
     */
    public static function importCodes(int $denominationId, string $raw, ?string $expiresAt = null): array
    {
        $denom = self::findDenomination($denominationId);
        if (!$denom) {
            return ['ok' => false, 'message' => 'Mệnh giá không tồn tại.', 'added' => 0];
        }

        $added = 0;
        $skip  = 0;
        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $parts = preg_split('/[|,\t;]/', $line);
            $serial = trim($parts[0] ?? '');
            $pin    = trim($parts[1] ?? '');
            if ($serial === '' || $pin === '') {
                $skip++;
                continue;
            }
            try {
                Database::insert('card_codes', [
                    'denomination_id' => $denominationId,
                    'serial'          => $serial,
                    'pin'             => $pin,
                    'amount'          => (float)$denom['amount'],
                    'status'          => 'available',
                    'expires_at'      => $expiresAt ?: null,
                ]);
                $added++;
            } catch (\Throwable $e) {
                $skip++;
            }
        }

        return [
            'ok'      => true,
            'message' => "Đã nhập {$added} mã thẻ" . ($skip > 0 ? ", bỏ qua {$skip} mã lỗi/trùng" : '') . '.',
            'added'   => $added,
            'skipped' => $skip,
        ];
    }
}
