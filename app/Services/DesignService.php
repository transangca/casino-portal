<?php
namespace App\Services;

use App\Core\Database;

/**
 * Thiết kế ảnh (poster/banner) theo mẫu — người dùng nhập thông tin,
 * hệ thống dựng ảnh SVG/Canvas phía server với preview.
 *
 * Font: admin upload file font, tạo "biến thể" (variant) với mã riêng
 * (VD: "HOVA_CO_DAM"). Variant gắn với 1 font + 1 mẫu chữ. Admin kéo
 * variant vào đúng vị trí trong bố cục (canvas) của template.
 */
class DesignService
{
    public const CATEGORIES = [
        'poster'    => 'Poster quảng cáo',
        'banner'    => 'Banner website',
        'facebook'  => 'Ảnh bài đăng MXH',
        'thumbnail' => 'Ảnh nhỏ video',
        'certificate' => 'Chứng chỉ',
        'menu'      => 'Thực đơn',
        'other'     => 'Khác',
    ];

    /** Các trường người dùng nhập cho mỗi danh mục */
    public const FIELD_SCHEMA = [
        'poster' => [
            ['key' => 'headline', 'label' => 'Tiêu đề chính', 'type' => 'text',  'required' => true],
            ['key' => 'subline',  'label' => 'Dòng phụ',      'type' => 'text'],
            ['key' => 'body',     'label' => 'Nội dung',      'type' => 'textarea'],
            ['key' => 'cta',      'label' => 'Chữ kêu gọi',   'type' => 'text'],
            ['key' => 'footer',   'label' => 'Chân trang',     'type' => 'text'],
        ],
        'banner' => [
            ['key' => 'headline', 'label' => 'Tiêu đề',   'type' => 'text', 'required' => true],
            ['key' => 'subline',  'label' => 'Mô tả',     'type' => 'text'],
            ['key' => 'cta',      'label' => 'Nút',       'type' => 'text'],
        ],
        'facebook' => [
            ['key' => 'headline', 'label' => 'Tiêu đề',   'type' => 'text', 'required' => true],
            ['key' => 'body',     'label' => 'Nội dung',  'type' => 'textarea'],
            ['key' => 'footer',   'label' => 'Dòng cuối', 'type' => 'text'],
        ],
        'thumbnail' => [
            ['key' => 'headline', 'label' => 'Tiêu đề',   'type' => 'text', 'required' => true],
            ['key' => 'subline',  'label' => 'Phụ đề',    'type' => 'text'],
        ],
        'certificate' => [
            ['key' => 'headline', 'label' => 'Tiêu đề',     'type' => 'text', 'required' => true],
            ['key' => 'name',     'label' => 'Tên người nhận', 'type' => 'text', 'required' => true],
            ['key' => 'body',     'label' => 'Nội dung',    'type' => 'textarea'],
            ['key' => 'footer',   'label' => 'Chứng danh',  'type' => 'text'],
        ],
        'menu' => [
            ['key' => 'headline', 'label' => 'Tên quán',   'type' => 'text', 'required' => true],
            ['key' => 'body',     'label' => 'Món (mỗi dòng: Tên - Giá)', 'type' => 'textarea'],
        ],
        'other' => [
            ['key' => 'headline', 'label' => 'Tiêu đề',   'type' => 'text'],
            ['key' => 'body',     'label' => 'Nội dung',  'type' => 'textarea'],
        ],
    ];

    public static function categoryLabel(string $code): string
    {
        return self::CATEGORIES[$code] ?? $code;
    }

    public static function schema(string $category): array
    {
        return self::FIELD_SCHEMA[$category] ?? self::FIELD_SCHEMA['other'];
    }

    // ---------------- Font ----------------

    public static function fonts(): array
    {
        return Database::fetchAll(
            'SELECT f.*, (SELECT COUNT(*) FROM design_variants v WHERE v.font_id = f.id) AS variant_count
             FROM design_fonts f WHERE f.is_active = 1 ORDER BY f.id DESC'
        );
    }

    /**
     * Tạo mã biến thể tự động từ họ + tên: "Họ Nguyễn" + "Đậm" → HO_NGUYEN_DAM.
     */
    public static function generateVariantCode(string $family, string $label): string
    {
        $strip = static function (string $s): string {
            $s = trim($s);
            $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
            $s = preg_replace('/[^\p{L}\p{N}]+/u', '_', $s) ?? $s;
            $s = trim($s, '_');
            return strtoupper($s);
        };

        $parts = array_filter([$strip($family), $strip($label)]);
        $code  = implode('_', $parts);
        $code  = preg_replace('/_+/', '_', $code) ?? $code;

        // Tránh trùng
        $base = $code ?: 'VAR';
        $i    = 1;
        while (Database::fetchColumn('SELECT id FROM design_variants WHERE code = ?', [$code])) {
            $code = $base . '_' . $i;
            $i++;
        }
        return $code;
    }

