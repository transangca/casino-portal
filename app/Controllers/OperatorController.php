<?php
namespace App\Controllers;

use App\Core\Database;
use App\Core\Wallet;
use App\Services\GameSessionService;

/**
 * Operator API — điểm tiếp xúc giữa game server (sảnh) và ví của website.
 *
 * Tuân thủ chuẩn Seamless/Transfer Wallet của các nhà phát hành:
 *
 *   POST /operator/api/verifySession   → xác thực token phiên, trả thông tin player
 *   POST /operator/api/wallet/get      → lấy số dư hiện tại của player
 *   POST /operator/api/wallet/change   → báo cáo cược/thắng, cập nhật ví
 *   POST /operator/api/game/info       → lấy thông tin game
 *
 * Chuẩn PGSoft dùng đường dẫn /Cash/Get và /Cash/Change; ta expose thêm alias
 * để các sảnh khác (KingMidas, JILI, CQ9...) dùng chung một endpoint.
 *
 * Mọi thay đổi số dư đều đi qua Wallet::apply() trong transaction + khoá dòng,
 * nên tiền nạp trên website luôn khớp với tiền trong game (đồng bộ 2 chiều).
 */
class OperatorController
{
    /** Chuẩn PGSoft: mã lỗi */
    private const ERR_INVALID_REQUEST = '1034';
    private const ERR_PLAYER_NOT_FOUND = '3004';
    private const ERR_WALLET_NOT_FOUND  = '3005';
    private const ERR_INTERNAL         = '1200';
    private const ERR_NOT_ENOUGH       = '3200';

    /** Lấy token phiên từ body (form hoặc JSON) hoặc query */
    private function token(): string
    {
        $body = json_decode((string)file_get_contents('php://input'), true);
        return (string)(
            $body['token']
            ?? $body['ot']
            ?? $body['operator_player_session']
            ?? $body['ops']
            ?? $body['OperatorPlayerSession']
            ?? $_GET['token']
            ?? $_GET['ot']
            ?? $_GET['operator_player_session']
            ?? $_POST['token']
            ?? $_POST['ot']
            ?? ''
        );
    }

