<?php
namespace App\Services;

use App\Core\Database;

/**
 * Hệ thống VIP 60 cấp.
 *
 * - Mỗi cấp có tên riêng, ngưỡng nạp, tỷ lệ thưởng/hoàn tiền, màu, badge 3D.
 * - Từ VIP 5 trở đi được tạo website con, % ăn chia do Admin đặt.
 */
class VipService
{
    public const MAX_LEVEL = 60;

    /** Danh sách 60 tên cấp bậc (tên tiếng Việt) */
    public const TIER_NAMES = [
        1  => 'Tân Bin',        2  => 'Thân Nhân',      3  => 'Tích Lũy',       4  => 'Đồng',          5  => 'Bạc',
        6  => 'Bạc Plus',      7  => 'Đồng Plus',      8  => 'Bạch Kim',       9  => 'Vàng Kim',      10 => 'Bạch Kim II',
        11 => 'Platinum',      12 => 'Platinum II',    13 => 'Đá Quý',        14 => 'Ngọc Trai',     15 => 'Ruby',
        16 => 'Ruby II',       17 => 'Kim Cương',      18 => 'Kim Cương II',   19 => 'Huyền Thoại',   20 => 'Thần Tích',
        21 => 'Sắt',           22 => 'Titan',          23 => 'Titan II',       24 => 'Đại Tướng',    25 => 'Chiến Tướng',
        26 => 'Hoàng Gia',      27 => 'Hoàng Tộc',      28 => 'Đế Vương',      29 => 'Minh Quân',     30 => 'Quân Vương',
        31 => 'Thái Hậa',      32 => 'Thiên Tử',       33 => 'Thiên Hoàng',    34 => 'Tử Đế',        35 => 'Đế Long',
        36 => 'Chí Tôn',       37 => 'Thánh Tôn',      38 => 'Thần Đế',       39 => 'Thiên Đế',      40 => 'Hồng Mông',
        41 => 'Huyền Thiên',   42 => 'Huyền Vũ',       43 => 'Thái Cổ',       44 => 'Cổ Đại',       45 => 'Thượng Cổ',
        46 => 'Hiện Đại',      47 => 'Tương Lai',      48 => 'Vô Cùng',       49 => 'Siêu Nhân',     50 => 'Huyền thoại',
        51 => 'Chí Mạng',      52 => 'Bất Diệt',       53 => 'Bất Hủy',       54 => 'Vô Song',      55 => 'Vô Đối',
        56 => 'Tôn Sư',        57 => 'Tôn Sư II',      58 => 'Chí Tôn II',    59 => 'Đế Tôn',       60 => 'HOÀNG ĐẾ',
    ];

    /** Màu badge theo cấp */
    public const TIER_COLORS = [
        1 => '#8a94a6', 2 => '#9aa5b5', 3 => '#a8b2c0', 4 => '#b6c0cd', 5 => '#cdd6df',
        6 => '#d9e2ea', 7 => '#e6edf3', 8 => '#eef3f7', 9 => '#f4f8fb', 10 => '#fafdff',
        11 => '#c0c6d1', 12 => '#c9cfd9', 13 => '#d2d8e1', 14 => '#dbe1e9', 15 => '#e4e9f0',
        16 => '#edf1f6', 17 => '#f5f8fb', 18 => '#fbfdff', 19 => '#c7b299', 20 => '#d4bd9c',
        21 => '#e0c9a4', 22 => '#ead5ac', 23 => '#f2e1b6', 24 => '#f8ecc6', 25 => '#fdf6da',
        26 => '#bfa06a', 27 => '#cbb078', 28 => '#d6c08a', 29 => '#e0d09c', 30 => '#ebe0b0',
        31 => '#a8b98f', 32 => '#b5c59c', 33 => '#c2d1aa', 34 => '#cfddb9', 35 => '#dce9c8',
        36 => '#9fc3d8', 37 => '#aecfe1', 38 => '#bddbea', 39 => '#cce7f3', 40 => '#dbf3fc',
        41 => '#c3a8d8', 42 => '#cfb7e1', 43 => '#dac6ea', 44 => '#e5d5f2', 45 => '#efe4fa',
        46 => '#d8a8a8', 47 => '#e3b9b9', 48 => '#eecaca', 49 => '#f7dbdb', 50 => '#fdecec',
        51 => '#f2c14e', 52 => '#f7cd68', 53 => '#f9d883', 54 => '#fbe39e', 55 => '#fceeb9',
        56 => '#f0d264', 57 => '#f5dc85', 58 => '#f9e6a6', 59 => '#fcf0c7', 60 => '#fff8e0',
    ];

