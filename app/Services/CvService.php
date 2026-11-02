<?php
namespace App\Services;

use App\Core\Database;

/**
 * CV Builder — tạo hồ sơ CV trực tuyến, xuất HTML/print-ready.
 */
class CvService
{
    public const TEMPLATES = [
        'modern'   => ['name' => 'Hiện đại',   'icon' => 'fa-bolt',        'accent' => '#f5c542', 'layout' => 'sidebar'],
        'classic'  => ['name' => 'Cổ điển',    'icon' => 'fa-book-open',   'accent' => '#2563eb', 'layout' => 'header'],
        'minimal'  => ['name' => 'Tối giản',   'icon' => 'fa-minus',       'accent' => '#111827', 'layout' => 'clean'],
        'creative' => ['name' => 'Sáng tạo',   'icon' => 'fa-palette',     'accent' => '#db2777', 'layout' => 'bold'],
        'tech'     => ['name' => 'Công nghệ',  'icon' => 'fa-code',        'accent' => '#059669', 'layout' => 'sidebar'],
        'elegant'  => ['name' => 'Thanh lịch', 'icon' => 'fa-feather',     'accent' => '#7c3aed', 'layout' => 'centered'],
        'executive'=> ['name' => 'Điều hành',  'icon' => 'fa-user-tie',    'accent' => '#0f172a', 'layout' => 'header'],
        'compact'  => ['name' => 'Gọn gàng',   'icon' => 'fa-compress',    'accent' => '#ea580c', 'layout' => 'two-col'],
        'aurora'   => ['name' => 'Aurora',     'icon' => 'fa-star',        'accent' => '#06b6d4', 'layout' => 'sidebar'],
        'mono'     => ['name' => 'Đơn sắc',    'icon' => 'fa-circle-half', 'accent' => '#334155', 'layout' => 'clean'],
        'rose'     => ['name' => 'Hồng',       'icon' => 'fa-heart',       'accent' => '#e11d48', 'layout' => 'centered'],
        'emerald'  => ['name' => 'Ngọc lục',   'icon' => 'fa-leaf',        'accent' => '#047857', 'layout' => 'two-col'],
    ];

    /** Các mục có thể bật/tắt & sắp xếp lại trong CV */
    public const SECTIONS = [
        'summary'    => 'Tóm tắt',
        'experience' => 'Kinh nghiệm',
        'education'  => 'Học vấn',
        'skills'     => 'Kỹ năng',
        'languages'  => 'Ngôn ngữ',
        'projects'   => 'Dự án',
        'certificates'=> 'Chứng chỉ',
        'awards'     => 'Giải thưởng',
        'references' => 'Người tham chiếu',
        'custom'     => 'Mục tùy chỉnh',
    ];

    /** Font chữ dùng cho CV */
    public const FONTS = [
        'Inter'          => 'Inter (hiện đại)',
        'Georgia'        => 'Georgia (cổ điển)',
        'Roboto'         => 'Roboto (gọn)',
        'Playfair'       => 'Playfair (cao cấp)',
        'Times'          => 'Times New Roman',
        'Verdana'        => 'Verdana (dễ đọc)',
    ];

    public static function templates(): array
    {
        return self::TEMPLATES;
    }

    public static function sections(): array
    {
        return self::SECTIONS;
    }

    public static function fonts(): array
    {
        return self::FONTS;
    }

