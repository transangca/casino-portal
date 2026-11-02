<?php
/**
 * Kiểm tra toàn bộ logo thương hiệu có tồn tại file thật không.
 * Dùng đúng hàm resolve thực tế (brand_logo_url) để tránh false-negative.
 * Chạy: php tests/test_brand_assets.php
 */

require_once __DIR__ . '/bootstrap.php';

$manifest = brand_manifest();
$missing = [];
$resolved = 0;
$placeholder = 0;

foreach ($manifest as $slug => $meta) {
    $url = brand_logo_url($slug);
    if ($url === '') {
        $missing[] = $slug . ' (' . implode('/', $meta['groups'] ?? [$meta['group']]) . ' → ' . $meta['file'] . ')';
        continue;
    }
    if (str_contains($url, 'placeholder.svg')) {
        $placeholder++;
        continue;
    }
    $resolved++;
}

echo "Brand manifest: " . count($manifest) . " mục\n";
echo "  Có logo riêng: {$resolved}\n";
echo "  Dùng placeholder: {$placeholder}\n";
echo "  Thiếu hoàn toàn:  " . count($missing) . "\n";
if ($missing) {
    echo "\nDanh sách thiếu:\n";
    foreach ($missing as $m) {
        echo "  - {$m}\n";
    }
}

// Kiểm tra nhóm "sim" và "ai" — mọi slug phải có file trong đúng nhóm đó
echo "\n-- Đối chiếu theo nhóm --\n";
foreach (['ai', 'sim', 'bank', 'game'] as $g) {
    $slugs = brand_by_group($g);
    $bad = [];
    foreach ($slugs as $s) {
        $meta = $manifest[$s];
        $found = false;
        foreach (($meta['groups'] ?? [$meta['group']]) as $gg) {
            foreach (['svg', 'png'] as $ext) {
                if (is_file(BASE_PATH . '/public/assets/img/brands/' . $gg . '/' . $meta['file'] . '.' . $ext)) {
                    $found = true;
                    break 2;
                }
            }
        }
        if (!$found) {
            foreach (['png', 'svg', 'jpg', 'jpeg', 'webp'] as $ext) {
                if (is_file(BASE_PATH . '/public/assets/img/providers/' . $meta['file'] . '.' . $ext)) {
                    $found = true;
                    break;
                }
            }
        }
        if (!$found) {
            $bad[] = $s;
        }
    }
    printf("  %-6s: %2d slug, thiếu %d %s\n", $g, count($slugs), count($bad), $bad ? '(' . implode(', ', $bad) . ')' : '');
}

exit($missing ? 1 : 0);
