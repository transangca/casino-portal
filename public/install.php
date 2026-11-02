<?php
/**
 * =====================================================================
 *  CASINO PORTAL — TRÌNH CÀI ĐẶT QUA WEB (Web Installer)
 * =====================================================================
 *  Dùng cho cPanel / Shared Hosting:
 *    1. Upload toàn bộ source lên thư mục public_html (hoặc thư mục con).
 *    2. Tạo database MySQL + user trong cPanel → "MySQL Databases".
 *    3. Truy cập  https://tenmien.com/install.php
 *    4. Làm theo từng bước. Installer sẽ tự tạo bảng, tạo admin,
 *       nạp danh mục sảnh game 60 cấp VIP và ghi file .env.
 *
 *  YÊU CẦU: PHP 8.0+, ext: pdo_mysql, mbstring, json, openssl, curl, gd
 *  LƯU Ý   : Xoá file install.php sau khi cài xong (installer sẽ nhắc).
 * =====================================================================
 */

declare(strict_types=1);

session_start();

define('INSTALL_ROOT', dirname(__DIR__));
define('INSTALL_STEP', $_SESSION['install_step'] ?? 1);
define('INSTALL_LOG', INSTALL_ROOT . '/storage/logs/install.log');

/* ---------------------------------------------------------------------
 |  Tiện ích
 * -------------------------------------------------------------------- */

function e(?string $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function iwrite(string $file, string $content): bool
{
    $dir = dirname($file);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        return false;
    }
    return @file_put_contents($file, $content, LOCK_EX) !== false;
}

