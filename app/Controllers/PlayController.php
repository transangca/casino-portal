<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\CSRF;
use App\Core\Database;
use App\Core\Wallet;
use App\Services\GameSessionService;
use App\Services\PackageService;

/**
 * Trang chơi game + các API nội bộ của phiên chơi.
 */
class PlayController
{
    /**
     * Trang trước khi vào game: kiểm tra quyền chơi, tạo session, chuyển hướng.
     */
    public function show(string $slug): void
    {
        if (Auth::guest()) {
            $_SESSION['intended'] = url('/play/' . $slug);
            flash('error', 'Vui lòng đăng nhập để chơi.');
            redirect(url('/login'));
        }

        $game = Database::fetch(
            'SELECT g.*, p.code AS provider_code, p.name AS provider_name
             FROM games g JOIN providers p ON p.id = g.provider_id
             WHERE g.slug = ? AND g.status = "active" LIMIT 1',
            [$slug]
        );

        if (!$game) {
            abort(404, 'Không tìm thấy game');
        }

        $userId  = (int)Auth::id();
        $check   = PackageService::canPlay($userId, (string)$game['provider_code'], (bool)$game['is_free']);
        $balance = Wallet::balance($userId);

        // Không đủ quyền chơi → hiện màn hình mua gói
        if (!$check['allowed']) {
            $packages = PackageService::listActive();
            view('games/locked', compact('game', 'packages', 'balance', 'check'));
            return;
        }

        // Tạo phiên chơi
        try {
            $session = GameSessionService::start($userId, (int)$game['id']);
        } catch (\Throwable $e) {
            logger('game start failed: ' . $e->getMessage(), 'error');
            flash('error', 'Không thể khởi tạo phiên chơi: ' . $e->getMessage());
            redirect(url('/games/' . $slug));
        }

        // Sảnh này chỉ có landing page, không có URL game chơi được
        // → mở tab mới thay vì nhúng iframe (tránh hiện nguyên website nhà phát hành).
        if (!empty($session['external_only'])) {
            view('games/external', [
                'game'     => $game,
                'launchUrl' => (string)$session['launch_url'],
            ]);
            return;
        }

        view('games/play', compact('game', 'session', 'balance', 'check'));
    }

    /**
     * API: game client hỏi URL embed + token phiên.
     */
    public function resolve(): void
    {
        CSRF::verifyOrFail();

        $token = (string)($_POST['session_token'] ?? '');
        if ($token === '') {
            json_error('Thiếu session_token', 422);
        }

        $session = GameSessionService::findBySessionToken($token);
        if (!$session || $session['status'] !== 'active') {
            json_error('Phiên không hợp lệ hoặc đã hết hạn', 401);
        }

        $game = Database::fetch('SELECT * FROM games WHERE id = ?', [(int)$session['game_id']]);
        if (!$game) {
            json_error('Không tìm thấy game', 404);
        }

        // Tạo URL launch mới (token operator giữ nguyên)
        $provider = \App\Services\Provider\ProviderFactory::make((string)$session['provider_code']);
        $launch   = $provider?->buildLaunchUrl($game, (array)Auth::user(), [
            'operator_token' => $session['operator_token'],
            'lang'           => 'vi',
        ]);

        if (!$launch) {
            json_error('Sảnh chưa hỗ trợ phát game', 500);
        }

        // Kiểm tra lại quyền chơi (gói có thể vừa hết hạn)
        $check = PackageService::canPlay((int)$session['user_id'], (string)$session['provider_code'], (bool)$game['is_free']);
        if (!$check['allowed']) {
            json_error('Gói chơi đã hết hạn. Vui lòng mua thêm gói.', 403);
        }

        json_response([
            'success'   => true,
            'embed'     => url('play/embed?t=' . $session['session_token']),
            'launch'    => $launch['url'],
            'remaining' => PackageService::remainingSeconds((int)$session['user_id']),
            'balance'   => Wallet::balance((int)$session['user_id']),
            'is_free'   => (bool)$game['is_free'],
        ]);
    }

    /**
     * Trang nhúng game — chỉ phục vụ nội dung embed cho game server.
     */
    public function embed(string $token): void
    {
        $session = GameSessionService::findBySessionToken($token);
        if (!$session) {
            http_response_code(401);
            echo '<!doctype html><html><body>Session invalid</body></html>';
            exit;
        }

        $game = Database::fetch('SELECT * FROM games WHERE id = ?', [(int)$session['game_id']]);
        if (!$game) {
            http_response_code(404);
            echo 'Game not found';
            exit;
        }

        $provider = \App\Services\Provider\ProviderFactory::make((string)$session['provider_code']);
        $launch   = $provider?->buildLaunchUrl($game, (array)Database::fetch('SELECT * FROM users WHERE id = ?', [$session['user_id']]), [
            'operator_token' => $session['operator_token'],
            'lang'           => 'vi',
        ]);

        if (!$launch) {
            http_response_code(500);
            echo 'Provider error';
            exit;
        }

        // Trang wrapper: nạp game trong iframe, giữ heartbeat với server
        view('games/embed', [
            'game'        => $game,
            'session'     => $session,
            'launchUrl'   => $launch['url'],
            'balance'     => Wallet::balance((int)$session['user_id']),
            'csrf'        => csrf_token(),
        ], null);
    }

    /**
     * Heartbeat — giữ phiên chơi và trả về trạng thái gói.
     */
    public function heartbeat(): void
    {
        CSRF::verifyOrFail();

        $token = (string)($_POST['session_token'] ?? '');
        $alive = GameSessionService::heartbeat($token);

        $session = GameSessionService::findBySessionToken($token);
        if (!$session) {
            json_error('Phiên không hợp lệ', 401);
        }

        $remaining = PackageService::remainingSeconds((int)$session['user_id']);
        $expired  = !$alive || ($remaining <= 0 && !$this->isFreeGame($session));

        json_response([
            'success'   => true,
            'alive'     => $alive,
            'expired'   => $expired,
            'action'    => $expired ? 'end' : 'ok',
            'remaining' => $remaining,
            'balance'   => Wallet::balance((int)$session['user_id']),
            'message'   => $expired ? 'Phiên chơi đã kết thúc' : 'OK',
        ]);
    }

    public function close(): void
    {
        CSRF::verifyOrFail();
        $token = (string)($_POST['session_token'] ?? '');
        GameSessionService::close($token);
        json_response(['success' => true]);
    }

    private function isFreeGame(array $session): bool
    {
        return (bool)Database::fetchColumn('SELECT is_free FROM games WHERE id = ?', [(int)$session['game_id']]);
    }
}