    /** Ưu đãi riêng theo mốc */
    public const PERKS = [
        1  => ['Hoàn tiền 1%', 'Thưởng nạp 1%'],
        2  => ['Hoàn tiền 1.5%', 'Thưởng nạp 2%', 'Hỗ trợ nhanh'],
        3  => ['Hoàn tiền 2%', 'Thưởng nạp 3%', 'Rút tiền ưu tiên'],
        4  => ['Hoàn tiền 2.5%', 'Thưởng nạp 4%', 'Bonus sinh nhật'],
        5  => ['Hoàn tiền 3%', 'Thưởng nạp 5%', 'Tạo website con', 'Ẩn giá khởi điểm'],
        6  => ['Hoàn tiền 3.5%', 'Thưởng nạp 6%', 'Ưu đãi gói chơi +5%'],
        7  => ['Hoàn tiền 4%', 'Thưởng nạp 7%', 'Mã giảm giá riêng'],
        8  => ['Hoàn tiền 4.5%', 'Thưởng nạp 8%', 'Rút tiền tối đa +20%'],
        9  => ['Hoàn tiền 5%', 'Thưởng nạp 9%', 'Hỗ trợ riêng 24/7'],
        10 => ['Hoàn tiền 5.5%', 'Thưởng nạp 10%', 'Website con +2% ăn chia'],
        15 => ['Hoàn tiền 7%', 'Thưởng nạp 15%', 'Ưu đãi sinh nhật x2'],
        20 => ['Hoàn tiền 8%', 'Thưởng nạp 20%', 'Mở khóa sảnh độc quyền'],
        25 => ['Hoàn tiền 9%', 'Thưởng nạp 25%', 'Tặng gói chơi miễn phí'],
        30 => ['Hoàn tiền 10%', 'Thưởng nạp 30%', 'Website con +5% ăn chia'],
        35 => ['Hoàn tiền 11%', 'Thưởng nạp 35%', 'Ưu tiên vào sảnh hot'],
        40 => ['Hoàn tiền 12%', 'Thưởng nạp 40%', 'Quà tặng giá trị cao'],
        45 => ['Hoàn tiền 13%', 'Thưởng nạp 45%', 'Rút tiền không giới hạn'],
        50 => ['Hoàn tiền 15%', 'Thưởng nạp 50%', 'Sự kiện riêng VIP50'],
        55 => ['Hoàn tiền 17%', 'Thưởng nạp 55%', 'Ưu đãi toàn sảnh +10%'],
        60 => ['Hoàn tiền 20%', 'Thưởng nạp 60%', 'Danh hiệu HOÀNG ĐẾ', 'Quà tặng đặc biệt'],
    ];

    public static function all(): array
    {
        return Database::fetchAll('SELECT * FROM vip_tiers ORDER BY level');
    }

    public static function find(int $level): ?array
    {
        return Database::fetch('SELECT * FROM vip_tiers WHERE level = ?', [$level]);
    }

    /** Cấp hiện tại của user (dựa trên tổng nạp) */
    public static function levelOf(int $userId): int
    {
        $total = (float)Database::fetchColumn(
            'SELECT COALESCE(SUM(amount),0) FROM wallet_transactions WHERE user_id = ? AND type IN ("deposit","giftcode","win")',
            [$userId]
        );
        return self::levelForAmount($total);
    }