function ilog(string $msg): void
{
    if (!is_dir(dirname(INSTALL_LOG))) {
        @mkdir(dirname(INSTALL_LOG), 0775, true);
    }
    @file_put_contents(INSTALL_LOG, '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL, FILE_APPEND);
}

function ih(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $_SESSION['install_data'][$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

/**
 * Kiểm tra extension + thư mục ghi được.
 */
function requirements(): array
{
    $checks = [];

    $checks[] = [
        'label' => 'PHP >= 8.0',
        'ok'    => version_compare(PHP_VERSION, '8.0.0', '>='),
        'note'  => PHP_VERSION,
    ];

    foreach (['pdo_mysql' => 'Kết nối MySQL', 'mbstring' => 'Hỗ trợ tiếng Việt',
              'json' => 'Xử lý JSON', 'openssl' => 'Mã hoá / 2FA',
              'curl' => 'Gọi API nhà phát hành', 'gd' => 'Xử lý ảnh'] as $ext => $why) {
        $checks[] = [
            'label' => 'Extension ' . $ext,
            'ok'    => extension_loaded($ext),
            'note'  => $why,
        ];
    }

    foreach ([
        INSTALL_ROOT . '/app'        => 'Thư mục mã nguồn',
        INSTALL_ROOT . '/config'     => 'Thư mục cấu hình',
        INSTALL_ROOT . '/storage'    => 'Thư mục ghi log / upload',
        INSTALL_ROOT . '/public'     => 'Thư mục web',
    ] as $dir => $label) {
        $checks[] = [
            'label' => $label . ' ghi được',
            'ok'    => is_dir($dir) && is_writable($dir),
            'note'  => $dir,
        ];
    }

    $checks[] = [
        'label' => 'File .env tạo được',
        'ok'    => is_writable(INSTALL_ROOT) || iwrite(INSTALL_ROOT . '/.env', ''),
        'note'  => INSTALL_ROOT,
    ];

    return $checks;
}

function requirementsPassed(): bool
{
    foreach (requirements() as $c) {
        if (!$c['ok']) {
            return false;
        }
    }
    return true;
}

/* ---------------------------------------------------------------------
 |  Các bước
 * -------------------------------------------------------------------- */

$steps = [
    1 => 'Kiểm tra hệ thống',
    2 => 'Cấu hình database',
    3 => 'Tạo bảng dữ liệu',
    4 => 'Tài khoản quản trị',
    5 => 'Cấu hình website',
    6 => 'Hoàn tất',
];

$step = (int)($_POST['step'] ?? INSTALL_STEP);
$errors = [];
$ok = false;
$summary = [];

/* =============== BƯỚC 1: kiểm tra hệ thống =============== */
if ($step === 1) {
    if (requirementsPassed()) {
        $ok = true;
        $_SESSION['install_step'] = 2;
    } else {
        $errors[] = 'Một số yêu cầu chưa đạt. Vui lòng sửa rồi tải lại trang này.';
    }
}

/* =============== BƯỚC 2: cấu hình database =============== */
if ($step === 2) {
    $host    = ih('db_host', 'localhost');
    $port    = ih('db_port', '3306');
    $name    = ih('db_name');
    $user    = ih('db_user');
    $pass    = (string)($_POST['db_pass'] ?? '');
    $siteUrl = ih('site_url');

    if ($name === '' || $user === '') {
        $errors[] = 'Vui lòng nhập tên database và tên user MySQL.';
    } else {
        // Thử kết nối (không chọn database trước, để test tạo mới)
        try {
            $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 10,
            ]);

            // Tạo database nếu chưa có
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            ilog("database created/exists: {$name}");

            $_SESSION['install_data'] = [
                'db_host'   => $host,
                'db_port'   => $port,
                'db_name'   => $name,
                'db_user'   => $user,
                'db_pass'   => $pass,
                'site_url'  => $siteUrl !== '' ? rtrim($siteUrl, '/') : guessUrl(),
            ];
            $_SESSION['install_step'] = 3;
            $ok = true;
        } catch (Throwable $e) {
            $errors[] = 'Không kết nối được MySQL: ' . $e->getMessage();
        }
    }
}

/* =============== BƯỚC 3: tạo bảng =============== */
if ($step === 3) {
    $d = $_SESSION['install_data'] ?? [];
    try {
        $pdo = dbPdo($d);
        runSqlFiles($pdo);

        $_SESSION['install_step'] = 4;
        $ok = true;
        $summary[] = 'Đã tạo toàn bộ bảng dữ liệu.';
    } catch (Throwable $e) {
        $errors[] = 'Lỗi tạo bảng: ' . $e->getMessage();
        ilog('install schema error: ' . $e->getMessage());
    }
}

/* =============== BƯỚC 4: tài khoản admin =============== */
if ($step === 4) {
    $username = ih('admin_username');
    $email    = ih('admin_email');
    $password = (string)($_POST['admin_password'] ?? '');
    $pass2    = (string)($_POST['admin_password2'] ?? '');

    if ($username === '' || $email === '') {
        $errors[] = 'Vui lòng nhập tên đăng nhập và email cho admin.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Mật khẩu phải có ít nhất 8 ký tự.';
    } elseif ($password !== $pass2) {
        $errors[] = 'Xác nhận mật khẩu không khớp.';
    } else {
        try {
            $pdo = dbPdo($_SESSION['install_data'] ?? []);
            $st = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
            $st->execute([$username, $email]);
            if ($st->fetch()) {
                $errors[] = 'Tên đăng nhập hoặc email đã tồn tại.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $st = $pdo->prepare(
                    'INSERT INTO users (username, email, password_hash, full_name, role, status, balance, vip_level, created_at)
                     VALUES (?, ?, ?, ?, "admin", "active", 0, 60, NOW())'
                );
                $st->execute([$username, $email, $hash, 'Quản trị hệ thống']);

                $_SESSION['install_admin'] = $username;
                $_SESSION['install_step']   = 5;
                $ok = true;
                $summary[] = "Đã tạo tài khoản admin: {$username}";
            }
        } catch (Throwable $e) {
            $errors[] = 'Lỗi tạo admin: ' . $e->getMessage();
        }
    }
}

