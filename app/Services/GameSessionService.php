<?php
namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Wallet;
use App\Services\Provider\ProviderFactory;

/**
 * Quản lý phiên chơi và đồng bộ số dư hai chiều với game server.
 *
 * Luồng:
 *  1. Player bấm "Chơi"  → GameSessionService::start() tạo session + token
 *  2. Player được redirect tới URL game kèm `ot={operator_token}`
 *  3. Game server gọi lại endpoint operator của ta:
 *       POST /operator/api/verifySession   → xác thực token, trả thông tin player
 *       POST /operator/api/wallet/get      → lấy số dư hiện tại
 *       POST /operator/api/wallet/change   → báo cáo cược/thắng, cập nhật ví
 *  4. Nhờ vậy mọi đồng nạp trên website đều được thấy ngay trong game.
 */
class GameSessionService
{
    /** Bắt đầu phiên chơi cho 1 user + 1 game */
    public static function start(int $userId, int $gameId, array $context = []): array
    {
        $game = Database::fetch(
            'SELECT g.*, p.code AS provider_code, p.driver, p.name AS provider_name
             FROM games g JOIN providers p ON p.id = g.provider_id
             WHERE g.id = ? AND g.status = "active"',
            [$gameId]
        );

        if (!$game) {
            throw new \RuntimeException('Không tìm thấy game');
        }

        // Đóng phiên cũ còn treo của user
        Database::update(
            'game_sessions',
            ['status' => 'closed', 'ended_at' => now()],
            'user_id = :u AND status = "active"',
            ['u' => $userId]
        );

        $sessionToken   = bin2hex(random_bytes(32));
        $operatorToken  = bin2hex(random_bytes(24));
        $balanceBefore  = Wallet::balance($userId);

        $sessionId = Database::insert('game_sessions', [
            'user_id'        => $userId,
            'game_id'        => $gameId,
            'provider_id'    => $game['provider_id'],
            'session_token'  => $sessionToken,
            'operator_token' => $operatorToken,
            'balance_before' => $balanceBefore,
            'balance_after'  => $balanceBefore,
            'status'         => 'active',
        ]);

        $provider = ProviderFactory::make($game['provider_code']);
        $launch   = $provider?->buildLaunchUrl($game, (array)Database::fetch('SELECT * FROM users WHERE id = ?', [$userId]), [
            'operator_token' => $operatorToken,
            'session_token'  => $sessionToken,
            'lang'           => 'vi',
        ]);

        if (!$launch) {
            Database::update('game_sessions', ['status' => 'closed', 'ended_at' => now()], 'id = :id', ['id' => $sessionId]);
            throw new \RuntimeException('Sảnh ' . $game['provider_name'] . ' chưa hỗ trợ phát game');
        }

        // URL phát game bị trùng với trang marketing (landing page của nhà phát hành).
        // Nếu không chặn, iframe sẽ hiển thị nguyên website của họ (form đăng ký, banner...)
        // → trả về thông báo "mở tab mới" thay vì nhúng.
        if (LobbyService::isMarketingPage((string)$launch['url'])) {
            Database::update('game_sessions', ['status' => 'closed', 'ended_at' => now()], 'id = :id', ['id' => $sessionId]);

            return [
                'session_id'     => $sessionId,
                'session_token'  => $sessionToken,
                'operator_token' => $operatorToken,
                'launch_url'     => (string)$launch['url'],
                'balance'        => $balanceBefore,
                'game'           => $game,
                'external_only'  => true,
            ];
        }

        audit('game.start', 'game_sessions', (string)$sessionId, [
            'game'    => $game['slug'],
            'balance' => $balanceBefore,
        ]);

        return [
            'session_id'     => $sessionId,
            'session_token'  => $sessionToken,
            'operator_token' => $operatorToken,
            'launch_url'     => $launch['url'],
            'balance'        => $balanceBefore,
            'game'           => $game,
        ];
    }

    /** Tìm session theo operator token (game server gọi) */
    public static function findByOperatorToken(string $token): ?array
    {
        return Database::fetch(
            'SELECT gs.*, u.username, u.email, u.full_name, g.name AS game_name, g.slug AS game_slug,
                    g.provider_id, p.code AS provider_code
             FROM game_sessions gs
             JOIN users u     ON u.id = gs.user_id
             JOIN games g     ON g.id = gs.game_id
             JOIN providers p ON p.id = gs.provider_id
             WHERE gs.operator_token = ? LIMIT 1',
            [$token]
        );
    }

    public static function findBySessionToken(string $token): ?array
    {
        return Database::fetch(
            'SELECT gs.*, u.username, g.name AS game_name, g.slug AS game_slug,
                    g.provider_id, p.code AS provider_code
             FROM game_sessions gs
             JOIN users u     ON u.id = gs.user_id
             JOIN games g     ON g.id = gs.game_id
             JOIN providers p ON p.id = gs.provider_id
             WHERE gs.session_token = ? LIMIT 1',
            [$token]
        );
    }