    public static function myProfiles(int $userId): array
    {
        return Database::fetchAll(
            'SELECT * FROM cv_profiles WHERE user_id = ? ORDER BY id DESC LIMIT 50',
            [$userId]
        );
    }

    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM cv_profiles WHERE id = ?', [$id]);
    }

    public static function save(int $userId, array $data): array
    {
        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') {
            $title = 'CV của tôi';
        }

        $template = (string)($data['template'] ?? 'modern');
        if (!isset(self::TEMPLATES[$template])) {
            $template = 'modern';
        }
        $accent = (string)($data['accent'] ?? self::TEMPLATES[$template]['accent']);

        $font = trim((string)($data['font_family'] ?? ''));
        if ($font === '' || !isset(self::FONTS[$font])) {
            $font = 'Inter';
        }

        // Các mục được bật & thứ tự hiển thị
        $sections = self::parseSections((string)($data['sections'] ?? ''));

        // Liên kết (website / github / linkedin ...)
        $links = self::parseLinks((string)($data['links'] ?? ''));

        $row = [
            'user_id'     => $userId,
            'title'       => mb_substr($title, 0, 150),
            'avatar'      => $data['avatar'] ?? null,
            'full_name'   => trim((string)($data['full_name'] ?? '')) ?: null,
            'headline'    => trim((string)($data['headline'] ?? '')) ?: null,
            'email'       => trim((string)($data['email'] ?? '')) ?: null,
            'phone'       => trim((string)($data['phone'] ?? '')) ?: null,
            'address'     => trim((string)($data['address'] ?? '')) ?: null,
            'links'       => $links !== [] ? json_encode($links, JSON_UNESCAPED_UNICODE) : null,
            'summary'     => trim((string)($data['summary'] ?? '')) ?: null,
            'experience'  => json_encode(self::parseLines((string)($data['experience'] ?? '')), JSON_UNESCAPED_UNICODE),
            'education'   => json_encode(self::parseLines((string)($data['education'] ?? '')), JSON_UNESCAPED_UNICODE),
            'skills'      => json_encode(self::parseList((string)($data['skills'] ?? '')), JSON_UNESCAPED_UNICODE),
            'languages'   => json_encode(self::parseList((string)($data['languages'] ?? '')), JSON_UNESCAPED_UNICODE),
            'sections'    => json_encode($sections, JSON_UNESCAPED_UNICODE),
            'template'    => $template,
            'accent'      => $accent,
            'font_family' => $font,
            'is_active'   => 1,
        ];

        $id = (int)($data['id'] ?? 0);
        if ($id > 0) {
            $exists = Database::fetch('SELECT id FROM cv_profiles WHERE id = ? AND user_id = ?', [$id, $userId]);
            if ($exists) {
                // Không ghi đè avatar nếu không upload ảnh mới
                if (empty($row['avatar'])) {
                    unset($row['avatar']);
                }
                Database::update('cv_profiles', $row, 'id = :id', ['id' => $id]);
                return ['ok' => true, 'message' => 'Đã cập nhật CV.', 'id' => $id];
            }
        }

        if (empty($row['avatar'])) {
            unset($row['avatar']);
        }
        $newId = Database::insert('cv_profiles', $row);
        return ['ok' => true, 'message' => 'Đã tạo CV.', 'id' => $newId];
    }

    /**
     * Danh sách mục được bật, theo thứ tự người dùng chọn.
     */
    private static function parseSections(string $raw): array
    {
        $out = [];
        foreach (preg_split('/[,;\r\n]+/', $raw) ?: [] as $key) {
            $key = trim($key);
            if ($key !== '' && isset(self::SECTIONS[$key]) && !in_array($key, $out, true)) {
                $out[] = $key;
            }
        }
        // Luôn có tối thiểu tóm tắt + kinh nghiệm
        foreach (['summary', 'experience'] as $def) {
            if (!in_array($def, $out, true)) {
                $out[] = $def;
            }
        }
        return $out;
    }

    /**
     * Liên kết dạng "nhãn | url" mỗi dòng.
     */
    private static function parseLinks(string $text): array
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line));
            $label = $parts[0] ?? '';
            $url   = $parts[1] ?? '';
            if ($label === '' && $url === '') {
                continue;
            }
            $out[] = ['label' => $label, 'url' => $url];
        }
        return $out;
    }

    /**
     * Mỗi dòng "Vị trí | Công ty | 2020-2023 | Mô tả" → 1 mục kinh nghiệm.
     */
    private static function parseLines(string $text): array
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line));
            $out[] = [
                'position' => $parts[0] ?? '',
                'company'  => $parts[1] ?? '',
                'period'   => $parts[2] ?? '',
                'detail'   => $parts[3] ?? '',
            ];
        }
        return $out;
    }

    private static function parseList(string $text): array
    {
        $out = [];
        foreach (preg_split('/[,;\r\n]+/', $text) ?: [] as $item) {
            $item = trim($item);
            if ($item !== '') {
                $out[] = $item;
            }
        }
        return $out;
    }

    /**
     * Render CV ra HTML (dùng cho xem trước & in).
     */
    public static function renderHtml(array $cv): string
    {
        $exp = json_decode((string)($cv['experience'] ?? '[]'), true) ?: [];
        $edu = json_decode((string)($cv['education'] ?? '[]'), true) ?: [];
        $skl = json_decode((string)($cv['skills'] ?? '[]'), true) ?: [];
        $lng = json_decode((string)($cv['languages'] ?? '[]'), true) ?: [];
        $lnk = json_decode((string)($cv['links'] ?? '[]'), true) ?: [];
        $sec = json_decode((string)($cv['sections'] ?? '[]'), true) ?: [];

        $esc = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        $accent = $esc($cv['accent'] ?? '#f5c542');
        $font   = $esc($cv['font_family'] ?? 'Inter');
        $tpl    = (string)($cv['template'] ?? 'modern');
        $layout = self::TEMPLATES[$tpl]['layout'] ?? 'sidebar';

        if (!$sec) {
            $sec = ['summary', 'experience', 'education', 'skills', 'languages'];
        }

        $h = [];
        $h[] = '<div class="cv-doc cv-layout-' . $esc($layout) . ' cv-tpl-' . $esc($tpl) . '"'
             . ' style="--cv-accent:' . $accent . ';--cv-font:' . $font . '">';

        // ---- Header ----
        $h[] = '<header class="cv-head">';
        if (!empty($cv['avatar'])) {
            $h[] = '<img class="cv-avatar" src="' . $esc($cv['avatar']) . '" alt="Avatar">';
        }
        $h[] = '<div class="cv-head-main">';
        $h[] = '<div class="cv-name">' . $esc($cv['full_name'] ?? '') . '</div>';
        if (!empty($cv['headline'])) {
            $h[] = '<div class="cv-headline">' . $esc($cv['headline']) . '</div>';
        }
        $h[] = '<div class="cv-title">' . $esc($cv['title'] ?? '') . '</div>';

        $contact = array_filter([$cv['email'] ?? '', $cv['phone'] ?? '', $cv['address'] ?? '']);
        if ($contact) {
            $h[] = '<div class="cv-contact">' . $esc(implode(' · ', $contact)) . '</div>';
        }
        if ($lnk) {
            $h[] = '<div class="cv-links">';
            foreach ($lnk as $l) {
                $label = $l['label'] ?? ($l['url'] ?? '');
                $url   = $l['url'] ?? '';
                if ($label === '' && $url === '') {
                    continue;
                }
                $h[] = '<span class="cv-link-item">' . $esc($label);
                if ($url !== '') {
                    $h[] = ' <a href="' . $esc($url) . '" target="_blank" rel="noopener">↗</a>';
                }
                $h[] = '</span>';
            }
            $h[] = '</div>';
        }
        $h[] = '</div></header>';

        // ---- Các mục theo thứ tự người dùng chọn ----
        foreach ($sec as $key) {
            switch ($key) {
                case 'summary':
                    if (!empty($cv['summary'])) {
                        $h[] = '<section class="cv-sec"><h3>Tóm tắt</h3><p>' . nl2br($esc($cv['summary'])) . '</p></section>';
                    }
                    break;

                case 'experience':
                    if ($exp) {
                        $h[] = '<section class="cv-sec"><h3>Kinh nghiệm</h3>';
                        foreach ($exp as $e) {
                            $h[] = self::renderItem($e);
                        }
                        $h[] = '</section>';
                    }
                    break;

                case 'education':
                    if ($edu) {
                        $h[] = '<section class="cv-sec"><h3>Học vấn</h3>';
                        foreach ($edu as $e) {
                            $h[] = self::renderItem($e);
                        }
                        $h[] = '</section>';
                    }
                    break;

                case 'skills':
                    if ($skl) {
                        $h[] = '<section class="cv-sec"><h3>Kỹ năng</h3><div class="cv-chips">';
                        foreach ($skl as $s) {
                            $h[] = '<span class="cv-chip">' . $esc($s) . '</span>';
                        }
                        $h[] = '</div></section>';
                    }
                    break;

                case 'languages':
                    if ($lng) {
                        $h[] = '<section class="cv-sec"><h3>Ngôn ngữ</h3><div class="cv-chips">';
                        foreach ($lng as $s) {
                            $h[] = '<span class="cv-chip cv-chip-alt">' . $esc($s) . '</span>';
                        }
                        $h[] = '</div></section>';
                    }
                    break;
            }
        }

        $h[] = '</div>';
        return implode('', $h);
    }

    /** Render 1 mục (kinh nghiệm / học vấn) */
    private static function renderItem(array $e): string
    {
        $esc = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        $out = '<div class="cv-item"><div class="cv-item-head"><strong>' . $esc($e['position'] ?? '') . '</strong>';
        if (!empty($e['company'])) {
            $out .= ' <span class="cv-org">' . $esc($e['company']) . '</span>';
        }
        if (!empty($e['period'])) {
            $out .= ' <span class="cv-period">' . $esc($e['period']) . '</span>';
        }
        $out .= '</div>';
        if (!empty($e['detail'])) {
            $out .= '<div class="cv-item-body">' . nl2br($esc($e['detail'])) . '</div>';
        }
        $out .= '</div>';
        return $out;
    }
}
