<?php
namespace App\Services;

use App\Core\Database;

/**
 * DỊCH VỤ TẠO WEBSITE TỰ ĐỘNG A-Z
 * ------------------------------------------------------------------
 * Khách nhập: tên, domain, chủ đề, từ khoá → hệ thống sinh ra 1 website
 * hoàn chỉnh gồm:
 *   - Trang chủ (hero, dịch vụ, banner, CTA, footer)
 *   - Trang giới thiệu, Liên hệ, Dịch vụ, Bài viết, Chính sách
 *   - CSS responsive (mobile-first), JS menu + slider + form
 *   - SEO: meta, sitemap.xml, robots.txt, structured data
 *   - favicon SVG
 * Tất cả gói lại thành 1 file ZIP tải về, giải nén là chạy được ngay.
 */
class SiteAutoBuilderService
{
    public const STORAGE = 'site-auto';

    /** Chủ đề có sẵn */
    public const CATEGORIES = [
        'casino'   => 'Casino / Sảnh game',
        'shop'     => 'Thương mại điện tử',
        'blog'     => 'Blog / Tin tức',
        'landing'  => 'Landing page',
        'service'  => 'Dịch vụ',
        'portfolio'=> 'Portfolio',
        'agency'   => 'Agency / Marketing',
        'vps'      => 'Hosting / VPS',
        'fintech'  => 'Tài chính / Ngân hàng',
        'edu'      => 'Giáo dục',
    ];

    /** Bảng màu theo chủ đề */
    private const THEMES = [
        'dark'    => ['bg' => '#0b0913', 'card' => '#14111f', 'line' => 'rgba(255,255,255,.09)', 'text' => '#f4f2ee', 'muted' => '#9a92a8', 'accent' => '#f5c542', 'accent2' => '#7c5cff'],
        'gold'    => ['bg' => '#100c05', 'card' => '#1a1409', 'line' => 'rgba(245,197,66,.22)', 'text' => '#fdf6e7', 'muted' => '#b8a889', 'accent' => '#f5c542', 'accent2' => '#e08a1e'],
        'blue'    => ['bg' => '#050b16', 'card' => '#0d1728', 'line' => 'rgba(120,170,255,.18)', 'text' => '#eef4ff', 'muted' => '#93a4c4', 'accent' => '#4d8dff', 'accent2' => '#22d3ee'],
        'red'     => ['bg' => '#0f0608', 'card' => '#1a0c10', 'line' => 'rgba(255,120,140,.20)', 'text' => '#fdf0f2', 'muted' => '#bd949c', 'accent' => '#ff4d6d', 'accent2' => '#ffb703'],
        'green'   => ['bg' => '#04120c', 'card' => '#0a1d15', 'line' => 'rgba(80,220,160,.20)', 'text' => '#effdf6', 'muted' => '#92b8a8', 'accent' => '#22c55e', 'accent2' => '#84cc16'],
        'light'   => ['bg' => '#f7f8fc', 'card' => '#ffffff', 'line' => 'rgba(20,24,40,.12)', 'text' => '#15182a', 'muted' => '#5f6780', 'accent' => '#c9922a', 'accent2' => '#4d8dff'],
    ];

    /**
     * Tạo dự án.
     */
    public static function create(int $userId, array $data): array
    {
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') {
            return ['ok' => false, 'message' => 'Vui lòng nhập tên website.', 'id' => 0];
        }

        $category = (string)($data['category'] ?? 'casino');
        if (!isset(self::CATEGORIES[$category])) {
            $category = 'casino';
        }

        $theme = (string)($data['theme'] ?? 'dark');
        if (!isset(self::THEMES[$theme])) {
            $theme = 'dark';
        }

        $domain = trim((string)($data['domain'] ?? ''));
        $domain = preg_replace('#^https?://#i', '', $domain) ?? $domain;
        $domain = trim($domain, '/');

        $id = Database::insert('site_auto_builds', [
            'user_id'     => $userId,
            'name'        => mb_substr($name, 0, 150),
            'domain'      => mb_substr($domain, 0, 150) ?: null,
            'category'    => $category,
            'template'    => $category,
            'theme'       => $theme,
            'keywords'    => mb_substr(trim((string)($data['keywords'] ?? '')), 0, 255) ?: null,
            'description' => mb_substr(trim((string)($data['description'] ?? '')), 0, 255) ?: null,
            'status'      => 'pending',
            'price'       => 0,
        ]);