/* =============== BƯỚC 5: cấu hình website =============== */
if ($step === 5) {
    $d = $_SESSION['install_data'] ?? [];

    $siteName   = ih('site_name', 'Casino Portal');
    $hotline    = ih('support_hotline');
    $telegram   = ih('support_telegram');
    $currency   = ih('currency_symbol', 'đ');
    $referralPc = (float)str_replace(',', '.', ih('referral_deposit_percent', '0.5'));
    $minVip     = (int)ih('child_site_min_vip', '5');
    $maxRev     = (float)str_replace(',', '.', ih('child_site_max_rev_share', '0.30'));
    $theme      = ih('theme_style', 'dark');

    $appKey = 'base64:' . base64_encode(random_bytes(32));

    // Ghi file .env
    $env = "# Auto-generated by installer\n";
    $env .= "APP_NAME=" . $siteName . "\n";
    $env .= "APP_ENV=production\n";
    $env .= "APP_DEBUG=false\n";
    $env .= "APP_URL=" . ($d['site_url'] ?? guessUrl()) . "\n";
    $env .= "APP_KEY=" . $appKey . "\n";
    $env .= "DB_HOST=" . ($d['db_host'] ?? 'localhost') . "\n";
    $env .= "DB_PORT=" . ($d['db_port'] ?? '3306') . "\n";
    $env .= "DB_NAME=" . ($d['db_name'] ?? '') . "\n";
    $env .= "DB_USER=" . ($d['db_user'] ?? '') . "\n";
    $env .= "DB_PASS=" . ($d['db_pass'] ?? '') . "\n";

    if (!iwrite(INSTALL_ROOT . '/.env', $env)) {
        $errors[] = 'Không ghi được file .env. Hãy chmod 755 thư mục gốc hoặc tạo file .env thủ công.';
    } else {
        ilog('env written');
    }

    // Lưu settings vào DB
    try {
        $pdo = dbPdo($d);
        $settings = [
            'site_name'                  => $siteName,
            'site_tagline'               => 'Sảnh game uy tín - đồng bộ ví tức thì',
            'currency_symbol'            => $currency,
            'support_hotline'            => $hotline,
            'support_telegram'           => $telegram,
            'theme_style'                => $theme,
            'default_language'           => 'vi',
            'referral_deposit_percent'   => (string)$referralPc,
            'referral_bonus_fixed'       => '50000',
            'child_site_min_vip'         => (string)$minVip,
            'child_site_max_rev_share'   => (string)$maxRev,
            'smtp_host'                  => '',
            'smtp_port'                  => '587',
            'smtp_encryption'            => 'tls',
            'smtp_username'              => '',
            'smtp_password'              => '',
            'mail_from_email'            => '',
            'mail_from_name'             => $siteName,
            'install_at'                 => date('Y-m-d H:i:s'),
        ];

        $st = $pdo->prepare(
            'INSERT INTO settings (name, value, updated_at) VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW()'
        );
        foreach ($settings as $k => $v) {
            $st->execute([$k, (string)$v]);
        }

        // Seed danh mục game + sảnh + VIP 60 cấp
        seedAll($pdo);

        $_SESSION['install_step'] = 6;
        $ok = true;
        $summary[] = 'Đã ghi file .env và lưu cấu hình website.';
        $summary[] = 'Đã nạp danh mục game, sảnh game và 60 cấp VIP.';
    } catch (Throwable $e) {
        $errors[] = 'Lỗi cấu hình: ' . $e->getMessage();
        ilog('install config error: ' . $e->getMessage());
    }
}

/* ---------------------------------------------------------------------
 |  Hàm DB
 * -------------------------------------------------------------------- */

function guessUrl(): string
{
    $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    $scheme = $https ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir    = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return $scheme . '://' . $host . $dir;
}

function dbPdo(array $d): PDO
{
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $d['db_host'] ?? 'localhost',
        $d['db_port'] ?? '3306',
        $d['db_name'] ?? ''
    );
    return new PDO($dsn, $d['db_user'] ?? '', $d['db_pass'] ?? '', [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
    ]);
}

/**
 * Chạy các file SQL: schema.sql trước, rồi migrate_v2.sql.
 * Bỏ qua lỗi "đã tồn tại" để có thể chạy lại nhiều lần.
 */
function runSqlFiles(PDO $pdo): void
{
    $files = [
        INSTALL_ROOT . '/database/schema.sql',
        INSTALL_ROOT . '/database/migrate_v2.sql',
    ];

    foreach ($files as $file) {
        if (!is_file($file)) {
            continue;
        }
        $sql = (string)file_get_contents($file);
        // Bỏ dòng "USE `db`" vì ta đã kết nối sẵn
        $sql = preg_replace('/^\s*USE\s+`?[a-z0-9_]+`?\s*;/mi', '', $sql) ?? $sql;

        foreach (splitSql($sql) as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '' || str_starts_with($stmt, '--')) {
                continue;
            }

            // ALTER TABLE ... ADD COLUMN IF NOT EXISTS (cú pháp MariaDB)
            // → kiểm tra information_schema rồi ADD thật (tương thích MySQL 8).
            if (stripos($stmt, 'ALTER TABLE') === 0 && stripos($stmt, 'ADD COLUMN IF NOT EXISTS') !== false) {
                applyAlter($pdo, $stmt);
                continue;
            }

            try {
                $pdo->exec($stmt);
            } catch (Throwable $e) {
                $msg = $e->getMessage();
                // Bỏ qua lỗi cấu trúc đã tồn tại
                if (
                    str_contains($msg, 'Duplicate column name')
                    || str_contains($msg, 'Duplicate key name')
                    || str_contains($msg, 'already exists')
                    || str_contains($msg, 'Duplicate entry')
                ) {
                    continue;
                }
                throw $e;
            }
        }
        ilog('applied ' . basename($file));
    }
}

/**
 * Thực thi câu ALTER TABLE dạng "ADD COLUMN IF NOT EXISTS" theo cách tương thích MySQL 8.
 */
