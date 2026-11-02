<?php
/**
 * Seed dữ liệu khởi tạo: providers, packages, games PGSoft thật, admin account.
 * Chạy: php database/seed.php
 */

define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $path = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

require_once BASE_PATH . '/app/Support/helpers.php';

use App\Core\Database;
use App\Core\Wallet;

date_default_timezone_set('Asia/Ho_Chi_Minh');

try {
    Database::conn();
} catch (Throwable $e) {
    fwrite(STDERR, "Khong ket noi duoc CSDL: {$e->getMessage()}\n");
    exit(1);
}

echo "Da ket noi CSDL.\n";

$db = Database::conn();
$db->exec("SET NAMES utf8mb4");

// ------------------------------------------------------------------
// 1. Providers
// ------------------------------------------------------------------
$providers = [
    ['pgsoft', 'PGSoft', 'pgsoft', 'https://m.pg-demo.com/{game_id}/index.html', 1],
    ['king-midas-games', 'KingMidas', 'king-midas-games', 'https://m.kingmidasgames.net/{game_id}/index.html', 2],
    ['internal', 'Sanh noi bo', 'internal', null, 99],
];

foreach ($providers as [$code, $name, $driver, $baseUrl, $sort]) {
    $exists = Database::fetchColumn('SELECT id FROM providers WHERE code = ?', [$code]);
    if ($exists) {
        echo "  - provider $code: da ton tai\n";
        continue;
    }
    Database::insert('providers', [
        'code'      => $code,
        'name'      => $name,
        'driver'    => $driver,
        'base_url'  => $baseUrl,
        'status'    => 'active',
        'sort_order'=> $sort,
    ]);
    echo "  + provider $code\n";
}

// ------------------------------------------------------------------
// 2. Packages
// ------------------------------------------------------------------
$packages = [
    ['Goi thu nghiem 5 phut', 'trial-5', 5, 0, 0, 1, 1, 'Cho nguoi moi trai nghiem game mien phi', 1],
    ['Goi 1 gio', 'pkg-1h', 60, 20000, 30000, 0, 1, 'Choi 1 gio voi tat ca sanh', 2],
    ['Goi 3 gio', 'pkg-3h', 180, 50000, 80000, 0, 1, 'Choi 3 gio - uu dai hon', 3],
    ['Goi 1 ngay', 'pkg-24h', 1440, 150000, 250000, 0, 1, 'Choi ca ngay khong gioi han', 4],
    ['Goi 1 tuan', 'pkg-7d', 10080, 800000, 1200000, 0, 1, 'Tuyet doi cho ca tuan', 5],
    ['Goi thang', 'pkg-30d', 43200, 2800000, 4000000, 0, 1, 'Dung cho ca thang', 6],
];

foreach ($packages as [$name, $slug, $mins, $price, $orig, $trial, $limit, $desc, $sort]) {
    $exists = Database::fetchColumn('SELECT id FROM packages WHERE slug = ?', [$slug]);
    if ($exists) {
        continue;
    }
    Database::insert('packages', [
        'name'                => $name,
        'slug'                => $slug,
        'description'         => $desc,
        'duration_minutes'    => $mins,
        'price'               => $price,
        'original_price'      => $orig ?: null,
        'is_free_trial'       => $trial,
        'trial_limit_per_user'=> $limit,
        'is_active'           => 1,
        'sort_order'          => $sort,
    ]);
    echo "  + package $slug\n";
}

// 3. Games PGSoft that (lay tu manifest that)
// ------------------------------------------------------------------
$pgId = (int)Database::fetchColumn('SELECT id FROM providers WHERE code = "pgsoft"');

// Tai manifest de lay icon hash
$manifest = [];
$cacheFile = BASE_PATH . '/storage/pgsoft_manifest.json';
if (is_file($cacheFile)) {
    $manifest = json_decode((string)file_get_contents($cacheFile), true) ?: [];
}
if (!$manifest) {
    echo "  ... tai manifest tu public.pg-demo.com\n";
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://public.pg-demo.com/pages/hash-manifest.json',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0',
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);
    if ($raw) {
        $manifest = json_decode((string)$raw, true) ?: [];
        @file_put_contents($cacheFile, $raw);
    }
}

function icon_for(array $manifest, string $gameId): ?string
{
    foreach ($manifest as $original => $hashed) {
        if (preg_match('#^image/en/Small_Icon/' . preg_quote($gameId, '#') . '/[^/]+\.png$#', $original)) {
            return 'https://public.pg-demo.com/pages/static/' . $hashed;
        }
    }
    return null;
}

// Dung provider that de lay ten game + loc game da bi go khoi lobby
$pgProvider = new App\Services\Provider\PgSoftProvider();
$pgGames    = $pgProvider->fetchGames();
echo "  ... provider tra ve " . count($pgGames) . " game PGSoft\n";

$hotIds      = ['65', '89', '24', '62', '105'];
$featuredIds = ['65', '89', '62'];

