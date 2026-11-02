<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Router;
use App\Services\AdService;
use App\Services\PackageService;

/**
 * Controller giao diện công khai.
 */
class HomeController
{
    public function index(): void
    {
        $featured = Database::fetchAll(            'SELECT g.*, p.name AS provider_name, p.code AS provider_code
             FROM games g JOIN providers p ON p.id = g.provider_id
             WHERE g.status = "active" AND g.is_featured = 1
             ORDER BY g.sort_order DESC LIMIT 12'
        );

        $hot = Database::fetchAll(
            'SELECT g.*, p.name AS provider_name, p.code AS provider_code
             FROM games g JOIN providers p ON p.id = g.provider_id
             WHERE g.status = "active" AND g.is_hot = 1
             ORDER BY g.sort_order DESC LIMIT 12'
        );

        $new = Database::fetchAll(
            'SELECT g.*, p.name AS provider_name, p.code AS provider_code
             FROM games g JOIN providers p ON p.id = g.provider_id
             WHERE g.status = "active" AND g.is_new = 1
             ORDER BY g.created_at DESC LIMIT 12'
        );

        $providers = Database::fetchAll(
            'SELECT p.*, COUNT(g.id) AS game_count
             FROM providers p LEFT JOIN games g ON g.provider_id = p.id AND g.status = "active"
             WHERE p.status = "active" GROUP BY p.id ORDER BY p.sort_order, p.name'
        );

        $stats = [
            'games'     => (int)Database::fetchColumn('SELECT COUNT(*) FROM games WHERE status = "active"'),
            'providers' => count($providers),
            'members'   => (int)Database::fetchColumn('SELECT COUNT(*) FROM users WHERE role = "user"'),
        ];

        // Banner quảng cáo theo vị trí — gom tất cả vào 1 carousel duy nhất
        $adsTop     = AdService::forSlot('home_top', 8);
        $adsMid     = AdService::forSlot('home_mid', 8);
        $adsSidebar = AdService::forSlot('sidebar', 3);

        // Gom quảng cáo + slide giới thiệu thành 1 danh sách carousel
        $slides = [];
        foreach (array_merge($adsTop, $adsMid) as $ad) {
            $slides[] = [
                'type'  => 'ad',
                'ad'    => $ad,
                'title' => (string)($ad['title'] ?? ''),
                'desc'  => (string)($ad['description'] ?? ''),
                'image' => media_url((string)($ad['image_url'] ?? '')),
                'video' => media_url((string)($ad['video_url'] ?? '')),
                'link'  => (string)($ad['link_url'] ?? ''),
            ];
        }
        // Chỉ hiển thị carousel khi có quảng cáo thật — không dùng slide giới thiệu mặc định.

        // Danh mục dịch vụ (nhóm theo mảng chức năng)
        $serviceCategories = self::serviceCategories();

        // ===== Showcase Deno Casino: banner + marquee + danh sách bàn =====
        $denoBanners = self::denoSetting('deno_banners');
        $denoMarquee = self::denoSetting('deno_marquee');
        $denoTables  = self::denoTables();

        // ===== Showcase PG SOFT: dựng 1:1 theo pgsoft.com (12 section) =====
        $pgAbout     = self::pgAbout();
        $pgFeatures  = self::pgFeatures();
        $pgLicensing = self::pgLicensing();
        $pgGallery   = self::pgGallery();
        $pgGames     = (int)($stats['games'] ?? 0);
        $pgProviders = (int)($stats['providers'] ?? 0);

        // ===== Showcase PG SOFT — dữ liệu mở rộng lấy nguyên i18n từ pgsoft.com =====
        $pgSlogan     = self::pgSlogan();
        $pgData       = self::pgData();
        $pgBackOffice = self::pgBackOffice();
        $pgPartners   = self::pgPartners();
        $pgVision     = self::pgVision();

        // Tiêu đề / mô tả giống hệt pgsoft.com
        $pgAboutTitle    = 'VỀ PG SOFT®';
        $pgAboutDesc     = 'PG SOFT®, còn gọi là Pocket Games Soft, là thương hiệu thành lập năm 2015 và đã trở thành '
                         . 'một tên tuổi vững mạnh, uy tín trong lĩnh vực trò chơi trực tuyến. Với danh mục game số '
                         . 'được công nhận rộng rãi, PG SOFT® nổi tiếng nhờ thương hiệu, phong cách và lối chơi đặc trưng.';
        $pgFeaturesTitle = 'TÍNH NĂNG CỦA CHÚNG TÔI';
        $pgFeaturesDesc  = 'Tại PG SOFT®, ai cũng có điều gì đó dành riêng cho mình. Đội ngũ của chúng tôi đã phát triển '
                         . 'nhiều tính năng đa dạng để đáp ứng mọi kiểu người chơi, đảm bảo trải nghiệm chơi game thú vị cho tất cả.';

        view('home', compact(
            'featured', 'hot', 'new', 'providers', 'stats',
            'adsTop', 'adsMid', 'adsSidebar', 'slides', 'serviceCategories',
            'denoBanners', 'denoMarquee', 'denoTables',
            'pgAbout', 'pgFeatures', 'pgLicensing', 'pgGallery', 'pgGames', 'pgProviders',
            'pgSlogan', 'pgData', 'pgBackOffice', 'pgPartners', 'pgVision'
        ));
    }