function applyAlter(PDO $pdo, string $stmt): void
{
    if (!preg_match('/ALTER\s+TABLE\s+`?([a-z0-9_]+)`?\s+(.*)/is', $stmt, $m)) {
        return;
    }
    $table = $m[1];
    $rest  = $m[2];

    // Tách thành từng đoạn "ADD COLUMN IF NOT EXISTS ..."
    $parts = preg_split('/,\s*(?=ADD\s+COLUMN)/i', $rest) ?: [];

    foreach ($parts as $part) {
        if (!preg_match('/ADD\s+COLUMN\s+IF\s+NOT\s+EXISTS\s+`?([a-z0-9_]+)`?\s*(.*)/is', $part, $cm)) {
            continue;
        }
        $column = $cm[1];
        $define = trim($cm[2]);

        $st = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $st->execute([$table, $column]);

        if ((int)$st->fetchColumn() > 0) {
            continue;
        }

        try {
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$define}");
        } catch (Throwable $e) {
            ilog("alter {$table}.{$column} failed: " . $e->getMessage());
        }
    }
}

/**
 * Tách chuỗi SQL thành từng câu lệnh (bỏ qua chuỗi và comment).
 */
function splitSql(string $sql): array
{
    $out = [];
    $buf = '';
    $inS = false;
    $inD = false;
    $len  = strlen($sql);

    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];
        $prev = $i > 0 ? $sql[$i - 1] : '';

        if ($ch === "'" && !$inD && $prev !== '\\') {
            $inS = !$inS;
        } elseif ($ch === '"' && !$inS && $prev !== '\\') {
            $inD = !$inD;
        }

        if ($ch === ';' && !$inS && !$inD) {
            $out[] = $buf;
            $buf = '';
            continue;
        }
        $buf .= $ch;
    }
    if (trim($buf) !== '') {
        $out[] = $buf;
    }

    return $out;
}

/* ---------------------------------------------------------------------
 |  Seed danh mục / sảnh / VIP
 * -------------------------------------------------------------------- */

