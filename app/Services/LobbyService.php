<?php
namespace App\Services;

use App\Core\Database;

/**
 * Sảnh game (lobby) — mỗi nhà phát hành 1 trang riêng.
 *
 * Mỗi sảnh có 2 chế độ:
 *   - 'seamless' : đồng bộ ví thật (cần operator token do nhà phát hành cấp)
 *   - 'demo_only' : chỉ chơi thử với tiền ảo, ai cũng chơi được
 *
 * Trang sảnh gồm 2 tab: "Chơi thử" (demo) và "Đồng bộ ví" (real).
 * Sảnh nào không có operator token sẽ tự động ẩn tab real và chỉ hiện demo.
 */
class LobbyService
{
    /**
     * Danh sách sảnh theo danh mục.
     */
    public static function providers(?string $category = null, bool $activeOnly = true): array
    {
        $where  = [];
        $params = [];

        if ($category !== null && $category !== '') {
            $where[] = 'category = ?';
            $params[] = $category;
        }
        if ($activeOnly) {
            $where[] = 'status = "active"';
        }

        $sql = 'SELECT p.*, c.name AS category_name, c.name_en AS category_name_en,
                       (SELECT COUNT(*) FROM games g WHERE g.provider_id = p.id AND g.status = "active") AS game_count
                FROM providers p
                LEFT JOIN game_categories c ON c.code = p.category';

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY p.sort_order, p.name';

        return Database::fetchAll($sql, $params);
    }

    public static function find(string $code): ?array
    {
        return Database::fetch(
            'SELECT p.*, c.name AS category_name, c.name_en AS category_name_en
             FROM providers p LEFT JOIN game_categories c ON c.code = p.category
             WHERE p.code = ? LIMIT 1',
            [$code]
        );
    }

    /**
     * Sảnh có hỗ trợ đồng bộ ví không?
     *
     * Chỉ sảnh khai báo wallet_mode = 'seamless'/'operator' MỚI đồng bộ ví.
     * Sảnh demo_only tuy có token trong config vẫn chỉ chơi thử —
     * không được quảng bá là đồng bộ ví 2 chiều khi thực tế không có.
     */
    public static function supportsWallet(array $provider): bool
    {
        $mode = strtolower(trim((string)($provider['wallet_mode'] ?? 'demo_only')));
        if ($mode !== 'seamless' && $mode !== 'operator') {
            return false;
        }

        $config = self::configOf($provider);
        return !empty($config['operator_token']) && !empty($config['secret_key']);
    }

    /**
     * Lấy config của provider (JSON trong DB, fallback config/app.php).
     */
    public static function configOf(array $provider): array
    {
        $code = (string)($provider['code'] ?? '');
        $cfg  = config('providers.' . $code, []);
        if (!is_array($cfg)) {
            $cfg = [];
        }

        $override = $provider['config'] ?? null;
        if (is_string($override) && $override !== '') {
            $decoded = json_decode($override, true);
            if (is_array($decoded)) {
                $cfg = array_merge($cfg, $decoded);
            }
        }

        // Một số nhà phát hành nằm trong config/app.php với tên khác (vd king-midas-games)
        foreach (config('providers', []) as $key => $conf) {
            if (!is_array($conf)) {
                continue;
            }
            if (strcasecmp((string)$key, $code) === 0) {
                $cfg = array_merge($conf, $cfg);
                break;
            }
        }

        return $cfg;
    }

    /**
     * Lấy danh sách game của 1 sảnh theo chế độ.
     *
     * @param string $mode 'demo' | 'real'
     */
    public static function games(array $provider, string $mode = 'demo', int $limit = 60, int $offset = 0, string $q = ''): array
    {
        $where  = ['g.provider_id = ?', 'g.status = "active"'];
        $params = [(int)$provider['id']];

        if ($q !== '') {
            $where[] = 'g.name LIKE ?';
            $params[] = '%' . $q . '%';
        }

        // Chế độ real: chỉ hiện game có thể chơi bằng tiền thật
        if ($mode === 'real') {
            $where[] = 'g.is_demo = 0';
        } else {
            $where[] = 'g.is_demo = 1';
        }

        $sql = 'SELECT g.* FROM games g WHERE ' . implode(' AND ', $where)
             . ' ORDER BY g.sort_order DESC, g.id DESC LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;

        return Database::fetchAll($sql, $params);
    }

    public static function countGames(array $provider, string $mode = 'demo', string $q = ''): int
    {
        $where  = ['g.provider_id = ?', 'g.status = "active"'];
        $params = [(int)$provider['id']];

        if ($q !== '') {
            $where[] = 'g.name LIKE ?';
            $params[] = '%' . $q . '%';
        }
        $where[] = $mode === 'real' ? 'g.is_demo = 0' : 'g.is_demo = 1';

        return (int)Database::fetchColumn(
            'SELECT COUNT(*) FROM games g WHERE ' . implode(' AND ', $where),
            $params
        );
    }

    /**
     * Danh mục kèm thống kê.
     */
    public static function categories(): array
    {
        return Database::fetchAll(
            'SELECT c.*,
                    (SELECT COUNT(*) FROM providers p WHERE p.category = c.code AND p.status = "active") AS provider_count,
                    (SELECT COUNT(*) FROM games g JOIN providers p2 ON p2.id = g.provider_id
                      WHERE p2.category = c.code AND g.status = "active") AS game_count
             FROM game_categories c WHERE c.is_active = 1 ORDER BY c.sort_order'
        );
    }