    /**
     * Heartbeat: giữ phiên sống.
     *
     * Lưu ý: rowCount() của MySQL trả 0 khi giá trị không thực sự thay đổi,
     * nên ta kiểm tra sự tồn tại của session thay vì dựa vào rowCount.
     */
    public static function heartbeat(string $sessionToken): bool
    {
        $exists = Database::fetchColumn(
            'SELECT id FROM game_sessions WHERE session_token = ? AND status = "active" LIMIT 1',
            [$sessionToken]
        );
        if (!$exists) {
            return false;
        }

        // Gia hạn phiên: đẩy mốc thời gian bắt đầu về hiện tại để không bị dọn
        Database::update(
            'game_sessions',
            ['started_at' => now(), 'ended_at' => null],
            'session_token = :t AND status = "active"',
            ['t' => $sessionToken]
        );

        return true;
    }

    public static function close(string $sessionToken): void
    {
        Database::update(
            'game_sessions',
            ['status' => 'closed', 'ended_at' => now()],
            'session_token = :t AND status = "active"',
            ['t' => $sessionToken]
        );
    }

    /**
     * Game server báo cáo thay đổi số dư (cược / thắng / hoàn).
     *
     * @param string $operatorToken token do game server gửi
     * @param string $type         bet|win|refund|deposit|withdraw
     * @param float  $amount      dương = cộng, âm = trừ
     */
    public static function applyWalletChange(string $operatorToken, string $type, float $amount, array $meta = []): array
    {
        $session = self::findByOperatorToken($operatorToken);
        if (!$session) {
            return ['success' => false, 'message' => 'Phiên không hợp lệ', 'code' => '1301'];
        }

        $userId = (int)$session['user_id'];

        // Idempotent: cùng transaction_id sẽ không ghi lại vào ví.
        // PGSoft có thể gửi lại request khi mạng lỗi.
        $txId = (string)($meta['transaction_id'] ?? $meta['transactionId'] ?? $meta['tx_id'] ?? '');
        if ($txId !== '') {
            $existing = Database::fetch(
                'SELECT id, balance_after FROM game_rounds WHERE session_id = ? AND round_id = ? LIMIT 1',
                [$session['id'], $txId]
            );
            if ($existing) {
                return ['success' => true, 'balance' => (float)$existing['balance_after'], 'duplicate' => true];
            }
        }

        $map = [
            'bet'      => 'bet',
            'win'      => 'win',
            'refund'   => 'refund',
            'deposit'  => 'deposit',
            'withdraw' => 'withdraw',
        ];
        $ledgerType = $map[$type] ?? 'adjustment';

        // Quy ước: "bet" luôn làm GIẢM số dư, "win"/"refund"/"deposit" làm TĂNG.
        // Game server gửi amount dương theo nghĩa "số tiền cược".
        if ($type === 'bet') {
            $amount = -abs($amount);
        } elseif (in_array($type, ['win', 'refund', 'deposit'], true)) {
            $amount = abs($amount);
        }

        try {
            $balanceAfter = Wallet::apply(
                $userId,
                $ledgerType,
                $amount,
                self::describeChange($type, $amount, $session),
                'game_session',
                (string)$session['id'],
                $meta
            );
        } catch (\RuntimeException $e) {
            // Không đủ tiền → trả lỗi về game để nó từ chối cược
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'balance' => Wallet::balance($userId),
                'code'    => '3200',
            ];
        }

        Database::update(
            'game_sessions',
            [
                'balance_after' => $balanceAfter,
                'wagered'       => $session['wagered'] + ($ledgerType === 'bet' ? abs($amount) : 0),
            ],
            'id = :id',
            ['id' => $session['id']]
        );

        if ($ledgerType === 'bet' || $ledgerType === 'win') {
            Database::insert('game_rounds', [
                'session_id'    => $session['id'],
                'round_id'      => $txId !== ''
                    ? $txId
                    : ($meta['roundId'] ?? $meta['round_id'] ?? null),
                'bet'           => $type === 'bet' ? abs($amount) : 0,
                'win'           => $type === 'win' ? abs($amount) : 0,
                'balance_after' => $balanceAfter,
                'raw'           => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
            ]);
        }

        return ['success' => true, 'balance' => $balanceAfter];
    }

    private static function describeChange(string $type, float $amount, array $session): string
    {
        $game = $session['game_name'] ?? 'Game';
        return match ($type) {
            'bet'      => 'Cược ' . $game . ': ' . number_format(abs($amount)),
            'win'      => 'Thắng ' . $game . ': ' . number_format(abs($amount)),
            'refund'   => 'Hoàn ' . $game . ': ' . number_format(abs($amount)),
            'deposit'  => 'Nạp từ game: ' . number_format(abs($amount)),
            'withdraw' => 'Rút về ví: ' . number_format(abs($amount)),
            default    => 'Cập nhật từ game',
        };
    }

    /** Tổng quan phiên chơi của user */
    public static function userSessions(int $userId, int $limit = 20): array
    {
        return Database::fetchAll(
            'SELECT gs.*, g.name AS game_name, g.slug AS game_slug, g.icon
             FROM game_sessions gs JOIN games g ON g.id = gs.game_id
             WHERE gs.user_id = ? ORDER BY gs.id DESC LIMIT ' . (int)$limit,
            [$userId]
        );
    }

    /** Dọn phiên quá hạn (cron) */
    public static function cleanupExpired(int $timeoutSeconds = 120): int
    {
        return Database::update(
            'game_sessions',
            ['status' => 'expired', 'ended_at' => now()],
            'status = "active" AND started_at < (NOW() - INTERVAL ' . (int)$timeoutSeconds . ' SECOND)',
            []
        );
    }
}