    public static function levelForAmount(float $amount): int
    {
        $row = Database::fetch(
            'SELECT level FROM vip_tiers WHERE deposit_required <= ? ORDER BY level DESC LIMIT 1',
            [$amount]
        );
        return (int)($row['level'] ?? 0);
    }

    /** Đồng bộ users.vip_level theo tổng nạp */
    public static function syncUserLevel(int $userId): int
    {
        $level = self::levelOf($userId);
        Database::update('users', ['vip_level' => $level], 'id = :id', ['id' => $userId]);
        return $level;
    }

    /**
     * Nhóm badge 3D — mỗi nhóm một hình khác nhau, không lặp lại.
     *
     * 13 nhóm phân bổ đều cho 60 cấp:
     *  1–5   Bronze      — 5 cấp
     *  6–10  Silver      — 5 cấp
     *  11–15 Gold        — 5 cấp
     *  16–20 Platinum    — 5 cấp
     *  21–25 Diamond     — 5 cấp
     *  26–30 Obsidian    — 5 cấp
     *  31–35 Crimson     — 5 cấp
     *  36–40 Emerald     — 5 cấp
     *  41–45 Void        — 5 cấp
     *  46–50 Rainbow     — 5 cấp
     *  51–55 Legendary   — 5 cấp
     *  56–58 Mythic      — 3 cấp
     *  59–60 Ascend      — 2 cấp
     */
    public const TIER_GROUPS = [
        1  => 'bronze', 2  => 'bronze', 3  => 'bronze', 4  => 'bronze', 5  => 'bronze',
        6  => 'silver', 7  => 'silver', 8  => 'silver', 9  => 'silver', 10 => 'silver',
        11 => 'gold',   12 => 'gold',   13 => 'gold',   14 => 'gold',   15 => 'gold',
        16 => 'platinum', 17 => 'platinum', 18 => 'platinum', 19 => 'platinum', 20 => 'platinum',
        21 => 'diamond', 22 => 'diamond', 23 => 'diamond', 24 => 'diamond', 25 => 'diamond',
        26 => 'obsidian', 27 => 'obsidian', 28 => 'obsidian', 29 => 'obsidian', 30 => 'obsidian',
        31 => 'crimson', 32 => 'crimson', 33 => 'crimson', 34 => 'crimson', 35 => 'crimson',
        36 => 'emerald', 37 => 'emerald', 38 => 'emerald', 39 => 'emerald', 40 => 'emerald',
        41 => 'void',    42 => 'void',    43 => 'void',    44 => 'void',    45 => 'void',
        46 => 'rainbow', 47 => 'rainbow', 48 => 'rainbow', 49 => 'rainbow', 50 => 'rainbow',
        51 => 'legendary', 52 => 'legendary', 53 => 'legendary', 54 => 'legendary', 55 => 'legendary',
        56 => 'mythic', 57 => 'mythic', 58 => 'mythic',
        59 => 'ascend', 60 => 'ascend',
    ];