        return ['ok' => true, 'message' => 'Đã tạo dự án. Bắt đầu sinh website...', 'id' => $id];
    }

    /**
     * Lấy dự án.
     */
    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM site_auto_builds WHERE id = ?', [$id]);
    }

    /**
     * Danh sách dự án của user.
     */
    public static function myProjects(int $userId, int $limit = 30): array
    {
        return Database::fetchAll(
            'SELECT * FROM site_auto_builds WHERE user_id = ? ORDER BY id DESC LIMIT ' . (int)$limit,
            [$userId]
        );
    }

    /**
     * Tất cả dự án (admin).
     */
    public static function allProjects(int $limit = 100): array
    {
        return Database::fetchAll(
            'SELECT sab.*, u.username FROM site_auto_builds sab
             LEFT JOIN users u ON u.id = sab.user_id
             ORDER BY sab.id DESC LIMIT ' . (int)$limit
        );
    }

    // =================================================================
    //  Sinh website
    // =================================================================

    /**
     * Chạy sinh website.
     */
    public static function run(int $id): array
    {
        $project = self::find($id);
        if (!$project) {
            return ['ok' => false, 'message' => 'Không tìm thấy dự án.'];
        }
        if ($project['status'] === 'done') {
            return ['ok' => false, 'message' => 'Dự án đã hoàn thành.'];
        }

        Database::update('site_auto_builds', ['status' => 'building'], 'id = :id', ['id' => $id]);

        $name   = (string)$project['name'];
        $domain = (string)($project['domain'] ?? '');
        $theme  = self::THEMES[(string)$project['theme']] ?? self::THEMES['dark'];
        $cat    = (string)$project['category'];

        $folder = 'site-' . slugify($name) . '-' . date('YmdHis');
        $dir    = BASE_PATH . '/storage/' . self::STORAGE . '/job-' . $id . '/' . $folder;
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return self::fail($id, 'Không tạo được thư mục.');
        }

        // ---- Nội dung theo chủ đề ----
        $content = self::contentFor($cat, $name, $domain, (string)($project['description'] ?? ''), (string)($project['keywords'] ?? ''));

        // ---- CSS ----
        @file_put_contents($dir . '/assets/css/style.css', self::buildCss($theme, $cat));

        // ---- JS ----
        @file_put_contents($dir . '/assets/js/main.js', self::buildJs());

        // ---- Favicon ----
        @file_put_contents($dir . '/assets/img/favicon.svg', self::buildFavicon($theme['accent']));

        // ---- Các trang ----
        $pages = self::buildPages($content, $theme, $name, $domain);
        $pageList = [];
        foreach ($pages as $file => $html) {
            $full = $dir . '/' . $file;
            if (!is_dir(dirname($full))) {
                @mkdir(dirname($full), 0775, true);
            }
            @file_put_contents($full, $html);
            $pageList[] = ['path' => $file, 'size' => strlen($html)];
        }

        // ---- SEO ----
        @file_put_contents($dir . '/sitemap.xml', self::buildSitemap($domain, array_keys($pages)));
        @file_put_contents($dir . '/robots.txt', self::buildRobots($domain));

        // ---- Manifest ----
        $manifest = [
            'name'       => $name,
            'domain'     => $domain,
            'category'   => $cat,
            'theme'      => $project['theme'],
            'generated_at' => date('Y-m-d H:i:s'),
            'folder'     => $folder,
            'pages'      => $pageList,
            'counts'     => ['pages' => count($pageList)],
        ];
        @file_put_contents($dir . '/manifest.json', json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        // ---- Đóng gói ----
        $zipPath = $dir . '.zip';
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return self::fail($id, 'Không tạo được file ZIP.');
        }
        UiScraperService::addDirToZip($zip, $dir, 'site-' . slugify($name));
        $zip->close();

        if (!is_file($zipPath)) {
            return self::fail($id, 'Không tạo được file ZIP.');
        }

        UiScraperService::rmDir($dir);

        Database::update('site_auto_builds', [
            'status'      => 'done',
            'pages'       => json_encode($pageList, JSON_UNESCAPED_UNICODE),
            'zip_path'    => $zipPath,
            'zip_size'    => (int)filesize($zipPath),
        ], 'id = :id', ['id' => $id]);

        return ['ok' => true, 'message' => 'Đã sinh website với ' . count($pageList) . ' trang.'];
    }

    private static function fail(int $id, string $message): array
    {
        Database::update('site_auto_builds', [
            'status'    => 'failed',
            'error_msg' => mb_substr($message, 0, 255),
        ], 'id = :id', ['id' => $id]);
        return ['ok' => false, 'message' => $message];
    }

    /**
     * Đường dẫn ZIP.
     */
    public static function zipOf(int $id): ?string
    {
        $project = self::find($id);
        if (!$project || $project['status'] !== 'done') {
            return null;
        }
        $path = (string)($project['zip_path'] ?? '');
        return ($path !== '' && is_file($path)) ? $path : null;
    }

    /**
     * Xoá dự án.
     */
    public static function delete(int $id, int $userId): void
    {
        $project = self::find($id);
        if (!$project || (int)$project['user_id'] !== $userId) {
            return;
        }
        $zip = self::zipOf($id);
        if ($zip !== null) {
            @unlink($zip);
        }
        Database::delete('site_auto_builds', 'id = ? AND user_id = ?', [$id, $userId]);
    }

    // =================================================================
    //  Nội dung
    // =================================================================

    /**
     * Sinh nội dung theo chủ đề.
     */
    private static function contentFor(string $cat, string $name, string $domain, string $desc, string $keywords): array
    {
        $services = match ($cat) {
            'shop' => [
                ['Sản phẩm chính hãng', 'Cam kết chất lượng, giao hàng toàn quốc 24-48h.'],
                ['Giá tốt nhất', 'Cập nhật giá theo thị trường mỗi ngày.'],
                ['Thanh toán tiện lợi', 'Ví điện tử, thẻ tín dụng, chuyển khoản ngân hàng.'],
                ['Đổi trả dễ dàng', 'Đổi sản phẩm trong 7 ngày nếu còn nguyên seal.'],
            ],
            'blog' => [
                ['Bài viết mới nhất', 'Cập nhật hàng ngày với nội dung hữu ích và thực tế.'],
                ['Hướng dẫn chi tiết', 'Từng bước rõ ràng cho người mới bắt đầu.'],
                ['Phân tích & review', 'Đánh giá trung thực từ góc nhìn người dùng.'],
                ['Kinh nghiệm thực tế', 'Bài học xương máu từ những người đi trước.'],
            ],
            'vps' => [
                ['VPS chất lượng cao', 'CPU thật, NVMe SSD, đặt tại Data Center tier 1.'],
                ['Triển khai 1 phút', 'Hệ điều hành Linux/Windows cài sẵn, trải nghiệm ngay.'],
                ['Băng thông không giới hạn', 'Traffic 1Gbps, không giới hạn, hỗ trợ IPv6.'],
                ['Hỗ trợ 24/7', 'Kỹ thuật trực trong giờ vàng, phản hồi dưới 15 phút.'],
            ],
            'fintech' => [
                ['Tra cứu tức thì', 'Kết quả chính xác từ hệ thống ngân hàng trực tiếp.'],
                ['Bảo mật tuyệt đối', 'Mã hoá AES-256, không lưu dữ liệu nhạy cảm lâu dài.'],
                ['Đồng bộ ví', 'Cập nhật số dư hai chiều tức thì.'],
                ['Hỗ trợ 24/7', 'Đội ngũ hỗ trợ luôn sẵn sàng.'],
            ],
            'landing' => [
                ['Nhanh gọn', 'Tải trang trong mili giây, tối ưu chuyển đổi.'],
                ['Chuyển đổi cao', 'Thiết kế tối ưu theo phễu marketing.'],
                ['Responsive hoàn hảo', 'Hiển thị đẹp trên mọi thiết bị.'],
                ['Dễ chỉnh sửa', 'Quản lý nội dung không cần biết lập trình.'],
            ],
            default => [
                ['Sảnh game uy tín', 'Kho game chính hãnh từ các nhà phát hành lớn.'],
                ['Ví đồng bộ hai chiều', 'Nạp rút tức thì, không chờ đối soát thủ công.'],
                ['Hỗ trợ 24/7', 'Đội ngũ hỗ trợ trực tuyến mọi khung giờ.'],
                ['Bảo mật cao', 'Hệ thống tài chính được giám sát liên tục.'],
            ],
        };

        return [
            'name'        => $name,
            'domain'      => $domain,
            'description' => $desc !== '' ? $desc : 'Hệ thống ' . mb_strtolower($name) . ' chuyên nghiệp, uy tín, giao diện đẹp và dịch vụ nhanh chóng.',
            'keywords'    => $keywords !== '' ? $keywords : $name . ', dịch vụ, chuyên nghiệp, uy tín',
            'services'    => $services,
            'cat'         => $cat,
        ];
    }

    /**
     * Sinh các trang HTML.
     */
    private static function buildPages(array $c, array $theme, string $name, string $domain): array
    {
        $nav = [
            'Trang chủ'  => 'index.html',
            'Giới thiệu' => 'gioi-thieu.html',
            'Dịch vụ'   => 'dich-vu.html',
            'Bài viết'  => 'bai-viet.html',
            'Liên hệ'  => 'lien-he.html',
            'Chính sách'=> 'chinh-sach.html',
        ];

        $pages = [];

        // --- Trang chủ ---
        $pages['index.html'] = self::page('Trang chủ', $name, $c, $theme, $nav, self::homeBody($c, $theme));

        // --- Giới thiệu ---
        $pages['gioi-thieu.html'] = self::page('Giới thiệu', $name, $c, $theme, $nav, self::aboutBody($c, $theme));

        // --- Dịch vụ ---
        $pages['dich-vu.html'] = self::page('Dịch vụ', $name, $c, $theme, $nav, self::servicesBody($c, $theme));

        // --- Bài viết ---
        $pages['bai-viet.html'] = self::page('Bài viết', $name, $c, $theme, $nav, self::blogBody($c, $theme));

        // --- Liên hệ ---
        $pages['lien-he.html'] = self::page('Liên hệ', $name, $c, $theme, $nav, self::contactBody($c, $theme));

        // --- Chính sách ---
        $pages['chinh-sach.html'] = self::page('Chính sách', $name, $c, $theme, $nav, self::policyBody($c, $theme));

        return $pages;
    }

    /**
     * Khung trang HTML.
     */
    private static function page(string $title, string $name, array $c, array $theme, array $nav, string $body): string
    {
        $navHtml = '';
        foreach ($nav as $label => $href) {
            $navHtml .= '<a href="' . e($href) . '">' . e($label) . '</a>';
        }

        $fullTitle = $title . ' | ' . $name;
        $desc      = e($c['description']);
        $kw        = e($c['keywords']);

        return <<<HTML
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$fullTitle}</title>
<meta name="description" content="{$desc}">
<meta name="keywords" content="{$kw}">
<meta name="author" content="{$name}">
<meta property="og:title" content="{$fullTitle}">
<meta property="og:description" content="{$desc}">
<meta property="og:type" content="website">
<meta name="theme-color" content="{$theme['accent']}">
<link rel="icon" href="assets/img/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="site-header">
  <div class="container">
    <a class="logo" href="index.html"><span class="logo-mark"></span>{$name}</a>
    <button class="nav-toggle" id="navToggle" aria-label="Menu"><span></span><span></span><span></span></button>
    <nav class="site-nav" id="siteNav">{$navHtml}</nav>
  </div>
