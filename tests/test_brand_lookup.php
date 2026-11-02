<?php
require_once __DIR__ . '/bootstrap.php';

$checks = [
    ['Cursor', 'ai'],
    ['ChatGPT', 'ai'],
    ['Claude', 'ai'],
    ['Gemini', 'ai'],
    ['Midjourney', 'ai'],
    ['Copilot', 'ai'],
    ['Canva', 'ai'],
    ['Discord', 'ai'],
    ['Discord', 'sim'],
    ['MoMo', 'sim'],
    ['MoMo', 'bank'],
    ['Telegram', 'sim'],
    ['Zalo', 'sim'],
    ['WhatsApp', 'sim'],
    ['TikTok', 'sim'],
    ['Shopee', 'sim'],
    ['Twilio', 'sim'],
    ['Microsoft', 'ai'],
    ['Google', 'ai'],
    ['PG Soft', 'game'],
    ['JILI', 'game'],
    ['Pragmatic Play', 'game'],
    ['FastSpin', 'game'],
    ['CQ9', 'game'],
    ['Spribe', 'game'],
    ['Vietcombank', 'bank'],
    ['MB Bank', 'bank'],
    ['Techcombank', 'bank'],
];

$bad = [];
foreach ($checks as [$name, $g]) {
    $url = brand_logo_url($name, $g);
    if ($url === '' || str_contains($url, 'placeholder')) {
        $bad[] = "{$name}[{$g}]";
    }
}

echo "Kiểm tra " . count($checks) . " tổ hợp logo\n";
if ($bad) {
    echo "  THIẾU: " . implode(', ', $bad) . "\n";
    exit(1);
}
echo "  Tất cả OK\n";