    /** Bảng màu chính + màu phụ + kiểu hình cho 13 nhóm badge */
    private const BADGE_STYLES = [
        'bronze'   => ['c1' => '#f3c9a4', 'c2' => '#c2762f', 'c3' => '#5a2c0d', 'ring' => '#f0b183', 'shape' => 'shield'],
        'silver'   => ['c1' => '#ffffff', 'c2' => '#b9c4d4', 'c3' => '#5c6672', 'ring' => '#e2eaf3', 'shape' => 'hex'],
        'gold'     => ['c1' => '#fff4c4', 'c2' => '#f0bd33', 'c3' => '#8a5f06', 'ring' => '#ffe08a', 'shape' => 'circle'],
        'platinum' => ['c1' => '#f6feff', 'c2' => '#9fd8e8', 'c3' => '#2f6a80', 'ring' => '#c9f0fb', 'shape' => 'diamond'],
        'diamond'  => ['c1' => '#ffffff', 'c2' => '#9ee8f5', 'c3' => '#12587c', 'ring' => '#e6fbff', 'shape' => 'gem'],
        'obsidian' => ['c1' => '#8a79ef', 'c2' => '#3d2f8f', 'c3' => '#0d0928', 'ring' => '#a99bf5', 'shape' => 'octagon'],
        'crimson'  => ['c1' => '#ffb0b0', 'c2' => '#d92b2b', 'c3' => '#4d0909', 'ring' => '#ff8080', 'shape' => 'shield'],
        'emerald'  => ['c1' => '#a8ffd6', 'c2' => '#17a862', 'c3' => '#034225', 'ring' => '#6cf0ad', 'shape' => 'pentagon'],
        'void'     => ['c1' => '#6d6d85', 'c2' => '#1c1c28', 'c3' => '#020204', 'ring' => '#8d8daa', 'shape' => 'octagon'],
        'rainbow'  => ['c1' => '#ff7a86', 'c2' => '#7ee8fa', 'c3' => '#2e1560', 'ring' => '#ffffff', 'shape' => 'gem'],
        'legendary'=> ['c1' => '#ffe28f', 'c2' => '#ff7a2f', 'c3' => '#5f0a0a', 'ring' => '#ffcf5c', 'shape' => 'crown'],
        'mythic'   => ['c1' => '#d4f6ff', 'c2' => '#4f8bff', 'c3' => '#140430', 'ring' => '#9ad8ff', 'shape' => 'star'],
        'ascend'   => ['c1' => '#fffef7', 'c2' => '#ffd166', 'c3' => '#8f0a13', 'ring' => '#fff3c4', 'shape' => 'crown'],
    ];

    /**
     * Nhóm badge của 1 cấp.
     */
    public static function group(int $level): string
    {
        return self::TIER_GROUPS[$level] ?? 'bronze';
    }

