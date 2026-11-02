<?php
/**
 * Menu bên trái các trang công cụ (partial).
 * @var string $toolKey
 */
$toolKey = $toolKey ?? '';
$toolNav = [
    'hash'     => ['Hash chuỗi', '/tools/hash', 'fa-hashtag'],
    'json'     => ['Format JSON', '/tools/json', 'fa-code'],
    'password' => ['Sinh mật khẩu', '/tools/password', 'fa-key'],
    'convert'  => ['Đổi đơn vị', '/tools/convert', 'fa-right-left'],
    'uuid'     => ['Sinh UUID', '/tools/uuid', 'fa-fingerprint'],
    'base64'   => ['Base64', '/tools/base64', 'fa-shuffle'],
    'ip'       => ['Tra cứu IP', '/tools/ip', 'fa-network-wired'],
    'whois'    => ['WHOIS', '/tools/whois', 'fa-address-book'],
    'seo'      => ['Kiểm tra SEO', '/tools/seo', 'fa-magnifying-glass-chart'],
    'speed'    => ['Đo tốc độ', '/tools/speed', 'fa-gauge-high'],
];
?>
<div class="panel-card tool-nav-card">
    <div class="services-col-title mb-2">Danh mục</div>
    <?php foreach ($toolNav as $key => $t): ?>
        <a href="<?= url($t[1]) ?>" class="tool-nav-item <?= $toolKey === $key ? 'active' : '' ?>">
            <i class="fas <?= $t[2] ?>"></i><?= e($t[0]) ?>
        </a>
    <?php endforeach; ?>
</div>