    public static function createVariant(array $data): array
    {
        $family = trim((string)($data['family'] ?? ''));
        $label  = trim((string)($data['label'] ?? ''));
        if ($family === '' || $label === '') {
            return ['ok' => false, 'message' => 'Vui lòng nhập họ font và tên biến thể.'];
        }

        // Tìm hoặc tạo font
        $fontId = (int)($data['font_id'] ?? 0);
        if ($fontId <= 0) {
            $fontId = (int)Database::fetchColumn(
                'SELECT id FROM design_fonts WHERE family = ? LIMIT 1',
                [$family]
            );
            if (!$fontId) {
                $fontId = Database::insert('design_fonts', [
                    'family'    => $family,
                    'file_path' => (string)($data['file_path'] ?? ''),
                    'weight'    => (string)($data['weight'] ?? '400'),
                    'format'    => (string)($data['format'] ?? 'ttf'),
                    'is_active' => 1,
                ]);
            }
        }

        $code = trim((string)($data['code'] ?? ''));
        if ($code === '') {
            $code = self::generateVariantCode($family, $label);
        } else {
            $code = strtoupper(preg_replace('/[^A-Za-z0-9_]/', '_', $code) ?? $code);
            if (Database::fetchColumn('SELECT id FROM design_variants WHERE code = ?', [$code])) {
                return ['ok' => false, 'message' => 'Mã biến thể "' . $code . '" đã tồn tại.'];
            }
        }

        $id = Database::insert('design_variants', [
            'font_id'     => $fontId ?: null,
            'label'       => mb_substr($label, 0, 150),
            'code'        => $code,
            'sample_text' => trim((string)($data['sample_text'] ?? '')) ?: 'Xin chào',
            'price'       => (float)($data['price'] ?? 0),
            'is_active'   => 1,
        ]);

        return ['ok' => true, 'message' => 'Đã tạo biến thể với mã <code>' . $code . '</code>', 'id' => $id, 'code' => $code];
    }

    public static function variants(): array
    {
        return Database::fetchAll(
            'SELECT v.*, f.family, f.file_path, f.weight FROM design_variants v
             LEFT JOIN design_fonts f ON f.id = v.font_id
             WHERE v.is_active = 1 ORDER BY v.id DESC'
        );
    }

    // ---------------- Dự án ----------------

    public static function createProject(int $userId, array $data): array
    {
        $category = (string)($data['category'] ?? 'poster');
        if (!isset(self::CATEGORIES[$category])) {
            $category = 'poster';
        }

        $schema = self::schema($category);
        $fields = [];
        foreach ($schema as $f) {
            $val = trim((string)($data[$f['key']] ?? ''));
            if (!empty($f['required']) && $val === '') {
                return ['ok' => false, 'message' => 'Vui lòng nhập: ' . $f['label']];
            }
            $fields[$f['key']] = $val;
        }

        $template = (string)($data['template'] ?? 'classic');
        $accent   = (string)($data['accent'] ?? '#f5c542');
        $variant  = (string)($data['variant_code'] ?? '');

        $price = (float)($data['price'] ?? 0);
        if ($price <= 0) {
            // Tính giá theo số biến thể dùng
            $price = count(array_filter($schema, fn($f) => $f['required'])) * 5000;
        }

        $id = Database::insert('design_projects', [
            'user_id'   => $userId,
            'name'      => mb_substr(trim((string)($data['name'] ?? ('Thiết kế ' . ucfirst($category)))), 0, 150),
            'category'  => $category,
            'template'  => $template,
            'canvas'    => json_encode([
                'fields'       => $fields,
                'variant_code' => $variant,
                'width'        => (int)($data['width'] ?? 1080),
                'height'       => (int)($data['height'] ?? 1080),
            ], JSON_UNESCAPED_UNICODE),
            'price'     => $price,
            'status'    => 'draft',
        ]);

        return ['ok' => true, 'message' => 'Đã tạo dự án thiết kế.', 'id' => $id];
    }

    public static function myProjects(int $userId): array
    {
        return Database::fetchAll(
            'SELECT * FROM design_projects WHERE user_id = ? ORDER BY id DESC LIMIT 100',
            [$userId]
        );
    }

    public static function findProject(int $id): ?array
    {
        return Database::fetch('SELECT * FROM design_projects WHERE id = ?', [$id]);
    }