    /**
     * Badge SVG phong cách kim loại cao cấp.
     *
     * Thiết kế:
     *  - Nền gradient kim loại 3 chặt dọc (sáng → chính → tối)
     *  - Lớp texture satin mờ (đường sọc mảnh, opacity thấp) → không còn "nhựa"
     *  - Viền ngoài gradient sáng + viền trong hairline tạo độ sâu
     *  - Highlight dạng quầng mảnh ở nửa trên, KHÔNG dùng radial trắng đục
     *  - Số cấp font-weight 900, tracking âm
     */
    public static function badgeSvg(int $level, int $size = 56): string
    {
        $level = max(1, min(self::MAX_LEVEL, $level));
        $group = self::group($level);
        $style = self::BADGE_STYLES[$group];
        $name  = self::TIER_NAMES[$level] ?? ('VIP ' . $level);

        $label = (string)mb_substr($name, 0, 14);
        $big   = $size >= 90 ? 22 : ($size >= 60 ? 21 : 19);
        $ls    = $size >= 90 ? 8 : ($size >= 60 ? 7.5 : 7);
        $lsb   = $size >= 90 ? 9.5 : ($size >= 60 ? 9 : 8.5);

        // Độ đậm viền tăng dần theo bậc trong nhóm (0..4)
        $step = ($level - 1) % 5;
        $sw   = 1.6 + ($step * 0.22);
        $uid  = 'vb' . $level . 'x';

        $shape = self::shapePath($style['shape']);
        $inner = self::innerPath($style['shape']);

        // Nhóm cao cấp: thêm ánh sáng quanh
        $premium = in_array($group, ['legendary', 'mythic', 'ascend', 'rainbow', 'void'], true);
        $glow = '';
        if ($premium) {
            $glow = '<filter id="g' . $uid . '" x="-50%" y="-50%" width="200%" height="200%">'
                . '<feGaussianBlur stdDeviation="1.9" result="b"/>'
                . '<feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge></filter>';
        }

        // Vân satin: các đường kẻ dọc mảnh tạo cảm giác kim loại xước
        $texture = '';
        for ($i = 8; $i <= 56; $i += 6) {
            $texture .= '<line x1="' . $i . '" y1="4" x2="' . $i . '" y2="60" '
                . 'stroke="#ffffff" stroke-width=".35" opacity=".10"/>';
        }

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="{$size}" height="{$size}" role="img" aria-label="VIP {$level} — {$label}">
  <defs>
    <linearGradient id="mt{$uid}" x1="0" y1="0" x2="0.18" y2="1">
      <stop offset="0%" stop-color="{$style['c1']}"/>
      <stop offset="34%" stop-color="{$style['c2']}"/>
      <stop offset="72%" stop-color="{$style['c3']}"/>
      <stop offset="100%" stop-color="{$style['c3']}"/>
    </linearGradient>
    <linearGradient id="rm{$uid}" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="{$style['ring']}"/>
      <stop offset="38%" stop-color="#ffffff" stop-opacity=".85"/>
      <stop offset="70%" stop-color="{$style['ring']}" stop-opacity=".65"/>
      <stop offset="100%" stop-color="{$style['c3']}"/>
    </linearGradient>
    <linearGradient id="gl{$uid}" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0%" stop-color="#ffffff" stop-opacity=".34"/>
      <stop offset="45%" stop-color="#ffffff" stop-opacity=".06"/>
      <stop offset="100%" stop-color="#ffffff" stop-opacity="0"/>
    </linearGradient>
    <clipPath id="cp{$uid}"><path d="{$shape}"/></clipPath>
    {$glow}
  </defs>
  <g filter="url(#g{$uid})">
    <path d="{$shape}" fill="url(#mt{$uid})"/>
    <g clip-path="url(#cp{$uid})">{$texture}</g>
    <path d="{$shape}" fill="url(#gl{$uid})"/>
    <path d="{$inner}" fill="none" stroke="#ffffff" stroke-opacity=".22" stroke-width=".7"/>
    <path d="{$shape}" fill="none" stroke="url(#rm{$uid})" stroke-width="{$sw}" stroke-linejoin="round"/>
  </g>
  <text x="32" y="36" text-anchor="middle" font-family="Inter,Segoe UI,Arial,sans-serif"
        font-size="{$big}" font-weight="900" letter-spacing="-1" fill="#ffffff"
        stroke="rgba(0,0,0,.45)" stroke-width=".8" paint-order="stroke">{$level}</text>
  <text x="32" y="47" text-anchor="middle" font-family="Inter,Segoe UI,Arial,sans-serif"
        font-size="{$lsb}" font-weight="800" letter-spacing="2.2"
        fill="rgba(255,255,255,.82)">VIP</text>
  <text x="32" y="56" text-anchor="middle" font-family="Inter,Segoe UI,Arial,sans-serif"
        font-size="{$ls}" font-weight="600" letter-spacing=".3"
        fill="rgba(255,255,255,.74)">{$label}</text>
</svg>
SVG;
    }

    /**
     * Đường viền theo kiểu hình (mỗi nhóm 1 kiểu riêng).
     */
    private static function shapePath(string $shape): string
    {
        switch ($shape) {
            case 'hex':
                return 'M32 2.5 L57.5 17 V47 L32 61.5 L6.5 47 V17 Z';
            case 'diamond':
                return 'M32 2 L60 32 L32 62 L4 32 Z';
            case 'gem':
                return 'M32 2 L58 19 L49 51 L32 62 L15 51 L6 19 Z';
            case 'crown':
                return 'M5 45 L9 15 L22 29 L32 8 L42 29 L55 15 L59 45 L32 58 Z';
            case 'star':
                return 'M32 2 L40.5 22.5 L62 23.5 L45.5 38 L51.5 59 L32 47.5 L12.5 59 L18.5 38 L2 23.5 L23.5 22.5 Z';
            case 'shield':
                return 'M32 2.5 L57 11 V32 C57 46 45 56 32 61.5 C19 56 7 46 7 32 V11 Z';
            case 'octagon':
                return 'M22 3 H42 L61 22 V42 L42 61 H22 L3 42 V22 Z';
            case 'pentagon':
                return 'M32 2.5 L60 24 L49 58 H15 L4 24 Z';
            case 'lozenge':
                return 'M32 2 L60 32 L32 62 L4 32 Z';
            case 'chevron':
                return 'M32 3 L60 25 V47 L32 61 L4 47 V25 Z';
            case 'circle':
            default:
                return 'M32 2.5 A29.5 29.5 0 1 1 31.9 2.5 Z';
        }
    }