</header>

{$body}

<footer class="site-footer">
  <div class="container footer-grid">
    <div>
      <h4>{$name}</h4>
      <p class="muted">{$desc}</p>
    </div>
    <div>
      <h5>Liên kết</h5>
      <ul>{$navHtml}</ul>
    </div>
    <div>
      <h5>Liên hệ</h5>
      <ul>
        <li>Hotline: 1900 0000</li>
        <li>Email: contact@example.com</li>
        <li>Telegram: @yourchannel</li>
      </ul>
    </div>
  </div>
  <div class="container footer-bottom">
    <span>&copy; {$name}. Đồng bộ bản quyền.</span>
  </div>
</footer>

<script src="assets/js/main.js"></script>
</body>
</html>
HTML;
    }

    private static function homeBody(array $c, array $t): string
    {
        $cards = '';
        foreach ($c['services'] as $i => $s) {
            $cards .= <<<HTML
<div class="card">
  <div class="card-ico">0{$i}</div>
  <h3>{$s[0]}</h3>
  <p class="muted">{$s[1]}</p>
</div>
HTML;
        }

        $slides = '';
        foreach (['Chào mừng đến với ' . $c['name'], 'Sản phẩm chất lượng cao', 'Hỗ trợ nhanh chóng 24/7'] as $i => $s) {
            $slides .= '<div class="slide' . ($i === 0 ? ' active' : '') . '"><h2>' . e($s) . '</h2>'
                . '<p class="muted">' . e($c['description']) . '</p>'
                . '<a class="btn btn-primary" href="dich-vu.html">Khám phá dịch vụ</a></div>';
        }

        return <<<HTML
<section class="hero">
  <div class="container hero-grid">
    <div>
      <span class="eyebrow">{$c['name']}</span>
      <h1 class="hero-title">Giải pháp <span class="grad">chuyên nghiệp</span><br>cho mọi nhu cầu</h1>
      <p class="lead muted">{$c['description']}</p>
      <div class="hero-actions">
        <a class="btn btn-primary" href="dich-vu.html">Xem dịch vụ</a>
        <a class="btn btn-ghost" href="lien-he.html">Liên hệ ngay</a>
      </div>
      <div class="stats">
        <div><b>120+</b><span>Khách hàng</span></div>
        <div><b>99%</b><span>Hài lòng</span></div>
        <div><b>24/7</b><span>Hỗ trợ</span></div>
      </div>
    </div>
    <div class="hero-slider" id="heroSlider">{$slides}</div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="section-head">
      <h2>Dịch vụ nổi bật</h2>
      <p class="muted">Những giải pháp chúng tôi mang đến cho bạn</p>
    </div>
    <div class="grid">{$cards}</div>
  </div>
</section>

<section class="cta">
  <div class="container">
    <h2>Bắt đầu ngay hôm nay</h2>
    <p class="muted">Đăng ký để nhận tư vấn miễn phí từ đội ngũ của chúng tôi.</p>
    <a class="btn btn-primary btn-lg" href="lien-he.html">Liên hệ ngay</a>
  </div>
</section>
HTML;
    }

    private static function aboutBody(array $c, array $t): string
    {
        return <<<HTML
<section class="section">
  <div class="container narrow">
    <div class="section-head">
      <h1>Giới thiệu về {$c['name']}</h1>
      <p class="muted">{$c['description']}</p>
    </div>
    <div class="prose">
      <h2>Chúng tôi là ai?</h2>
      <p class="muted">{$c['name']} được xây dựng trên nền tảng công nghệ hiện đại, tập trung vào trải nghiệm người dùng và chất lượng dịch vụ. Mọi tính năng đều được kiểm thử nghiêm ngặt trước khi đưa vào vận hành.</p>
      <h2>Sứ mệnh</h2>
      <p class="muted">Mang đến giải pháp đáng tin cậy, minh bạch và dễ sử dụng cho mọi khách hàng — từ cá nhân đến doanh nghiệp.</p>
      <h2>Cam kết</h2>
      <ul class="muted">
        <li>Minh bạch về giá bán và phí dịch vụ.</li>
        <li>Bảo mật dữ liệu theo tiêu chuẩn quốc tế.</li>
        <li>Hỗ trợ khách hàng trong giờ vàng, phản hồi nhanh chóng.</li>
      </ul>
    </div>
  </div>
</section>
HTML;
    }

    private static function servicesBody(array $c, array $t): string
    {
        $cards = '';
        foreach ($c['services'] as $i => $s) {
            $cards .= '<div class="card"><div class="card-ico">0' . ($i + 1) . '</div><h3>' . e($s[0])
                . '</h3><p class="muted">' . e($s[1]) . '</p>'
                . '<a class="link" href="lien-he.html">Đăng ký ngay →</a></div>';
        }

        return <<<HTML
<section class="section">
  <div class="container">
    <div class="section-head">
      <h1>Dịch vụ của chúng tôi</h1>
      <p class="muted">Giải pháp đáp ứng mọi nhu cầu của bạn</p>
    </div>
    <div class="grid">{$cards}</div>
  </div>
</section>
HTML;
    }

    private static function blogBody(array $c, array $t): string
    {
        $posts = '';
        for ($i = 1; $i <= 6; $i++) {
            $posts .= '<article class="post card"><div class="post-thumb"></div>'
                . '<h3>Bài viết số ' . $i . ' — ' . e($c['services'][($i - 1) % count($c['services'])][0]) . '</h3>'
                . '<p class="muted">Những phân tích và hướng dẫn thực tế giúp bạn nắm rõ vấn đề và áp dụng hiệu quả.</p>'
                . '<a class="link" href="lien-he.html">Đọc tiếp →</a></article>';
        }

        return <<<HTML
<section class="section">
  <div class="container">
    <div class="section-head">
      <h1>Bài viết</h1>
      <p class="muted">Kiến thức chia sẻ mỗi ngày</p>
    </div>
    <div class="grid grid-3">{$posts}</div>
  </div>
</section>
HTML;
    }

    private static function contactBody(array $c, array $t): string
    {
        return <<<HTML
<section class="section">
  <div class="container narrow">
    <div class="section-head">
      <h1>Liên hệ với chúng tôi</h1>
      <p class="muted">Gửi lời nhắn — đội ngũ sẽ phản hồi trong thời gian sớm nhất</p>
    </div>
    <form class="form card" onsubmit="event.preventDefault();alert('Cảm ơn bạn! Chúng tôi sẽ liên hệ lại sớm.');">
      <div class="field">
        <label for="n">Họ và tên</label>
        <input id="n" type="text" placeholder="Nguyễn Văn A" required>
      </div>
      <div class="field">
        <label for="e">Email</label>
        <input id="e" type="email" placeholder="email@example.com" required>
      </div>
      <div class="field">
        <label for="p">Số điện thoại</label>
        <input id="p" type="tel" placeholder="0912 345 678">
      </div>
      <div class="field">
        <label for="m">Nội dung</label>
        <textarea id="m" rows="5" placeholder="Nội dung cần hỗ trợ..."></textarea>
      </div>
      <button class="btn btn-primary" type="submit">Gửi đi</button>
    </form>
  </div>
</section>
HTML;
    }

    private static function policyBody(array $c, array $t): string
    {
        return <<<HTML
<section class="section">
  <div class="container narrow">
    <div class="section-head"><h1>Chính sách bảo mật</h1></div>
    <div class="prose">
      <h2>Thu thập thông tin</h2>
      <p class="muted">Chúng tôi chỉ thu thập những thông tin cần thiết cho việc cung cấp dịch vụ và không chia sẻ dữ liệu cho bên thứ ba vì mục đích thương mại.</p>
      <h2>Bảo mật dữ liệu</h2>
      <p class="muted">Toàn bộ dữ liệu được mã hoá khi truyền tải và lưu trữ. Quyền truy cập được phân quyền theo vai trò.</p>
      <h2>Điều khoản sử dụng</h2>
      <p class="muted">Việc sử dụng dịch vụ đồng nghĩa với việc chấp nhận các điều khoản tại đây. Chúng tôi có quyền từ chối phục vụ khi có dấu hiệu lạm dụng.</p>
    </div>
  </div>
</section>
HTML;
    }

    // =================================================================
    //  CSS / JS
    // =================================================================

    private static function buildCss(array $t, string $cat): string
    {
        $accent  = $t['accent'];
        $accent2 = $t['accent2'];

        return <<<CSS
:root{
  --bg:{$t['bg']}; --card:{$t['card']}; --line:{$t['line']};
  --text:{$t['text']}; --muted:{$t['muted']};
  --accent:{$accent}; --accent2:{$accent2};
  --radius:16px; --max:1200px;
  --font:'Inter',system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;
  --shadow:0 10px 40px rgba(0,0,0,.28);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth;-webkit-text-size-adjust:100%}
body{
  font-family:var(--font); background:var(--bg); color:var(--text);
  line-height:1.65; font-size:16px; -webkit-font-smoothing:antialiased;
}
img{max-width:100%;display:block}
a{color:inherit;text-decoration:none}
ul{list-style:none}
.container{width:100%;max-width:var(--max);margin:0 auto;padding:0 20px}
.container.narrow{max-width:820px}
.muted{color:var(--muted)}
.grad{background:linear-gradient(120deg,var(--accent),var(--accent2));-webkit-background-clip:text;background-clip:text;color:transparent}

/* ===== Header ===== */
.site-header{
  position:sticky;top:0;z-index:50;
  background:color-mix(in srgb, var(--bg) 88%, transparent);
  backdrop-filter:blur(14px);
  border-bottom:1px solid var(--line);
}
.site-header .container{display:flex;align-items:center;gap:20px;height:68px}
.logo{display:flex;align-items:center;gap:10px;font-weight:800;font-size:1.05rem;letter-spacing:.2px}
.logo-mark{
  width:32px;height:32px;border-radius:9px;flex:0 0 auto;
  background:linear-gradient(135deg,var(--accent),var(--accent2));
  box-shadow:0 4px 14px rgba(0,0,0,.3);
}
.site-nav{margin-left:auto;display:flex;gap:6px}
.site-nav a{
  padding:9px 14px;border-radius:999px;font-size:.92rem;font-weight:600;
  color:var(--muted);transition:.18s ease;
}
.site-nav a:hover{color:var(--text);background:color-mix(in srgb,var(--accent) 12%, transparent)}
.nav-toggle{
  display:none;margin-left:auto;background:none;border:0;cursor:pointer;
  width:42px;height:42px;border-radius:10px;padding:10px;
}
.nav-toggle span{display:block;height:2px;background:var(--text);border-radius:2px;margin:4px 0;transition:.2s ease}

/* ===== Buttons ===== */
.btn{
  display:inline-flex;align-items:center;justify-content:center;gap:8px;
  padding:12px 24px;border-radius:999px;border:1px solid transparent;
  font-weight:700;font-size:.94rem;cursor:pointer;transition:.18s ease;
}
.btn-primary{background:linear-gradient(120deg,var(--accent),var(--accent2));color:#0b0913;box-shadow:0 8px 24px color-mix(in srgb,var(--accent) 26%, transparent)}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 12px 30px color-mix(in srgb,var(--accent) 34%, transparent)}
.btn-ghost{border-color:var(--line);color:var(--text)}
.btn-ghost:hover{border-color:var(--accent);color:var(--accent)}
.btn-lg{padding:15px 32px;font-size:1rem}
.link{color:var(--accent);font-weight:700;font-size:.9rem}
.link:hover{text-decoration:underline}

/* ===== Hero ===== */
.hero{padding:80px 0 60px;position:relative;overflow:hidden}
.hero::before{
  content:'';position:absolute;top:-180px;right:-120px;width:520px;height:520px;border-radius:50%;
  background:radial-gradient(circle, color-mix(in srgb,var(--accent) 22%, transparent), transparent 68%);
  pointer-events:none;
}
.hero-grid{display:grid;grid-template-columns:1.05fr .95fr;gap:56px;align-items:center;position:relative}
.eyebrow{
  display:inline-block;padding:6px 14px;border-radius:999px;font-size:.76rem;font-weight:800;
  letter-spacing:.6px;text-transform:uppercase;
  background:color-mix(in srgb,var(--accent) 14%, transparent);color:var(--accent);
  border:1px solid color-mix(in srgb,var(--accent) 28%, transparent);
}
.hero-title{font-size:clamp(2rem,4.6vw,3.3rem);line-height:1.15;font-weight:800;margin:18px 0}
.lead{font-size:1.05rem;max-width:52ch}
.hero-actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:28px}
.stats{display:flex;gap:36px;margin-top:44px}
.stats b{display:block;font-size:1.7rem;font-weight:800;color:var(--accent)}
.stats span{font-size:.82rem;color:var(--muted)}
.hero-slider{
  position:relative;border-radius:var(--radius);border:1px solid var(--line);
  background:var(--card);padding:38px;box-shadow:var(--shadow);min-height:290px;
}
.slide{display:none;animation:fade .4s ease}
.slide.active{display:block}
.slide h2{font-size:1.5rem;margin-bottom:12px;font-weight:800}
@keyframes fade{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}

/* ===== Sections ===== */
.section{padding:70px 0}
.section-head{text-align:center;margin-bottom:44px}
.section-head h1,.section-head h2{font-size:clamp(1.6rem,3.4vw,2.3rem);font-weight:800}
.section-head p{margin-top:10px}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:22px}
.grid-3{grid-template-columns:repeat(auto-fit,minmax(290px,1fr))}
.card{
  background:var(--card);border:1px solid var(--line);border-radius:var(--radius);
  padding:26px;transition:.2s ease;
}
.card:hover{transform:translateY(-4px);border-color:color-mix(in srgb,var(--accent) 40%, transparent);box-shadow:var(--shadow)}
.card h3{font-size:1.08rem;font-weight:700;margin:12px 0 8px}
.card-ico{
  width:46px;height:46px;border-radius:13px;display:grid;place-items:center;
  font-weight:800;color:#0b0913;background:linear-gradient(135deg,var(--accent),var(--accent2));
}
.post-thumb{height:140px;border-radius:12px;background:linear-gradient(135deg,var(--accent),var(--accent2));opacity:.24;margin-bottom:16px}
.cta{padding:70px 0;text-align:center;border-top:1px solid var(--line);border-bottom:1px solid var(--line)}
.cta h2{font-size:clamp(1.6rem,3.4vw,2.3rem);font-weight:800}
.cta p{margin:12px 0 26px}
.prose h2{margin:28px 0 10px;font-size:1.2rem;font-weight:700}
.prose p{margin-bottom:12px}
.prose li{margin-left:20px;margin-bottom:8px}