$added = 0;
foreach ($pgGames as $g) {
    $gid = (string)($g['game_id'] ?? '');
    if ($gid === '') {
        continue;
    }

    $exists = Database::fetchColumn(
        'SELECT id FROM games WHERE provider_id = ? AND provider_game_id = ?',
        [$pgId, $gid]
    );
    if ($exists) {
        continue;
    }

    $gname = $g['name'] ?: ('PG ' . $gid);

    Database::insert('games', [
        'provider_id'      => $pgId,
        'provider_game_id' => $gid,
        'slug'             => slugify($gname) ?: ('pg-' . $gid),
        'name'             => $gname,
        'description'      => 'Game slot PGSoft chinh hang. RTP cao, gameplay muot ma tren moi thiet bi.',
        'icon'             => $g['icon'] ?? icon_for($manifest, $gid),
        'embed_url'        => $g['embed_url'] ?? ("https://m.pg-demo.com/{$gid}/index.html"),
        'demo_url'         => $g['demo_url'] ?? ("https://m.pg-demo.com/{$gid}/index.html"),
        'game_type'        => $g['game_type'] ?? 'slot',
        'is_free'          => 0,
        'is_featured'      => in_array($gid, $featuredIds, true) ? 1 : 0,
        'is_hot'           => in_array($gid, $hotIds, true) ? 1 : 0,
        'is_new'           => in_array($gid, $hotIds, true) ? 0 : 1,
        'rtp'              => 96.50,
        'min_bet'          => 1000,
        'max_bet'          => 5000000,
        'sort_order'       => random_int(1, 100),
        'status'           => 'active',
    ]);
    $added++;
}
echo "  + $added game PGSoft\n";

// ------------------------------------------------------------------
// 4. Admin account
// ------------------------------------------------------------------
$adminExists = Database::fetchColumn('SELECT id FROM users WHERE role IN ("admin","operator") LIMIT 1');
if (!$adminExists) {
    Database::insert('users', [
        'username'      => 'admin',
        'email'         => 'admin@localhost',
        'password_hash' => password_hash('admin123', PASSWORD_DEFAULT),
        'full_name'     => 'Quan Tri Vien',
        'role'          => 'admin',
        'status'        => 'active',
        'balance'       => 0,
    ]);
    echo "  + tai khoan admin (admin / admin123)\n";
} else {
    echo "  - tai khoan admin da ton tai\n";
}

// ------------------------------------------------------------------
// 4b. Game noi bo (HTML5 tu host) - ho tro them sanh game
// ------------------------------------------------------------------
$intId = (int)Database::fetchColumn('SELECT id FROM providers WHERE code = "internal"');
$internalGames = [
    ['Neon Reels 7',        'neon-reels-7',    ['engine'=>'slot','reels'=>3,'rows'=>3,'paylines'=>7,'rtp'=>96.20], 'hot',    1],
    ['Golden Fortune Spin', 'golden-fortune', ['engine'=>'slot','reels'=>5,'rows'=>3,'paylines'=>20,'rtp'=>96.50], 'featured',1],
    ['Diamond Vault',       'diamond-vault',   ['engine'=>'slot','reels'=>3,'rows'=>3,'paylines'=>9,'rtp'=>95.80], 'new',    0],
    ['Lucky Koi Paradise',  'lucky-koi',       ['engine'=>'slot','reels'=>3,'rows'=>3,'paylines'=>5,'rtp'=>96.00], 'new',    0],
];
foreach ($internalGames as [$gname, $gslug, $gcfg, $flag, $feat]) {
    $exists = Database::fetchColumn(
        'SELECT id FROM games WHERE provider_id = ? AND provider_game_id = ?',
        [$intId, $gslug]
    );
    if ($exists) {
        continue;
    }
    Database::insert('games', [
        'provider_id'      => $intId,
        'provider_game_id' => $gslug,
        'slug'             => $gslug,
        'name'             => $gname,
        'description'      => 'Game slot HTML5 sanh noi bo. Ho tro dong bo so du hai chieu truc tiep voi vi website.',
        'icon'             => null,
        'embed_url'        => url('game/' . $gslug . '/play'),
        'demo_url'         => url('game/' . $gslug . '/play'),
        'game_type'        => 'slot',
        'is_free'          => 0,
        'is_featured'      => $feat,
        'is_hot'           => $flag === 'hot' ? 1 : 0,
        'is_new'           => $flag === 'new' ? 1 : 0,
        'rtp'              => $gcfg['rtp'],
        'min_bet'          => 1000,
        'max_bet'          => 5000000,
        'sort_order'       => random_int(1, 100),
        'game_config'      => json_encode($gcfg, JSON_UNESCAPED_UNICODE),
        'status'           => 'active',
    ]);
    echo "  + game noi bo $gslug\n";
}

// ------------------------------------------------------------------
// 5. Giftcode mau
// ------------------------------------------------------------------
$gcExists = Database::fetchColumn('SELECT id FROM giftcodes WHERE code = ?', ['WELCOME50K']);
if (!$gcExists) {
    Database::insert('giftcodes', [
        'code'        => 'WELCOME50K',
        'amount'      => 50000,
        'bonus_amount'=> 0,
        'min_deposit' => 0,
        'max_uses'    => 1000,
        'is_active'   => 1,
    ]);
    echo "  + giftcode WELCOME50K\n";
}

echo "\nSeed hoan tat.\n";
echo "Dang nhap admin: admin / admin123\n";