    /**
     * Đường viền phụ (inner ring) — tạo cảm giác kim loại mạ chứ không phải nhựa.
     */
    private static function innerPath(string $shape): string
    {
        switch ($shape) {
            case 'hex':
                return 'M32 9 L51.5 20 V44 L32 55 L12.5 44 V20 Z';
            case 'diamond':
                return 'M32 12 L50 32 L32 52 L14 32 Z';
            case 'gem':
                return 'M32 12 L50 24 L44 46 L32 54 L20 46 L14 24 Z';
            case 'crown':
                return 'M14 41 L16 24 L24 33 L32 19 L40 33 L48 24 L50 41 L32 49 Z';
            case 'star':
                return 'M32 12 L38 27 L54 28 L41 39 L45 54 L32 45 L19 54 L23 39 L10 28 L26 27 Z';
            case 'shield':
                return 'M32 10 L50 16 V32 C50 42 41 50 32 54.5 C23 50 14 42 14 32 V16 Z';
            case 'octagon':
                return 'M25 10 H39 L54 25 V39 L39 54 H25 L10 39 V25 Z';
            case 'pentagon':
                return 'M32 11 L52 27 L44 51 H20 L12 27 Z';
            case 'lozenge':
                return 'M32 13 L49 32 L32 51 L15 32 Z';
            case 'chevron':
                return 'M32 12 L52 28 V43 L32 52 L12 43 V28 Z';
            case 'circle':
            default:
                return 'M32 9 A23 23 0 1 1 31.9 9 Z';
        }
    }

    /** HTML badge inline (dùng trong view) */
    public static function badge(int $level, int $size = 44): string
    {
        return self::badgeSvg($level, $size);
    }

    /** Class CSS của nhóm badge (để áp màu nền cho card) */
    public static function groupClass(int $level): string
    {
        return 't-' . self::group($level);
    }

    /**
     * Seed 60 cấp bậc — chạy lại nhiều lần được (không ghi đè cấu hình admin đã sửa).
     */
    public static function seed(): int
    {
        $maxShare = (float)setting('child_site_max_rev_share', 0.30);
        $count = 0;

        for ($lvl = 1; $lvl <= self::MAX_LEVEL; $lvl++) {
            // Ngưỡng nạp tăng dần theo cấp (1tr → 5 tỷ)
            $threshold = (int)round(1000000 * pow($lvl, 1.65));

            $bonusRate   = min(0.60, 0.005 * $lvl + 0.005 * pow($lvl, 1.25));
            $cashbackRate = min(0.20, 0.005 * $lvl);

            $perks = self::PERKS[$lvl] ?? [
                'Hoàn tiền ' . number_format($cashbackRate * 100, 1) . '%',
                'Thưởng nạp ' . number_format($bonusRate * 100, 1) . '%',
            ];

            $canChild  = $lvl >= (int)setting('child_site_min_vip', 5);
            $revShare  = $canChild ? min($maxShare, 0.02 + ($lvl - 5) * 0.006) : 0.0;

            $existing = self::find($lvl);
            $data = [
                'name'            => self::TIER_NAMES[$lvl] ?? ('VIP ' . $lvl),
                'deposit_required'=> $threshold,
                'bonus_rate'      => round($bonusRate, 3),
                'cashback_rate'   => round($cashbackRate, 3),
                'perks'           => json_encode($perks, JSON_UNESCAPED_UNICODE),
                'badge_3d'        => self::badgeSvg($lvl, 96),
                'color'           => self::TIER_COLORS[$lvl] ?? '#c9a227',
                'can_child_site'  => $canChild ? 1 : 0,
                'rev_share_max'   => round($revShare, 3),
            ];

            if ($existing) {
                Database::update('vip_tiers', $data, 'level = :level', ['level' => $lvl]);
            } else {
                Database::insert('vip_tiers', $data + ['level' => $lvl]);
            }
            $count++;
        }
        return $count;
    }
}
