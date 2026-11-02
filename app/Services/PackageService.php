<?php
namespace App\Services;

use App\Core\Database;
use App\Core\Wallet;

/**
 * Gói chơi: gói trải nghiệm (free trial) và gói thuê theo thời gian.
 *
 * Mỗi gói đã mua sinh 1 `user_packages` đang active.
 * Phiên chơi được phép chạy khi còn gói active HOẶC game miễn phí.
 */
class PackageService
{
    public static function listActive(): array
    {
        return Database::fetchAll('SELECT * FROM packages WHERE is_active = 1 ORDER BY sort_order, price');
    }

    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM packages WHERE id = ?', [$id]);
    }

    /**
     * Người dùng có gói đang hiệu lực không.
     */
    public static function activePackageFor(int $userId): ?array
    {
        return Database::fetch(
            'SELECT up.*, p.name AS package_name, p.slug, p.duration_minutes
             FROM user_packages up JOIN packages p ON p.id = up.package_id
             WHERE up.user_id = ? AND up.status = "active" AND up.expires_at > NOW()
             ORDER BY up.expires_at DESC LIMIT 1',
            [$userId]
        );
    }

    /** Gói active có hỗ trợ provider cụ thể không (null = tất cả) */
    public static function activePackageForProvider(int $userId, string $providerCode): ?array
    {
        $pkg = self::activePackageFor($userId);
        if (!$pkg) {
            return null;
        }
        $scope = Database::fetchColumn('SELECT provider_code FROM packages WHERE id = ?', [$pkg['package_id']]);
        if ($scope && $scope !== $providerCode) {
            return null;
        }
        return $pkg;
    }

    /**
     * Mua gói. Trừ tiền trong ví và tạo user_packages.
     *
     * @return array{success:bool, message:string, user_package_id?:int}
     */
    public static function purchase(int $userId, int $packageId, array $options = []): array
    {
        $pkg = self::find($packageId);
        if (!$pkg || !$pkg['is_active']) {
            return ['success' => false, 'message' => 'Gói không tồn tại hoặc đã ngừng bán'];
        }

        // Gói trải nghiệm: giới hạn số lần mỗi user
        if ($pkg['is_free_trial']) {
            $limit = (int)$pkg['trial_limit_per_user'];
            $used  = (int)Database::fetchColumn(
                'SELECT COUNT(*) FROM user_packages WHERE user_id = ? AND package_id = ?',
                [$userId, $packageId]
            );
            if ($used >= $limit) {
                return ['success' => false, 'message' => 'Bạn đã dùng hết lượt trải nghiệm của gói này'];
            }
        }

        // Trừ tiền nếu gói có giá
        if ((float)$pkg['price'] > 0) {
            try {
                Wallet::apply(
                    $userId,
                    'deposit',
                    -((float)$pkg['price']),
                    'Mua gói: ' . $pkg['name'],
                    'package',
                    (string)$packageId,
                    ['package' => $pkg['slug']]
                );
            } catch (\RuntimeException $e) {
                return ['success' => false, 'message' => $e->getMessage()];
            }
        }

        // Tính thời gian: nếu đã có gói thì cộng dồn
        $existing = self::activePackageFor($userId);
        $startAt  = $existing ? new \DateTime($existing['expires_at']) : new \DateTime();
        $expires  = $startAt->modify('+' . (int)$pkg['duration_minutes'] . ' minutes');

        $upId = Database::insert('user_packages', [
            'user_id'        => $userId,
            'package_id'     => $packageId,
            'session_token'  => bin2hex(random_bytes(24)),
            'started_at'     => $startAt->format('Y-m-d H:i:s'),
            'expires_at'     => $expires->format('Y-m-d H:i:s'),
            'remaining_seconds' => (int)$pkg['duration_minutes'] * 60,
            'status'         => 'active',
        ]);

        audit('package.purchase', 'user_packages', (string)$upId, [
            'package' => $pkg['slug'],
            'price'   => $pkg['price'],
        ]);

        return [
            'success'          => true,
            'message'          => 'Đã kích hoạt gói ' . $pkg['name'],
            'user_package_id'  => $upId,
            'expires_at'       => $expires->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Kiểm tra user có được chơi game thuộc provider này không.
     *
     * @return array{allowed:bool, reason:string, package:?array}
     */
    public static function canPlay(int $userId, string $providerCode, bool $isFreeGame): array
    {
        $pkg = self::activePackageForProvider($userId, $providerCode);

        if ($pkg) {
            return ['allowed' => true, 'reason' => 'active_package', 'package' => $pkg];
        }

        if ($isFreeGame) {
            return ['allowed' => true, 'reason' => 'free_game', 'package' => null];
        }

        $requireDeposit = (bool)setting('require_deposit_for_play', '1');
        if (!$requireDeposit) {
            return ['allowed' => true, 'reason' => 'free_play', 'package' => null];
        }

        return [
            'allowed' => false,
            'reason'  => 'no_package',
            'package' => null,
        ];
    }

    /** Số giây còn lại của gói active */
    public static function remainingSeconds(int $userId): int
    {
        $pkg = self::activePackageFor($userId);
        if (!$pkg) {
            return 0;
        }
        $diff = strtotime($pkg['expires_at']) - time();
        return max(0, $diff);
    }

    /** Đánh dấu hết hạn các gói đã qua */
    public static function expireOld(): int
    {
        return Database::update(
            'user_packages',
            ['status' => 'expired'],
            'status = "active" AND expires_at <= NOW()',
            []
        );
    }
}