/* ===== Form ===== */
.form{display:flex;flex-direction:column;gap:16px}
.field label{display:block;font-weight:700;font-size:.86rem;margin-bottom:7px}
.field input,.field textarea,.field select{
  width:100%;padding:12px 14px;border-radius:11px;
  background:color-mix(in srgb,var(--bg) 60%, var(--card));
  border:1px solid var(--line);color:var(--text);
  font-family:inherit;font-size:.94rem;transition:.16s ease;
}
.field input:focus,.field textarea:focus,.field select:focus{
  outline:none;border-color:var(--accent);
  box-shadow:0 0 0 3px color-mix(in srgb,var(--accent) 16%, transparent);
}
.field textarea{resize:vertical;min-height:120px}
.field input::placeholder,.field textarea::placeholder{color:var(--muted);opacity:.75}

/* ===== Footer ===== */
.site-footer{border-top:1px solid var(--line);padding:52px 0 0;margin-top:60px}
.footer-grid{display:grid;grid-template-columns:2fr 1fr 1fr;gap:40px}
.footer-grid h4{font-weight:800;margin-bottom:12px;font-size:1.05rem}
.footer-grid h5{font-weight:700;margin-bottom:12px;font-size:.9rem;color:var(--accent)}
.footer-grid li{margin-bottom:7px;font-size:.9rem;color:var(--muted)}
.footer-bottom{border-top:1px solid var(--line);margin-top:36px;padding:18px 0;font-size:.84rem;color:var(--muted)}