    /**
     * Đọc body dạng JSON hoặc form-urlencoded.
     */
    private function body(): array
    {
        $raw = (string)file_get_contents('php://input');
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
            parse_str($raw, $form);
            return $form ?: [];
        }
        return $_POST ?: [];
    }

    /**
     * Chuẩn PGSoft: VerifySession.
     *
     * Game server gửi: operator_token, secret_key, operator_player_session,
     * bet_type, game_id, ip.
     * Ta trả về: player_name, nickname, currency.
     */
    public function verifySession(): void
    {
        $token   = $this->token();
        $session = $token !== '' ? GameSessionService::findByOperatorToken($token) : null;

        if (!$session) {
            $this->fail('Player session is empty', self::ERR_INVALID_REQUEST);
        }

        $user = Database::fetch('SELECT * FROM users WHERE id = ?', [(int)$session['user_id']]);
        if (!$user) {
            $this->fail('Player not found', self::ERR_PLAYER_NOT_FOUND);
        }

        $this->ok([
            'player_name' => $this->playerName($user),
            'nickname'    => (string)($user['full_name'] ?: $user['username']),
            'currency'    => 'VND',
            'language'    => 'vi',
            'country'     => 'VN',
            'isDemo'      => false,
            'balance'     => Wallet::balance((int)$user['id']),
        ]);
    }

    /**
     * Chuẩn PGSoft: Cash/Get — lấy số dư.
     */
    public function walletGet(): void
    {
        $token   = $this->token();
        $session = $token !== '' ? GameSessionService::findByOperatorToken($token) : null;

        if (!$session) {
            $this->fail('Player session is empty', self::ERR_INVALID_REQUEST);
        }

        $balance = Wallet::balance((int)$session['user_id']);

        $this->ok([
            'currency_code'  => 'VND',
            'balance_amount' => $balance,
            'updated_time'   => (int)(microtime(true) * 1000),
            'balance'        => $balance,
            'playerId'       => (string)$session['user_id'],
        ]);
    }

    /**
     * Chuẩn PGSoft: Cash/Change — báo cáo cược / thắng / hoàn.
     *
     * Payload mẫu:
     *   { token, type: "bet"|"win"|"refund", amount: 1000, transaction_id: "..." }
     * amount dương = cộng vào ví, âm = trừ khỏi ví.
     *
     * Idempotent theo transaction_id: cùng transaction_id sẽ trả lại kết quả
     * cũ mà không cộng/trừ thêm (PGSoft có thể gửi lại khi mạng lỗi).
     */
    public function walletChange(): void
    {
        $token   = $this->token();
        $body    = $this->body();
        $session = $token !== '' ? GameSessionService::findByOperatorToken($token) : null;

        if (!$session) {
            $this->fail('Player session is empty', self::ERR_INVALID_REQUEST);
        }

        $type   = (string)($body['type'] ?? $body['Type'] ?? 'adjustment');
        $amount = (float)($body['amount'] ?? $body['Amount'] ?? 0);

        if ($amount == 0.0) {
            $this->ok(['balance' => Wallet::balance((int)$session['user_id'])]);
        }

        $result = GameSessionService::applyWalletChange($token, $type, $amount, $body);

        if (!$result['success']) {
            $code = ($result['code'] ?? '') !== '' ? (string)$result['code'] : self::ERR_INTERNAL;
            $this->fail(
                $result['message'] ?? 'Cập nhật số dư thất bại',
                $code,
                ['balance' => $result['balance'] ?? 0]
            );
        }

        $this->ok(['balance' => $result['balance']]);
    }

    /**
     * Thông tin game.
     */
    public function gameInfo(): void
    {
        $token   = $this->token();
        $session = $token !== '' ? GameSessionService::findByOperatorToken($token) : null;

        if (!$session) {
            $this->fail('Player session is empty', self::ERR_INVALID_REQUEST);
        }

        $game = Database::fetch('SELECT * FROM games WHERE id = ?', [(int)$session['game_id']]);

        $this->ok([
            'game' => [
                'id'    => (string)($session['provider_game_id'] ?? $session['game_id']),
                'name'  => (string)$session['game_name'],
                'slug'  => (string)$session['game_slug'],
                'icon'  => media_url((string)($game['icon'] ?? '')),
                'type'  => (string)($game['game_type'] ?? 'slot'),
            ],
        ]);
    }

    /**
     * Danh sách game của 1 sảnh (dùng cho client đồng bộ danh mục).
     */
    public function gameList(): void
    {
        $provider = (string)($_GET['provider'] ?? 'pgsoft');

        $games = Database::fetchAll(
            'SELECT g.provider_game_id, g.name, g.slug, g.icon, g.game_type
             FROM games g JOIN providers p ON p.id = g.provider_id
             WHERE p.code = ? AND g.status = "active"
             ORDER BY g.sort_order DESC LIMIT 500',
            [$provider]
        );

        $this->ok(['games' => $games, 'total' => count($games)]);
    }

    // ---------------- Response helpers ----------------

    /**
     * Chuẩn PGSoft: luôn HTTP 200, lỗi nằm trong `error`.
     */
    private function ok(array $data): never
    {
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'dt'   => $data,
            'data' => $data,
            'err'  => null,
            'error' => null,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function fail(string $message, string $code = self::ERR_INVALID_REQUEST, array $extra = []): never
    {
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'dt'    => $extra,
            'data'  => null,
            'err'   => ['cd' => $code, 'msg' => $message, 'tid' => $this->traceId(), 'at' => 0],
            'error' => ['code' => $code, 'message' => $message],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Chuẩn PGSoft: player_name chỉ gồm chữ/số/@/-/_ và tối đa 50 ký tự.
     */
    private function playerName(array $user): string
    {
        $name = (string)($user['username'] ?? ('player' . $user['id']));
        $name = preg_replace('/[^A-Za-z0-9@\-_]/', '', $name) ?? '';
        return substr($name !== '' ? $name : ('player' . $user['id']), 0, 50);
    }

    private function traceId(): string
    {
        return substr(strtoupper(bin2hex(random_bytes(4))), 0, 8);
    }
}