    /**
     * Đọc 1 setting dạng JSON (dùng cho dữ liệu lobby Deno).
     */
    private static function denoSetting(string $key): array
    {
        $raw = (string)Database::fetchColumn(
            'SELECT value FROM settings WHERE name = ? LIMIT 1',
            [$key]
        );

        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Danh sách bàn Deno Casino (kèm category để gom nhóm).
     */
    private static function denoTables(): array
    {
        $rows = Database::fetchAll(
            'SELECT g.slug, g.name, g.provider_game_id, g.game_config
             FROM games g JOIN providers p ON p.id = g.provider_id
             WHERE p.code = "deno-casino" AND g.status = "active"
             ORDER BY g.game_type, g.name'
        );

        $out = [];
        foreach ($rows as $r) {
            $cfg = json_decode((string)($r['game_config'] ?? '{}'), true);
            $cfg = is_array($cfg) ? $cfg : [];

            $out[] = [
                'slug'     => (string)$r['slug'],
                'name'     => (string)$r['name'],
                'table_id' => (string)($r['provider_game_id'] ?? ''),
                'category' => (string)($cfg['category'] ?? 'baccarat'),
            ];
        }

        return $out;
    }

    /**
     * 6 ô "About" của PG SOFT — dựng theo pgsoft.com (tiêu đề 2 dòng, icon, ảnh hover, màu nhấn).
     */
    private static function pgAbout(): array
    {
        return [
            [
                'line1' => 'ĐỒ HỌA',
                'line2' => 'TUYỆT ĐẸP',
                'icon'  => 'iconDesign@2x.c0ace3e.png',
                'bg'    => 'Design@2x.edb788a.jpg',
                'color' => 'rgb(255, 110, 0)',
                'text'  => 'Kết hợp các vũ trụ tưởng tượng, trò chơi năng động và lối chơi chưa từng có, các trò chơi '
                         . 'của chúng tôi vượt qua ranh giới của thế giới ảo bằng cách tạo ra lối chơi sáng tạo và tác '
                         . 'phẩm nghệ thuật tầm cỡ AAA. Chúng tôi hứa hẹn mang đến những pha hành động kịch tính cho '
                         . 'người chơi của mình thông qua các trò chơi được chế tạo khéo léo.',
            ],
            [
                'line1' => 'ĐỘI NGŨ',
                'line2' => 'TÀI NĂNG',
                'icon'  => 'iconTeam@2x.d3ba97f.png',
                'bg'    => 'Team@2x.017c9d4.jpg',
                'color' => 'rgb(255, 185, 26)',
                'text'  => 'PG SOFT<sup>®</sup> là studio quốc tế từng đạt giải thưởng nhà thiết kế, nghệ sĩ, nhà làm '
                         . 'phim hoạt hình và nhà sản xuất tạo ra nội dung cho các cái tên lớn trong điện ảnh và trò '
                         . 'chơi điện tử. Làm việc song song với các sinh viên toán học tốt nghiệp Đại học Oxford và '
                         . 'Cambridge, mỗi sản phẩm là một phản ánh năng lực khả năng của đội ngũ chúng tôi.',
            ],
            [
                'line1' => 'MÃ',
                'line2' => 'KIỂM TRA NGHIÊM NGẶT',
                'icon'  => 'iconCode@2x.4051542.png',
                'bg'    => 'Code@2x.0df527e.jpg',
                'color' => 'rgb(100, 20, 230)',
                'text'  => 'Đội ngũ nhà phát triển của PG SOFT<sup>®</sup> khám phá ra một ranh giới mới trong lĩnh vực '
                         . 'kỹ thuật số bằng cách đi sâu vào một nhiệm vụ khó là sắp xếp lại hàng triệu mã lệnh. Chúng tôi '
                         . 'viết lại các quy tắc thuật toán và chạy nhiều lần lặp để mang đến các sự kiện điện ảnh nhanh, '
                         . 'không gián đoạn và nhập vai trò trong lối chơi, mang đến cho các game thủ trải nghiệm hoàn '
                         . 'chỉnh trên các thiết bị trình duyệt, iOS, Android và Windows trong một thế giới trò chơi mở rộng.',
            ],
            [
                'line1' => 'ÂM NHẠC',
                'line2' => 'ĐỈNH CAO',
                'icon'  => 'iconMusic@2x.53902a9.png',
                'bg'    => 'Music@2x.296e619.jpg',
                'color' => 'rgb(20, 230, 145)',
                'text'  => 'PG SOFT<sup>®</sup> hiểu tầm quan trọng của hiệu ứng âm thanh sống động trong việc tạo ra '
                         . 'các trải nghiệm chơi game hấp dẫn. Đội ngũ kỹ sư âm thanh, nhà thiết kế âm thanh và một tập '
                         . 'thể nhà sản xuất âm nhạc của chúng tôi làm việc liên tục để tạo ra một pha trộn âm nhạc sáng '
                         . 'tạo phù hợp với lối chơi và phong cách nghệ thuật.',
            ],
            [
                'line1' => 'HỖ TRỢ',
                'line2' => '24/7',
                'icon'  => 'iconSupport@2x.ac6cb61.png',
                'bg'    => 'Support@2x.392104a.jpg',
                'color' => 'rgb(255, 25, 40)',
                'text'  => 'Ưu tiên hàng đầu của chúng tôi là khách hàng, chúng tôi đối xử với khách hàng như những '
                         . 'người bạn tốt nhất. Đội ngũ dịch vụ khách hàng thân thiện và am hiểu của PG SOFT<sup>®</sup> '
                         . 'làm việc liên tục để cung cấp sự hỗ trợ 24 giờ mỗi ngày, 7 ngày một tuần và 365 ngày một năm '
                         . 'để đảm bảo trải nghiệm trò chơi mượt mà cho tất cả người chơi.',
            ],
            [
                'line1' => 'THỊ TRƯỜNG',
                'line2' => 'RỘNG LỚN',
                'icon'  => 'iconMarket@2x.78d544a.png',
                'bg'    => 'Market@2x.770b69b.jpg',
                'color' => 'rgb(25, 190, 230)',
                'text'  => 'Đội ngũ sáng tạo của chúng tôi chuyển các thuật ngữ kỹ thuật phức tạp thành nội dung '
                         . 'ngắn gọn dễ hiểu, truyền đạt bản sắc thương hiệu của PG SOFT<sup>®</sup> một cách trực quan '
                         . 'để phát triển khách hàng thành những người ủng hộ và biến người dùng thành người hâm mộ. '
                         . 'Kết quả là, một loạt các trò chơi khác nhau và lối chơi độc đáo của chúng tôi thu hút một thị '
                         . 'trường rộng lớn trên toàn thế giới.',
            ],
        ];
    }

    /**
     * Các tab tính năng của PG SOFT (giống mục FEATURES trên pgsoft.com).
     */
    private static function pgFeatures(): array
    {
        return [
            [
                'title'  => 'Cộng Đồng',
                'screen' => 'screen5_social@2x.28914a4.png',
                'text'   => 'Chia sẻ là quan tâm. Để tăng mức độ gắn bó và giữ chân người chơi, PG SOFT® là đơn vị '
                          . 'tiên phong ra mắt Hệ thống xã hội trong ngành game di động. Người chơi có thể trò chuyện '
                          . 'nhóm, gửi biểu tượng cảm xúc và chia sẻ thành tích của mình.',
            ],
            [
                'title'  => 'Giải Đấu',
                'screen' => 'screen5_tournament@2x.50637b0.png',
                'text'   => 'Giải đấu là tính năng độc đáo nơi người chơi tụ họp trên đấu trường cạnh tranh để giành '
                          . 'phần thưởng hấp dẫn. Đây cũng là dịch vụ hỗ trợ đối tác tổ chức hoạt động khuyến mãi '
                          . 'và tăng lượng người chơi trung thành.',
            ],
            [
                'title'  => 'Ván Chơi Miễn Phí & Phần Thưởng',
                'screen' => 'screen5_freegame@2x.b17e435.png',
                'text'   => 'Tính năng Free Game và Bonus cho phép đối tác tùy chỉnh linh hoạt theo từng cấp độ người '
                          . 'chơi, hỗ trợ một hoặc nhiều game cùng lúc, giúp giảm chi phí marketing và xây dựng '
                          . 'kế hoạch khuyến mãi theo nhu cầu thị trường.',
            ],
            [
                'title'  => 'Bot Thông Minh',
                'screen' => 'screen5_smartbot@2x.9af8fed.png',
                'text'   => 'Smartbot là công nghệ độc quyền cho phép người dùng chuyển ứng dụng mà vẫn giữ game '
                          . 'chạy nền. Tính năng tự ngắt kết nối / tạm dừng giúp người chơi dễ dàng quản lý thắng thua.',
            ],
            [
                'title'  => 'Sảnh Chơi',
                'screen' => 'screen5_lobby@2x.3144e0b.png',
                'text'   => 'PG Lobby chạy trên trình duyệt, iOS và Android, tích hợp dễ dàng trên MacOS / Windows. '
                          . 'Giao diện đẹp mắt bổ trợ thiết kế tối giản, điều hướng trực quan với thời gian thi đấu, '
                          . 'giải thưởng và luật chơi hiển thị nhanh chóng.',
            ],
        ];
    }

    /**
     * Chứng chỉ & giấy phép (2 tab: Licensing / Certification).
     */
    private static function pgLicensing(): array
    {
        return [
            [
                'title' => 'Cấp Phép',
                'items' => [
                    [
                        'logo' => 'MGA_logo.22d72a1.png',
                        'text' => 'MGA là cơ quan quản lý độc lập duy nhất chịu trách nhiệm quản lý toàn bộ hoạt động '
                                . 'cờ bạc tại Malta, đảm bảo khung pháp lý và cấu trúc doanh nghiệp hiệu quả '
                                . 'và toàn diện cho ngành.',
                    ],
                ],
            ],
            [
                'title' => 'Chứng Chỉ',
                'items' => [
                    [
                        'logo' => 'BMM@2x.9e52a4e.png',
                        'text' => 'BMM Testlabs là phòng thí nghiệm kiểm định game độc lập, thành lập ngày 27/11/1981. '
                                . 'Được công nhận ISO 17025 IT và 17020, hiện có 14 văn phòng tại 13 quốc gia.',
                    ],
                    [
                        'logo' => 'GA@2x.4d0e3a2.png',
                        'text' => 'Gaming Associates là cơ sở kiểm địm độc lập được công nhận quốc tế, chứng nhận ISO 9001, '
                                . 'ISO/IEC 17025 và PCI QSA, được các cơ quan quản lý trên toàn thế giới công nhận.',
                    ],
                ],
            ],
        ];
    }

    /**
     * Game PG SOFT hiển thị dạng lưới cuộn ngang.
     */
    private static function pgGallery(): array
    {
        $rows = Database::fetchAll(
            'SELECT g.slug, g.name, g.icon
             FROM games g JOIN providers p ON p.id = g.provider_id
             WHERE p.code = "pgsoft" AND g.status = "active"
             ORDER BY g.is_featured DESC, g.sort_order DESC, g.name'
        );

        // Ưu tiên game có icon nằm sẵn trong project (CDN cũ đã chết)
        $local = [];
        $remote = [];
        foreach ($rows as $r) {
            $icon = (string)($r['icon'] ?? '');
            if ($icon === '') {
                continue;
            }

            $item = [
                'slug' => (string)$r['slug'],
                'name' => (string)$r['name'],
                'icon' => media_url($icon),
            ];

            if (preg_match('#^https?://#i', $icon)) {
                $remote[] = $item;
            } else {
                $local[] = $item;
            }
        }

        return array_slice(array_merge($local, $remote), 0, 24);
    }

    /**
     * Slogan hero của pgsoft.com — chữ lớn + thanh "Vuốt Lên" chạy animation.
     * Text theo đúng bản dịch tiếng Việt trong app.js của pgsoft.com.
     */
    private static function pgSlogan(): array
    {
        return [
            'large'  => 'SỰ KHÁC BIỆT TẠO NÊN<br>SỰ KHÁC BIỆT',
            'medium' => 'SỰ KHÁC BIỆT TẠO NÊN<br>SỰ KHÁC BIỆT',
            'small'  => 'SỰ KHÁC BIỆT <br>TẠO NÊN<br>SỰ KHÁC BIỆT',
            'xs'     => 'SỰ KHÁC BIỆT <br>TẠO NÊN<br>SỰ KHÁC BIỆT',
            'swipeUp' => 'Vuốt Lên',
        ];
    }

    /**
     * Section "Data" của pgsoft.com — quả địa cầu + 8 ô thống kê
     * (103 quốc gia, 1063 nhà điều hành, 4 game bàn, 171 slot, 173 tiền tệ,
     *  44 ngôn ngữ, 5 nền tảng) — số liệu lấy từ homeSettings trên pgsoft.com.
     */
    private static function pgData(): array
    {
        return [
            'desc' => 'Là nhà thay đổi trò chơi trong ngành trò chơi di động, PG SOFT<sup>®</sup> đã tạo tác động lớn '
                    . 'trong thế giới trò chơi kể từ khi thành lập vào năm 2015.',

            // 2 ô đếm số lớn trên đầu (Games / Languages)
            'counterGames'    => 173,
            'counterLanguages' => 44,

            'countries' => 103,
            'operators' => 1063,
            'poker'     => 4,
            'slots'     => 171,
            'currencies' => 173,
            'languages' => 44,
            'devices'   => 5,

            // Nhãn chính xác từ i18n tiếng Việt
            'countriesLabel' => 'KHÁCH HÀNG TẠI {key} QUỐC GIA',
            'operatorsLabel' => 'NHÀ ĐIỀU HÀNH TOÀN CẦU',
            'pokerLabel'     => "PG SOFT\nTRÒ CHƠI BÀN",
            'slotsLabel'     => "PG SOFT\nTRÒ QUAY SỐ",
            'currencyLabel'  => 'HIỆN ĐƯỢC HỖ TRỢ',
            'langLabel'      => 'NGÔN NGỮ ĐƯỢC HỖ TRỢ',
            'deviceLabel'    => 'HỖ TRỢ TẤT CẢ THIẾT BỊ',
            'hoursLabel'     => '<span class="number">24 GIỜ</span><span class="hour"></span>',
            'hoursTitle'     => 'TRUY CẬP NHÀ ĐIỀU HÀNH',
            'hoursDesc'      => 'Truy cập 24/7, đảm bảo các hoạt động kinh doanh của nhà điều hành vận hành '
                             . 'mượt mà trong nhiều năm.',

            // Biểu đồ tròn (pie) — màu + style tam giác chính xác từ bundle gốc
            'areas' => [
                ['code' => 'Asia',    'label' => 'CHÂU Á',         'pct' => '42%', 'color' => '#FF3D6A'],
                ['code' => 'Europe',  'label' => 'CHÂU ÂU',        'pct' => '36%', 'color' => '#F59F21'],
                ['code' => 'Africa',  'label' => 'CHÂU PHI',       'pct' => '11%', 'color' => '#F2DE51'],
                ['code' => 'America', 'label' => 'CHÂU MỸ',        'pct' => '7%',  'color' => '#5DB7FE'],
                ['code' => 'Oceania', 'label' => 'CHÂU ĐẠI DƯƠNG', 'pct' => '4%',  'color' => '#A638E6'],
            ],

            // Testimonial nhà điều hành (3 logo xoay)
            'operatorTestimonials' => [
                [
                    'logo' => 'partner_logo_Leander@2x.80d0f0d.png',
                    'desc' => 'Leander là công ty trò chơi độc lập đã phát triển máy chủ trò chơi từ xa Đoạt Giải '
                            . 'thưởng B2B eGR (RGS), LeGa.',
                ],
                [
                    'logo' => 'partner_logo_comeon@2x.6eae9a5.png',
                    'desc' => 'ComeOn! là trang web cược thể thao và casino trực tuyến đoạt giải thưởng cung cấp '
                            . 'trải nghiệm trò chơi casino trực tuyến độc nhất vô nhị và tin cậy cho tất cả người chơi.',
                ],
                [
                    'logo' => 'partner_logo_betconstruct@2x.623101b.png',
                    'desc' => 'BetConstruct là nhà cung cấp công nghệ và dịch vụ đoạt giải thưởng toàn cầu trong '
                            . 'ngành trò chơi trực tuyến và trên đất liền.',
                ],
            ],

            // 6 đồng tiền
            'coins' => [
                'coin_1@2x.738cf9c.png', 'coin_2@2x.7b45445.png', 'coin_3@2x.c1e2574.png',
                'coin_4@2x.06777ff.png', 'coin_5@2x.2c2d1b0.png', 'coin_6@2x.b88dc25.png',
            ],

            // 5 nền tảng
            'deviceIcons' => [
                'android@2x.8613b44.png', 'html5@2x.7346a79.png', 'ios@2x.3376057.png',
                'mac@2x.b5b3235.png', 'win@2x.4204fe4.png',
            ],

            // 4 icon game bàn (1 bị ẩn width 0 như bản gốc)
            'pokerIcons' => [
                'american_blackjack.94e0775.png',
                'baccarat_deluxe.20bee7b.png',
                'european_blackjack.25f8426.png',
                'american_blackjack.94e0775.png',
            ],

            // 21 ngôn ngữ — chia 2 cột như bản gốc
            'langLeft' => [
                'Chinese@2x.png', 'Danish@2x.png', 'Dutch@2x.png', 'Finnish@2x.png',
                'French@2x.png', 'German@2x.png', 'Indonesian@2x.png', 'Ireland@2x.png',
                'Japanese@2x.png', 'Korean@2x.png',
            ],
            'langRight' => [
                'Norwegian@2x.png', 'Polish@2x.png', 'Portuguese@2x.png', 'Romanian@2x.png',
                'Russian@2x.png', 'Spanish@2x.png', 'Swedish@2x.png', 'Thai@2x.png',
                'Turkish@2x.png', 'UK@2x.png', 'Vietnamese@2x.png',
            ],

            // 16 icon slot (bản gốc dùng 16 icon xếp lưới 4x4)
            'slotIcons' => [
                '1.997caf6.png', '2.322e9f9.png', '3.6ad2a4c.png', '4.5873b14.png',
                '5.838e624.png', '6.ad44bdb.png', '7.589555b.png', '8.38fdbd9.png',
                '9.2a9a788.png', '10.7cb88f1.png', '11.0134575.png', '12.4759114.png',
                '13.67d6bb8.png', '14.9902f0f.png', '15.afa9947.png', '16.098ef65.png',
            ],

            'pieImage'    => 'pie.b01dc8b.jpg',
            'earthVideo'  => 'earth_animation.ef40696.mp4',
            'rocketVideo' => 'rocket_animation.bb0f5f5.mp4',
            'rocketImage' => 'rocket.f5c07ee.jpg',
        ];
    }

    /**
     * Section "Back Office" của pgsoft.com — 4 chỉ số nổi bật + animation lottie.
     */
    private static function pgBackOffice(): array
    {
        return [
            'title' => 'BACK OFFICE',
            'desc'  => 'PG SOFT<sup>®</sup> tin tưởng chắc chắn rằng tất cả các quyết định tiếp thị tuyệt vời đều được '
                    . 'thực hiện dựa trên dữ liệu định lượng, đó là lý do tại sao chúng tôi đã khởi xướng PG SOFT<sup>®</sup> '
                    . 'Back Office, một nền tảng được khởi xướng để cung cấp cho các nhà điều hành trên toàn cầu dữ liệu '
                    . 'thời gian thực chi tiết và toàn diện để tăng doanh thu và tăng cường biên lợi nhuận.',
            'items' => [
                ['code' => 'delay',   'num' => '0',   'title' => '0 Độ Trễ Truy Vấn Dữ Liệu',
                 'desc' => 'PG SOFT<sup>®</sup> Back Office có thể cung cấp dữ liệu thời gian thực của người chơi và doanh thu với độ trễ zero (0) đối với tất cả nhà điều hành.'],
                ['code' => 'product', 'num' => '1',   'title' => '1 Trung Tâm Quản lý Một Cửa',
                 'desc' => 'PG SOFT<sup>®</sup> Back Office được trang bị nhiều công cụ và hệ thống quản lý trò chơi có thể hỗ trợ các nhà điều hành phát triển doanh nghiệp của họ dễ dàng.'],
                ['code' => 'language', 'num' => '21', 'title' => 'Hỗ Trợ 21 Ngôn Ngữ',
                 'desc' => 'PG SOFT<sup>®</sup> Back Office cho phép các nhà điều hành thêm nhiều ngôn ngữ vào các trò chơi của họ để mở rộng thị trường trên toàn cầu.'],
                ['code' => 'secure',  'num' => '100', 'title' => '100% Bảo Mật và Ổn Định',
                 'desc' => 'PG SOFT<sup>®</sup> Back Office được phát triển bảo mật và ổn định nhất có thể vì thế các nhà điều hành sẽ có truy cập đến các hệ thống của họ 24/7 và 365 ngày trong suốt năm.'],
            ],
            'lottie' => 'backoffice_lottie.json',
        ];
    }

    /**
     * Section "Partners" — logo đối tác chính thức của PG SOFT.
     */
    private static function pgPartners(): array
    {
        return [
            'title' => 'ĐỐI TÁC CỦA CHÚNG TÔI',
            'desc'  => 'PG SOFT<sup>®</sup> sát cánh cùng các đối tác chuyên nghiệp để cung cấp cho các game thủ các trò '
                    . 'chơi thú vị, mới mẻ và rất được mong đợi.<br><br>Chúng tôi cam kết xây dựng quan hệ đối tác trên toàn '
                    . 'cầu trong khi tuân thủ sự giám sát của các cơ quan Thẩm Quyền và Cấp Phép Châu Âu. Hãy tham gia '
                    . 'cùng chúng tôi nếu bạn cùng tầm nhìn cách mạng hóa ứng dụng trò chơi di động!',
            'logos' => [
                'partner_logo_betconstruct@2x.623101b.png',
                'partner_logo_Leander@2x.80d0f0d.png',
                'partner_logo_Nektan@2x.5969e7f.png',
                'partner_logo_comeon@2x.6eae9a5.png',
                'partner_logo_betB2B.627b06c.svg',
                'partner_logo_888casino_logo.41a928f.svg',
                'partner_logo_bet365.5bde483.png',
                'partner_logo_bethard@2x.b2a14ad.png',
                'partner_logo_Betsson@2x.484674d.png',
                'partner_logo_Casumo@2x.de4d7cf.png',
                'partner_logo_everymatrix@2x.de121ab.png',
                'partner_logo_gig@2x.543886c.png',
                'partner_logo_GVC@2x.a20b0e8.png',
                'partner_logo_Interwetten@2x.eb5ebcc.png',
                'partner_logo_kindred@2x.4f87bea.png',
                'partner_logo_leovegas@2x.4f4a5f7.png',
                'partner_logo_parimatch@2x.016fb11.png',
                'partner_logo_Pariplay@2x.9f00759.png',
                'partner_logo_RelaxGamingGroup@2x.6a9a806.png',
                'partner_logo_sbobet@2x.649bf36.png',
                'partner_logo_softgamings@2x.ba7bc64.png',
                'partner_logo_softswiss@2x.c59f917.png',
                'partner_logo_Videoslots@2x.290d706.png',
                'partner_logo_188Bet@2x.eb1f963.png',
            ],
        ];
    }

    /**
     * Section "Vision" — sóng line video + mô tả tầm nhìn.
     */
    private static function pgVision(): array
    {
        return [
            'title' => 'TẦM NHÌN CỦA CHÚNG TÔI',
            'desc'  => 'PG SOFT<sup>®</sup> ra đời để cách mạng hóa ứng dụng trò chơi di động bằng cách tận dụng sự '
                    . 'phong phú các giải pháp trò chơi được thiết kế riêng của chúng tôi. Các trò chơi của chúng tôi '
                    . 'là hình ảnh thu nhỏ của thẩm mỹ đáng chú ý đến kinh ngạc và lối chơi hấp dẫn sẽ mang đến sự giải '
                    . 'trí trò chơi độc nhất vô nhị cho tất cả người chơi trò quay số di động.',
            'video' => 'line-video.2db4fa0.mp4',
        ];
    }

    /**
     * Danh sách dịch vụ theo danh mục — mỗi danh mục 1 card, bấm vào mở drawer.
     */
    private static function serviceCategories(): array
    {
        return [
            [
                'key'   => 'finance',
                'title' => 'Ví & Tài chính',
                'icon'  => 'fa-wallet',
                'items' => [
                    ['name' => 'Ví của tôi',      'desc' => 'Số dư, lịch sử, đồng bộ 2 chiều', 'url' => '/wallet',          'icon' => 'fa-wallet'],
                    ['name' => 'Nạp tiền',        'desc' => 'QR ngân hàng & thẻ cào, tự động cộng', 'url' => '/wallet/deposit',  'icon' => 'fa-circle-plus'],
                    ['name' => 'Rút tiền',        'desc' => 'Duyệt nhanh 24/7',                    'url' => '/wallet/withdraw', 'icon' => 'fa-money-bill-transfer'],
                    ['name' => 'Chuyển tiền',     'desc' => 'Chuyển nội bộ siêu tốc',                'url' => '/wallet/transfer', 'icon' => 'fa-right-left'],
                    ['name' => 'Bank ảo',         'desc' => 'Thuê tài khoản ngân hàng ảo, có logo chính thức', 'url' => '/services/virtual-bank', 'icon' => 'fa-vault'],
                    ['name' => 'Tra cứu ngân hàng', 'desc' => 'VietQR tra cứu tài khoản ngân hàng', 'url' => '/services/bank-lookup', 'icon' => 'fa-building-columns'],
                    ['name' => 'Sàn P2P',         'desc' => 'Mua bán tiền & tài khoản an toàn',     'url' => '/p2p',             'icon' => 'fa-handshake-angle'],
                    ['name' => 'Phần mềm kế toán', 'desc' => 'Sổ sách, báo cáo, hóa đơn',         'url' => '/services/accounting', 'icon' => 'fa-calculator'],
                ],
            ],
            [
                'key'   => 'entertainment',
                'title' => 'Giải trí & Sảnh Game',
                'icon'  => 'fa-gamepad',
                'items' => [
                    ['name' => 'Sảnh game',   'desc' => 'PG Soft, JILI, Pragmatic… có logo chuẩn', 'url' => '/lobbies', 'icon' => 'fa-crown'],
                    ['name' => 'Kho game',     'desc' => 'Toàn bộ game của mọi nhà phát hành',   'url' => '/games',   'icon' => 'fa-layer-group'],
                    ['name' => 'Gói chơi',     'desc' => 'Mua gói trải nghiệm giá ưu đãi',        'url' => '/packages', 'icon' => 'fa-box-open'],
                    ['name' => 'Chợ tài nguyên', 'desc' => 'Mua bán tài nguyên số',               'url' => '/market', 'icon' => 'fa-store'],
                    ['name' => 'Xem phim',     'desc' => 'Kho phim & theo dõi',                    'url' => '/services/media', 'icon' => 'fa-film'],
                    ['name' => 'Đục UI/UX web', 'desc' => 'Quét CSS, ảnh, sprite từ web casino',  'url' => '/services/ui-scraper', 'icon' => 'fa-wand-magic-sparkles'],
                    ['name' => 'Copy 1-1 website', 'desc' => 'Tải toàn bộ website về làm của mình', 'url' => '/services/site-clone', 'icon' => 'fa-clone'],
                    ['name' => 'Tạo website A-Z', 'desc' => 'Sinh website hoàn chỉnh từ mẫu', 'url' => '/services/site-auto', 'icon' => 'fa-wand-sparkles'],
                ],
            ],
            [
                'key'   => 'accounts',
                'title' => 'Tài khoản & Số',
                'icon'  => 'fa-user-secret',
                'items' => [
                    ['name' => 'Tài khoản AI',   'desc' => 'Cursor, ChatGPT, Claude, Gemini…', 'url' => '/services/ai-accounts', 'icon' => 'fa-robot'],
                    ['name' => 'Tài khoản & Nick', 'desc' => 'Nick, clone tài khoản MXH giao tự động', 'url' => '/services/vuavia-accounts', 'icon' => 'fa-user-secret'],
                    ['name' => 'Thuê số OTP',    'desc' => 'Facebook, Zalo, TikTok, Shopee…',   'url' => '/services/otp-rental', 'icon' => 'fa-comment-sms'],
                    ['name' => 'Trung tâm AI',   'desc' => 'Chat với model AI ngay trên web',    'url' => '/services/ai', 'icon' => 'fa-robot'],
                    ['name' => 'Dịch vụ Sale',   'desc' => 'Gửi tin nhắn hàng loạt, chống spam', 'url' => '/services/sale', 'icon' => 'fa-paper-plane'],
                    ['name' => 'Social & Buff',  'desc' => 'Like, view, follower chất lượng',    'url' => '/services/social', 'icon' => 'fa-share-nodes'],
                    ['name' => 'Kho proxy',      'desc' => 'Proxy xoay nhiều quốc gia',           'url' => '/services/proxy', 'icon' => 'fa-network-wired'],
                    ['name' => 'Check số điện thoại', 'desc' => 'Tên, CCCD, địa chỉ, link Facebook', 'url' => '/services/phone-check', 'icon' => 'fa-mobile-screen-button'],
                    ['name' => 'Gửi tin nhắn SMS', 'desc' => 'Gửi hàng loạt, tự động theo cron', 'url' => '/services/sms', 'icon' => 'fa-comment-dots'],
                ],
            ],
            [
                'key'   => 'business',
                'title' => 'Kinh doanh & Hạ tầng',
                'icon'  => 'fa-briefcase',
                'items' => [
                    ['name' => 'Thiết kế website', 'desc' => 'Website chuyên nghiệp theo yêu cầu', 'url' => '/services/website-builder', 'icon' => 'fa-laptop-code'],
                    ['name' => 'Phần mềm bán hàng', 'desc' => 'POS bán hàng tại cửa hàng',         'url' => '/services/pos', 'icon' => 'fa-cash-register'],
                    ['name' => 'Tạo CV',           'desc' => 'CV đẹp, xuất PDF',                 'url' => '/services/cv', 'icon' => 'fa-file-user'],
                    ['name' => 'Thiết kế ảnh',     'desc' => 'Ảnh bìa, logo, banner',            'url' => '/services/design', 'icon' => 'fa-palette'],
                    ['name' => 'Domain & Hosting', 'desc' => 'Tên miền .vn/.com + hosting',       'url' => '/services/infra', 'icon' => 'fa-server'],
                    ['name' => 'Thuê VPS VMware',  'desc' => 'VPS hiệu năng cao, quản lý trực tiếp', 'url' => '/services/vps', 'icon' => 'fa-server'],
                    ['name' => 'VPS StoreVPS',     'desc' => 'Gói VPS StoreVPS giá rẻ',          'url' => '/services/vps-storevps', 'icon' => 'fa-layer-group'],
                    ['name' => 'API thanh toán',   'desc' => 'Tích hợp cổng thanh toán tự động', 'url' => '/services/payment-api', 'icon' => 'fa-money-bill-transfer'],
                ],
            ],
            [
                'key'   => 'platform',
                'title' => 'Nền tảng & Quyền lợi',
                'icon'  => 'fa-gem',
                'items' => [
                    ['name' => 'VIP 60 cấp',   'desc' => 'Badge 3D 60 cấp, ăn chia & quyền lợi', 'url' => '/vip', 'icon' => 'fa-gem'],
                    ['name' => 'Website con',  'desc' => 'Tạo website bán hàng của riêng bạn',   'url' => '/child-sites', 'icon' => 'fa-sitemap'],
                    ['name' => 'API của tôi', 'desc' => 'Mua gói API key cho hệ thống của bạn', 'url' => '/api-keys', 'icon' => 'fa-key'],
                    ['name' => 'Tài liệu API', 'desc' => 'Hướng dẫn tích hợp + OpenAPI',       'url' => '/api/docs', 'icon' => 'fa-book'],
                    ['name' => 'Giới thiệu',    'desc' => 'Giới thiệu & nhận hoa hồng',          'url' => '/services/forum', 'icon' => 'fa-comments'],
                    ['name' => 'Công cụ',       'desc' => 'Hash, JSON, Base64, IP, WHOIS…',     'url' => '/tools', 'icon' => 'fa-screwdriver-wrench'],
                ],
            ],
        ];
    }
}