/* ===== Responsive ===== */
@media (max-width:900px){
  .hero-grid{grid-template-columns:1fr;gap:38px}
  .footer-grid{grid-template-columns:1fr 1fr}
  .nav-toggle{display:block}
  .site-nav{
    position:absolute;top:68px;left:0;right:0;flex-direction:column;
    background:var(--bg);border-bottom:1px solid var(--line);padding:10px 20px 18px;
    display:none;
  }
  .site-nav.open{display:flex}
  .site-nav a{padding:12px 14px}
}
@media (max-width:600px){
  .stats{gap:22px}
  .footer-grid{grid-template-columns:1fr}
  .hero{padding:48px 0 40px}
  .section{padding:48px 0}
}
CSS;
    }

    private static function buildJs(): string
    {
        return <<<'JS'
(function () {
  'use strict';

  // ===== Menu mobile =====
  var toggle = document.getElementById('navToggle');
  var nav    = document.getElementById('siteNav');
  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      nav.classList.toggle('open');
    });
  }

  // ===== Slider hero =====
  var slider = document.getElementById('heroSlider');
  if (slider) {
    var slides = slider.querySelectorAll('.slide');
    var idx = 0;
    if (slides.length > 1) {
      setInterval(function () {
        slides[idx].classList.remove('active');
        idx = (idx + 1) % slides.length;
        slides[idx].classList.add('active');
      }, 5000);
    }
  }

  // ===== Smooth scroll nội bộ =====
  document.querySelectorAll('a[href^="#"]').forEach(function (a) {
    a.addEventListener('click', function (e) {
      var el = document.querySelector(a.getAttribute('href'));
      if (el) { e.preventDefault(); el.scrollIntoView({ behavior: 'smooth' }); }
    });
  });
})();
JS;
    }

    private static function buildFavicon(string $accent): string
    {
        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="{$accent}"/>
      <stop offset="100%" stop-color="{$accent}" stop-opacity=".65"/>
    </linearGradient>
  </defs>
  <rect width="64" height="64" rx="16" fill="url(#g)"/>
  <path d="M20 44V20h6l6 10 6-10h6v24h-6V31l-6 9-6-9v13z" fill="#0b0913" opacity=".85"/>
</svg>
SVG;
    }

    private static function buildSitemap(string $domain, array $pages): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($pages as $p) {
            $loc = $domain !== '' ? rtrim($domain, '/') . '/' . $p : $p;
            $xml .= "  <url><loc>" . e($loc) . "</loc></url>\n";
        }
        $xml .= '</urlset>';
        return $xml;
    }

    private static function buildRobots(string $domain): string
    {
        $base = $domain !== '' ? 'https://' . rtrim($domain, '/') : '';
        return "User-agent: *\nAllow: /\n\nSitemap: {$base}/sitemap.xml\n";
    }
}