    /**
     * Render 1 dự án thành SVG (preview + download).
     */
    public static function renderSvg(array $project): string
    {
        $canvas = json_decode((string)($project['canvas'] ?? '{}'), true) ?: [];
        $fields = (array)($canvas['fields'] ?? []);
        $w = max(200, (int)($canvas['width'] ?? 1080));
        $h = max(200, (int)($canvas['height'] ?? 1080));
        $accent = (string)($canvas['accent'] ?? '#f5c542');

        $variantCode = (string)($canvas['variant_code'] ?? '');
        $fontFamily  = 'Inter, system-ui, sans-serif';
        $fontWeight  = 700;
        if ($variantCode !== '') {
            $v = Database::fetch(
                'SELECT v.label, v.code, f.family, f.weight FROM design_variants v
                 LEFT JOIN design_fonts f ON f.id = v.font_id WHERE v.code = ?',
                [$variantCode]
            );
            if ($v) {
                $fontFamily = (string)($v['family'] ?: 'Inter') . ', system-ui, sans-serif';
                $fontWeight = (int)($v['weight'] ?: 700);
            }
        }

        $esc = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $lines = [];
        $y = (int)($h * 0.30);

        $lines[] = '<defs><linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">'
            . '<stop offset="0%" stop-color="#12102a"/><stop offset="100%" stop-color="#2a1550"/></linearGradient></defs>';
        $lines[] = '<rect width="' . $w . '" height="' . $h . '" fill="url(#bg)"/>';
        $lines[] = '<rect x="0" y="' . (int)($h * 0.78) . '" width="' . $w . '" height="' . (int)($h * 0.22) . '" fill="' . $esc($accent) . '"/>';

        if (!empty($fields['headline'])) {
            $lines[] = '<text x="' . (int)($w / 2) . '" y="' . $y . '" text-anchor="middle" font-family="' . $esc($fontFamily) . '" font-size="' . (int)($w * 0.075) . '" font-weight="' . $fontWeight . '" fill="#ffffff">' . $esc($fields['headline']) . '</text>';
            $y += (int)($w * 0.095);
        }
        if (!empty($fields['subline'])) {
            $lines[] = '<text x="' . (int)($w / 2) . '" y="' . $y . '" text-anchor="middle" font-family="' . $esc($fontFamily) . '" font-size="' . (int)($w * 0.038) . '" fill="#d8d2ea">' . $esc($fields['subline']) . '</text>';
            $y += (int)($w * 0.06);
        }
        if (!empty($fields['body'])) {
            $words = preg_split('/\s+/', $fields['body']) ?: [];
            $line = '';
            $out  = [];
            foreach ($words as $wd) {
                if (mb_strlen($line . ' ' . $wd) > 46) {
                    $out[] = trim($line);
                    $line  = $wd;
                } else {
                    $line .= ' ' . $wd;
                }
            }
            if ($line !== '') {
                $out[] = trim($line);
            }
            foreach (array_slice($out, 0, 4) as $i => $t) {
                $lines[] = '<text x="' . (int)($w / 2) . '" y="' . ($y + $i * (int)($w * 0.045)) . '" text-anchor="middle" font-family="' . $esc($fontFamily) . '" font-size="' . (int)($w * 0.028) . '" fill="#b9b1cf">' . $esc($t) . '</text>';
            }
        }
        if (!empty($fields['cta'])) {
            $bw = (int)($w * 0.28);
            $lines[] = '<rect x="' . (int)(($w - $bw) / 2) . '" y="' . (int)($h * 0.60) . '" width="' . $bw . '" height="' . (int)($w * 0.075) . '" rx="' . (int)($w * 0.038) . '" fill="#ffffff"/>';
            $lines[] = '<text x="' . (int)($w / 2) . '" y="' . (int)($h * 0.60 + $w * 0.052) . '" text-anchor="middle" font-family="' . $esc($fontFamily) . '" font-size="' . (int)($w * 0.032) . '" font-weight="700" fill="#14100a">' . $esc($fields['cta']) . '</text>';
        }
        if (!empty($fields['footer'])) {
            $lines[] = '<text x="' . (int)($w / 2) . '" y="' . (int)($h * 0.90) . '" text-anchor="middle" font-family="' . $esc($fontFamily) . '" font-size="' . (int)($w * 0.028) . '" fill="#14100a">' . $esc($fields['footer']) . '</text>';
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $w . '" height="' . $h . '" viewBox="0 0 ' . $w . ' ' . $h . '">' . implode('', $lines) . '</svg>';
    }
}