    /**
     * Sảnh có nên nhúng lobby trực tiếp không?
     *
     * Studio lớn (Casino Live, thể thao, xổ số…) không công bố API danh mục game
     * công khai. Thay vì bỏ trống, ta nhúng trực tiếp lobby/demo chính thức
     * của nhà phát hành trong 1 khung iframe.
     */
    public static function shouldEmbedLobby(array $provider): bool
    {
        $count = (int)Database::fetchColumn(
            'SELECT COUNT(*) FROM games WHERE provider_id = ? AND status = "active"',
            [(int)$provider['id']]
        );

        // Đã có danh mục game → hiện lưới game, không nhúng lobby
        if ($count > 0) {
            return false;
        }

        $cfg   = self::configOf($provider);
        $mode  = strtolower(trim((string)($cfg['embed_mode'] ?? '')));
        $lobby = trim((string)($cfg['lobby_url'] ?: ($provider['lobby_url'] ?? '')));

        if ($lobby === '' || !preg_match('#^https?://#i', $lobby)) {
            return false;
        }

        // Admin tắt nhúng → chỉ hiện danh sách game
        if ($mode === 'none') {
            return false;
        }

        // Admin ép nhúng (vd. Deno Casino) → luôn nhúng dù URL có dạng marketing
        if ($mode === 'always') {
            return true;
        }

        // URL là trang marketing (đăng nhập / nạp tiền / ví) → không nhúng,
        // nếu không sẽ hiện nguyên cả website trong khung iframe.
        if (self::isMarketingPage($lobby)) {
            return false;
        }

        return true;
    }

    /**
     * Phát hiện URL trang marketing (không phải lobby game).
     *
     * Nhiều nhà phát hành VN (168game.net, db789.io, vpslot.net...) đặt URL gốc
     * là trang landing/đăng ký. Nếu nhúng thẳng vào iframe, người dùng sẽ thấy
     * nguyên cả website của họ (form đăng ký, banner nạp tiền...) rất xấu.
     * => Phải chặn, chỉ cho mở tab mới.
     */
    public static function isMarketingPage(string $url): bool
    {
        $url = strtolower(trim($url));
        if ($url === '') {
            return true;
        }

        // Đường dẫn chắc chắn là chức năng tài chính / tài khoản
        $badPaths = [
            '/wallet', '/register', '/login', '/signup', '/sign-in', '/sign-up',
            '/deposit', '/withdraw', '/payment', '/nap-tien', '/rut-tien',
            '/promotion', '/promo', '/bonus', '/free', '/gift', '/giftcode',
            '/affiliate', '/agent', '/download', '/app',
        ];
        foreach ($badPaths as $bad) {
            if (str_contains($url, $bad)) {
                return true;
            }
        }

        // Tham số ref / utm → trang quảng bá
        if (preg_match('/[?&](ref|referrer|aff|affiliate|utm_|from=)/i', $url)) {
            return true;
        }

        // Domain dạng "cổng game" trỏ thẳng về trang chủ marketing
        $host = (string)parse_url($url, PHP_URL_HOST);
        $path = (string)parse_url($url, PHP_URL_PATH);
        $path = '/' . trim($path, '/');

        if ($path === '' || $path === '/') {
            return true;   // trang chủ = marketing
        }

        // Đuôi file .php/.html ở gốc => landing page, không phải lobby
        $segments = array_values(array_filter(explode('/', $path), 'strlen'));
        if (preg_match('#\.(php|html?|shtml)$#i', $path)) {
            $leaf = strtolower(basename($path));
            // Tên file chung chung ở cấp gốc => trang danh sách/marketing
            $generic = ['games.php', 'games.html', 'slots.php', 'lobby.php', 'default.php'];
            if (in_array($leaf, $generic, true)) {
                return true;
            }
            // File .php ở cấp gốc (không có thư mục con) => landing page
            // (file .html trong thư mục game vẫn là game hợp lệ)
            if (count($segments) <= 1 && preg_match('#\.php$#i', $leaf)) {
                return true;
            }
        }

        // Đường dẫn dạng /xxx-slot, /xxx-games mà không có đuôi file
        if (preg_match('#/(game|games|slot|slots|lobby)(-|$)#i', $path) && !str_contains($path, '/game/')) {
            return true;
        }

        return false;
    }

    /**
     * Chế độ chơi của sảnh: 'seamless' (đồng bộ ví) | 'demo'.
     *
     * Sảnh demo_only KHÔNG được quảng bá là đồng bộ ví.
     */
    public static function playMode(array $provider): string
    {
        $mode = strtolower(trim((string)($provider['wallet_mode'] ?? 'demo_only')));
        if ($mode === 'seamless' || $mode === 'operator') {
            return 'seamless';
        }
        return 'demo';
    }

    /**
     * Sảnh này có chơi được bằng tiền thật không?
     */
    public static function supportsRealPlay(array $provider): bool
    {
        return self::playMode($provider) === 'seamless';
    }

    /**
     * URL lobby để nhúng.
     *
     * Ưu tiên lobby_url đã được probe thủ công (host còn sống + nội dung thật).
     * fallback_lobby chỉ dùng khi lobby_url rỗng.
     */
    public static function embedUrl(array $provider): string
    {
        $cfg   = self::configOf($provider);
        $lobby = trim((string)($cfg['lobby_url'] ?? ($provider['lobby_url'] ?? '')));
        if ($lobby !== '') {
            return $lobby;
        }

        return trim((string)($cfg['fallback_lobby'] ?? ''));
    }

    public static function category(string $code): ?array
    {
        return Database::fetch('SELECT * FROM game_categories WHERE code = ? LIMIT 1', [$code]);
    }
}