function seedAll(PDO $pdo): void
{
    $registry = require INSTALL_ROOT . '/config/providers_registry.php';

    // --- Danh mục game ---
    $cats = [
        ['casino', 'Casino Live', 'Casino Live', 1],
        ['slot', 'Slot', 'Slots', 2],
        ['fishing', 'Bắn Cá', 'Fishing', 3],
        ['blockchain', 'Blockchain / Crash', 'Blockchain', 4],
        ['sports', 'Thể Thao', 'Sports', 5],
        ['card', 'Game Bài', 'Card Games', 6],
        ['lottery', 'Xổ Số', 'Lottery', 7],
    ];
    $st = $pdo->prepare(
        'INSERT INTO game_categories (code, name, name_en, sort_order, is_active)
         VALUES (?, ?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE name = VALUES(name), name_en = VALUES(name_en), sort_order = VALUES(sort_order)'
    );
    foreach ($cats as $c) {
        $st->execute($c);
    }

    // --- Sảnh game ---
    $st = $pdo->prepare(
        'INSERT INTO providers (code, name, driver, category, wallet_mode, lobby_url, base_url, config, status, sort_order, is_featured)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, "active", ?, ?)
         ON DUPLICATE KEY UPDATE name = VALUES(name), category = VALUES(category), wallet_mode = VALUES(wallet_mode),
                                 lobby_url = VALUES(lobby_url), base_url = VALUES(base_url), config = VALUES(config),
                                 sort_order = VALUES(sort_order), is_featured = VALUES(is_featured)'
    );
    foreach ($registry as $code => $cfg) {
        $config = json_encode([
            'game_host'      => $cfg['game_host'] ?? '',
            'game_url'       => $cfg['game_url'] ?? '',
            'api_base'       => $cfg['api_base'] ?? '',
            'icon_base'      => $cfg['icon_base'] ?? '',
            'operator_token' => '',
            'secret_key'     => '',
            'bet_type'       => 2,
            'source'         => $cfg['source'] ?? 'none',
        ], JSON_UNESCAPED_UNICODE);

        $st->execute([
            $code,
            $cfg['name'] ?? $code,
            $cfg['driver'] ?? 'generic-lobby',
            $cfg['category'] ?? 'slot',
            $cfg['wallet_mode'] ?? 'demo_only',
            $cfg['lobby_url'] ?? ($cfg['game_host'] ?? ''),
            $cfg['game_host'] ?? '',
            $config,
            $cfg['sort'] ?? 100,
            !empty($cfg['featured']) ? 1 : 0,
        ]);
    }

    // --- 60 cấp VIP ---
    $st = $pdo->prepare(
        'INSERT INTO vip_tiers (level, name, deposit_required, bonus_rate, cashback_rate, perks, icon, badge_3d, color, can_child_site, rev_share_max)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE name = VALUES(name), deposit_required = VALUES(deposit_required),
                                 bonus_rate = VALUES(bonus_rate), cashback_rate = VALUES(cashback_rate),
                                 perks = VALUES(perks), icon = VALUES(icon), badge_3d = VALUES(badge_3d),
                                 color = VALUES(color), can_child_site = VALUES(can_child_site),
                                 rev_share_max = VALUES(rev_share_max)'
    );

    for ($i = 1; $i <= 60; $i++) {
        $requirement = (int)round(1000000 * pow($i, 1.65));
        $cashback   = min(0.20, 0.005 * $i);
        $bonus      = min(0.60, 0.005 * $i + 0.005 * pow($i, 1.25));

        $perks = [
            'Hoàn tiền ' . number_format($cashback * 100, 1) . '%',
            'Thưởng nạp ' . number_format($bonus * 100, 1) . '%',
            'Rút tiền ưu tiên',
        ];
        if ($i >= 5) {
            $perks[] = 'Tạo website con (ăn chia tối đa ' . number_format(min(0.30, 0.02 + ($i - 5) * 0.006) * 100, 1) . '%)';
        }
        if ($i >= 20) {
            $perks[] = 'Hỗ trợ VIP ưu tiên 24/7';
        }
        if ($i >= 40) {
            $perks[] = 'Quà tặng đặc biệt theo mốc cấp';
        }

        $st->execute([
            $i,
            'VIP ' . $i,
            $requirement,
            round($bonus, 3),
            round($cashback, 3),
            json_encode($perks, JSON_UNESCAPED_UNICODE),
            'fas fa-crown',
            badgeSvg($i),
            vipColor($i),
            $i >= 5 ? 1 : 0,
            round(min(0.30, 0.02 + ($i - 5) * 0.006), 3),
        ]);
    }

    // --- Ngôn ngữ ---
    $st = $pdo->prepare(
        'INSERT INTO languages (code, name, flag, is_active, sort_order)
         VALUES (?, ?, ?, 1, ?)
         ON DUPLICATE KEY UPDATE name = VALUES(name), flag = VALUES(flag), sort_order = VALUES(sort_order)'
    );
    $langs = [
        ['vi', 'Tiếng Việt', '🇻🇳', 1],
        ['en', 'English', '🇬🇧', 2],
        ['zh', '中文', '🇨🇳', 3],
        ['ko', '한국어', '🇰🇷', 4],
        ['ja', '日本語', '🇯🇵', 5],
        ['th', 'ไทย', '🇹🇭', 6],
        ['id', 'Bahasa Indonesia', '🇮🇩', 7],
    ];
    foreach ($langs as $l) {
        $st->execute($l);
    }

    ilog('seed done: ' . count($registry) . ' providers, 60 vip tiers');
}

function vipColor(int $level): string
{
    $palette = [
        1 => '#b8c0cc', 5 => '#cd7f32', 10 => '#c0c0c0', 15 => '#b8860b',
        20 => '#a9a9a9', 25 => '#8b0000', 30 => '#4169e1', 35 => '#228b22',
        40 => '#8a2be2', 45 => '#ff8c00', 50 => '#ff1493', 55 => '#00ced1',
        60 => '#ffd700',
    ];
    $best = '#b8c0cc';
    foreach ($palette as $min => $color) {
        if ($level >= $min) {
            $best = $color;
        }
    }
    return $best;
}

/**
 * Sinh badge SVG 3D cho cấp VIP.
 */
function badgeSvg(int $level): string
{
    $color  = vipColor($level);
    $num    = (string)$level;
    $shadow = '#00000055';

    return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 130" width="120" height="130">
  <defs>
    <linearGradient id="g{$level}" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0%" stop-color="#ffffff" stop-opacity=".85"/>
      <stop offset="35%" stop-color="{$color}" stop-opacity=".95"/>
      <stop offset="100%" stop-color="{$color}"/>
    </linearGradient>
    <linearGradient id="s{$level}" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0%" stop-color="#{$color}" stop-opacity=".55"/>
      <stop offset="100%" stop-color="#000000" stop-opacity=".5"/>
    </linearGradient>
  </defs>
  <path d="M60 4 L112 26 V72 C112 100 90 120 60 128 C30 120 8 100 8 72 V26 Z"
        fill="url(#s{$level})" stroke="{$shadow}" stroke-width="2"/>
  <path d="M60 10 L106 29 V71 C106 95 87 113 60 121 C33 113 14 95 14 71 V29 Z"
        fill="url(#g{$level})" stroke="#ffffff" stroke-opacity=".55" stroke-width="2"/>
  <path d="M60 20 L97 35 V70 C97 90 81 105 60 112 C39 105 23 90 23 70 V35 Z"
        fill="none" stroke="#ffffff" stroke-opacity=".35" stroke-width="1.5"/>
  <ellipse cx="44" cy="34" rx="22" ry="12" fill="#ffffff" opacity=".25"/>
  <text x="60" y="76" text-anchor="middle" font-family="Inter,Arial,sans-serif"
        font-size="34" font-weight="800" fill="#ffffff" stroke="#00000055" stroke-width="1"
        paint-order="stroke">{$num}</text>
  <text x="60" y="98" text-anchor="middle" font-family="Inter,Arial,sans-serif"
        font-size="12" font-weight="700" fill="#ffffff" opacity=".9">VIP</text>
</svg>
SVG;
}

/* =====================================================================
 |  GIAO DIỆN
 * ===================================================================== */
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cài đặt Casino Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: radial-gradient(900px 500px at 10% -10%, rgba(245,197,66,.10), transparent 60%),
                        radial-gradient(800px 450px at 95% 0%, rgba(245,197,66,.06), transparent 62%),
                        #07060b;
            color: #f8f5ef;
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
        }
        .inst-wrap { max-width: 880px; margin: 0 auto; padding: 40px 16px 80px; }
        .inst-card {
            background: #14111f; border: 1px solid rgba(245,197,66,.18);
            border-radius: 1.1rem; padding: 2rem;
        }
        .inst-card::before {
            content: ''; display: block; height: 2px; margin: -2rem -2rem 1.6rem;
            background: linear-gradient(90deg, transparent, #f5c542, transparent);
        }
        .inst-title { font-weight: 800; font-size: 1.6rem; }
        .inst-steps { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: 1.8rem; }
        .inst-step {
            display: flex; align-items: center; gap: .4rem;
            padding: .4rem .85rem; border-radius: 999px; font-size: .8rem; font-weight: 600;
            background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.07);
            color: #9a92a8;
        }
        .inst-step.active { background: #f5c542; color: #14100a; border-color: #f5c542; }
        .inst-step.done { color: #6ee7b7; border-color: rgba(52,211,153,.35); }
        .inst-step .n {
            width: 20px; height: 20px; border-radius: 50%; display: inline-flex;
            align-items: center; justify-content: center; font-size: .68rem;
            background: rgba(255,255,255,.1);
        }
        .inst-step.active .n { background: rgba(0,0,0,.25); }
        .form-control, .form-select {
            background: rgba(255,255,255,.04); border-color: rgba(255,255,255,.08); color: #f8f5ef;
        }
        .form-control:focus, .form-select:focus {
            background: rgba(255,255,255,.06); border-color: #f5c542; color: #f8f5ef;
            box-shadow: 0 0 0 .2rem rgba(245,197,66,.14);
        }
        .form-label { font-size: .82rem; font-weight: 600; color: #d8d2e4; }
        .form-hint { font-size: .76rem; color: #9a92a8; }
        .btn-gold { background: linear-gradient(135deg,#f5c542,#ff9d3d); border: none; color: #14100a; font-weight: 700; }
        .btn-gold:hover { color: #14100a; filter: brightness(1.06); }
        .req-item {
            display: flex; align-items: center; gap: .6rem; padding: .55rem .7rem;
            border-bottom: 1px solid rgba(255,255,255,.05); font-size: .87rem;
        }
        .req-item .icon { width: 22px; text-align: center; }
        .req-item.ok .icon { color: #34d399; }
        .req-item.no .icon { color: #f87171; }
        .req-item .note { margin-left: auto; font-size: .76rem; color: #9a92a8; }
        .alert { border-radius: .8rem; font-size: .9rem; }
        code { color: #9fe6b8; background: rgba(255,255,255,.05); padding: 1px 6px; border-radius: 4px; }
        .done-box { text-align: center; padding: 1rem 0; }
        .done-box .big { font-size: 3.4rem; color: #34d399; }
    </style>
</head>
<body>
<div class="inst-wrap">

    <div class="d-flex align-items-center gap-2 mb-4">
        <i class="fas fa-shield-halved fs-3" style="color:#f5c542"></i>
        <div>
            <div class="inst-title">Cài đặt Casino Portal</div>
            <div class="form-hint">Trình cài đặt nhanh cho cPanel / Shared Hosting</div>
        </div>
    </div>

    <div class="inst-steps">
        <?php foreach ($steps as $n => $label): ?>
            <div class="inst-step <?= $n === $step ? 'active' : ($n < $step ? 'done' : '') ?>">
                <span class="n"><?= $n < $step ? '<i class="fas fa-check"></i>' : $n ?></span>
                <?= e($label) ?>
            </div>
        <?php endforeach; ?>
    </div>

    <?php foreach ($errors as $err): ?>
        <div class="alert alert-danger"><i class="fas fa-triangle-exclamation me-2"></i><?= e($err) ?></div>
    <?php endforeach; ?>

    <?php foreach ($summary as $s): ?>
        <div class="alert alert-success"><i class="fas fa-circle-check me-2"></i><?= e($s) ?></div>
    <?php endforeach; ?>

    <div class="inst-card">

        <?php /* ---------- BƯỚC 1 ---------- */ ?>
        <?php if ($step === 1): ?>
            <h5 class="mb-3"><i class="fas fa-list-check text-warning me-2"></i>Kiểm tra yêu cầu hệ thống</h5>
            <div class="mb-3">
                <?php foreach (requirements() as $c): ?>
                    <div class="req-item <?= $c['ok'] ? 'ok' : 'no' ?>">
                        <span class="icon"><i class="fas fa-<?= $c['ok'] ? 'circle-check' : 'circle-xmark' ?>"></i></span>
                        <span><?= e($c['label']) ?></span>
                        <span class="note"><?= e((string)$c['note']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <form method="POST">
                <input type="hidden" name="step" value="2">
                <button class="btn btn-gold" <?= requirementsPassed() ? '' : 'disabled' ?>>
                    Tiếp tục <i class="fas fa-arrow-right ms-2"></i>
                </button>
            </form>

        <?php /* ---------- BƯỚC 2 ---------- */ ?>
        <?php elseif ($step === 2): ?>
            <h5 class="mb-3"><i class="fas fa-database text-warning me-2"></i>Cấu hình MySQL</h5>
            <p class="form-hint mb-3">Thông tin lấy từ cPanel → MySQL Databases. Installer sẽ tự tạo database nếu chưa có.</p>
            <form method="POST">
                <input type="hidden" name="step" value="2">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Host</label>
                        <input type="text" class="form-control" name="db_host" value="<?= e(ih('db_host', 'localhost')) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Port</label>
                        <input type="text" class="form-control" name="db_port" value="<?= e(ih('db_port', '3306')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Tên database *</label>
                        <input type="text" class="form-control" name="db_name" value="<?= e(ih('db_name')) ?>" placeholder="ten_database" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">User database *</label>
                        <input type="text" class="form-control" name="db_user" value="<?= e(ih('db_user')) ?>" placeholder="ten_user" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Mật khẩu</label>
                        <input type="password" class="form-control" name="db_pass">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">URL website</label>
                        <input type="text" class="form-control" name="site_url" value="<?= e(ih('site_url', guessUrl())) ?>">
                        <div class="form-hint mt-1">VD: https://tenmien.com</div>
                    </div>
                </div>
                <button class="btn btn-gold mt-4"><i class="fas fa-plug me-2"></i>Kết nối &amp; tiếp tục</button>
            </form>

        <?php /* ---------- BƯỚC 3 ---------- */ ?>
        <?php elseif ($step === 3): ?>
            <h5 class="mb-3"><i class="fas fa-table text-warning me-2"></i>Tạo bảng dữ liệu</h5>
            <p class="form-hint">Đang chờ bấm nút để chạy schema…</p>
            <form method="POST">
                <input type="hidden" name="step" value="3">
                <button class="btn btn-gold"><i class="fas fa-database me-2"></i>Tạo bảng &amp; tiếp tục</button>
            </form>

        <?php /* ---------- BƯỚC 4 ---------- */ ?>
        <?php elseif ($step === 4): ?>
            <h5 class="mb-3"><i class="fas fa-user-shield text-warning me-2"></i>Tạo tài khoản quản trị</h5>
            <form method="POST">
                <input type="hidden" name="step" value="4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Tên đăng nhập *</label>
                        <input type="text" class="form-control" name="admin_username" value="<?= e(ih('admin_username', 'admin')) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email *</label>
                        <input type="email" class="form-control" name="admin_email" value="<?= e(ih('admin_email')) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Mật khẩu *</label>
                        <input type="password" class="form-control" name="admin_password" required minlength="8">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Xác nhận mật khẩu *</label>
                        <input type="password" class="form-control" name="admin_password2" required minlength="8">
                    </div>
                </div>
                <button class="btn btn-gold mt-4"><i class="fas fa-user-check me-2"></i>Tạo tài khoản &amp; tiếp tục</button>
            </form>

        <?php /* ---------- BƯỚC 5 ---------- */ ?>
        <?php elseif ($step === 5): ?>
            <h5 class="mb-3"><i class="fas fa-sliders text-warning me-2"></i>Cấu hình website</h5>
            <form method="POST">
                <input type="hidden" name="step" value="5">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Tên website</label>
                        <input type="text" class="form-control" name="site_name" value="<?= e(ih('site_name', 'Casino Portal')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Ký hiệu tiền tệ</label>
                        <input type="text" class="form-control" name="currency_symbol" value="<?= e(ih('currency_symbol', 'đ')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Hotline hỗ trợ</label>
                        <input type="text" class="form-control" name="support_hotline" value="<?= e(ih('support_hotline')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Telegram hỗ trợ</label>
                        <input type="text" class="form-control" name="support_telegram" value="<?= e(ih('support_telegram')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">% hoa hồng giới thiệu</label>
                        <input type="text" class="form-control" name="referral_deposit_percent" value="<?= e(ih('referral_deposit_percent', '0.5')) ?>">
                        <div class="form-hint mt-1">0.5 = 0,5%</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">VIP tối thiểu tạo web con</label>
                        <input type="number" class="form-control" name="child_site_min_vip" value="<?= e(ih('child_site_min_vip', '5')) ?>" min="1" max="60">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Trần % ăn chia</label>
                        <input type="text" class="form-control" name="child_site_max_rev_share" value="<?= e(ih('child_site_max_rev_share', '0.30')) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Giao diện mặc định</label>
                        <select class="form-select" name="theme_style">
                            <option value="dark">Tối</option>
                            <option value="light">Sáng</option>
                            <option value="system">Theo hệ thống</option>
                        </select>
                    </div>
                </div>
                <button class="btn btn-gold mt-4"><i class="fas fa-check-double me-2"></i>Hoàn tất cài đặt</button>
            </form>

        <?php /* ---------- BƯỚC 6 ---------- */ ?>
        <?php else: ?>
            <div class="done-box">
                <i class="fas fa-circle-check big d-block mb-3"></i>
                <h4 class="mb-2">Cài đặt thành công!</h4>
                <p class="form-hint mb-4">
                    Tài khoản quản trị: <strong><?= e($_SESSION['install_admin'] ?? 'admin') ?></strong><br>
                    Đăng nhập tại <code>/admin</code>
                </p>

                <div class="alert alert-warning text-start">
                    <i class="fas fa-shield-halved me-2"></i>
                    <strong>Bắt buộc:</strong> xoá file <code>install.php</code> khỏi server để tránh bị truy cập lại.
                </div>

                <div class="d-flex gap-2 justify-content-center flex-wrap">
                    <a class="btn btn-gold" href="<?= e($_SESSION['install_data']['site_url'] ?? guessUrl()) ?>">
                        <i class="fas fa-house me-2"></i>Vào trang chủ
                    </a>
                    <a class="btn btn-soft" href="<?= e($_SESSION['install_data']['site_url'] ?? guessUrl()) ?>/admin">
                        <i class="fas fa-user-shield me-2"></i>Trang quản trị
                    </a>
                    <a class="btn btn-soft" href="<?= e($_SESSION['install_data']['site_url'] ?? guessUrl()) ?>/lobbies">
                        <i class="fas fa-layer-group me-2"></i>Danh sách sảnh
                    </a>
                </div>

                <div class="alert alert-info text-start mt-4 mb-0">
                    <div class="fw-bold mb-2"><i class="fas fa-list me-2"></i>Việc cần làm tiếp theo</div>
                    <ol class="mb-0 ps-3" style="font-size:.87rem">
                        <li>Admin → <strong>Banner quảng cáo</strong>: chèn banner ảnh/video của bạn.</li>
                        <li>Admin → <strong>Ngân hàng (VietQR)</strong>: thêm tài khoản nhận tiền.</li>
                        <li>Admin → <strong>Cấu hình</strong>: điền SMTP để gửi email thông báo.</li>
                        <li>Admin → <strong>Tin tức / Sự kiện</strong>: đăng banner sự kiện mới nhất.</li>
                        <li>Trong bảng <code>providers</code>, điền <code>operator_token</code> + <code>secret_key</code> cho sảnh muốn đồng bộ ví.</li>
                    </ol>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <p class="text-center mt-4 form-hint">
        Casino Portal Installer · Xoá file <code>install.php</code> sau khi cài xong
    </p>
</div>
</body>
</html>
