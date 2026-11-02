-- =====================================================================
-- MIGRATION V4 — chuẩn hoá chế độ chơi, dịch vụ mới, dữ liệu mẫu
-- =====================================================================

-- ---------- 1. Trạng thái launch của game ----------
-- ready      : có URL chơi thật (demo hoặc credit)
-- pending    : đã có ảnh nhưng chưa tìm được URL chơi
-- unavailable: URL chỉ trỏ về trang marketing → không nhúng
ALTER TABLE `games`
    ADD COLUMN `launch_state` ENUM('ready','pending','unavailable') NOT NULL DEFAULT 'ready' AFTER `game_type`;

-- Cờ: game này thuộc nhóm demo-only (không đồng bộ ví)
ALTER TABLE `games`
    ADD COLUMN `play_mode` ENUM('demo','real') NOT NULL DEFAULT 'demo' AFTER `launch_state`;

-- ---------- 2. Cấu hình nhúng cho provider ----------
-- JSON trong providers.config:
--   embed_mode: 'none' | 'iframe' | 'external'
--   embed_note: ghi chú hiển thị cho người dùng
-- ---------- 3. Vị trí đặt biến thể font trong template ----------
ALTER TABLE `design_variants`
    ADD COLUMN `slot_key` VARCHAR(60) DEFAULT NULL AFTER `sample_text`,
    ADD COLUMN `slot_x` INT DEFAULT NULL AFTER `slot_key`,
    ADD COLUMN `slot_y` INT DEFAULT NULL AFTER `slot_x`,
    ADD COLUMN `slot_size` INT DEFAULT NULL AFTER `slot_y`;

-- ---------- 4. Công cụ / hạ tầng lưu trữ ----------
ALTER TABLE `service_catalog`
    ADD COLUMN `pricing_note` VARCHAR(255) DEFAULT NULL AFTER `description`;

ALTER TABLE `tools_registry`
    ADD COLUMN `tool_type` VARCHAR(40) NOT NULL DEFAULT 'utility' AFTER `group_name`,
    ADD COLUMN `config_json` JSON DEFAULT NULL AFTER `route`;

-- ---------- 5. Kế toán / POS: kho & phiếu ----------
ALTER TABLE `pos_orders`
    ADD COLUMN `warehouse_id` INT DEFAULT NULL AFTER `user_id`;

-- ---------- 6. Cài đặt & bảo trì ----------
ALTER TABLE `settings`
    ADD COLUMN `group_name` VARCHAR(40) NOT NULL DEFAULT 'general' AFTER `value`,
    ADD COLUMN `input_type` VARCHAR(30) NOT NULL DEFAULT 'text' AFTER `group_name`;

-- ---------- 7. Seed: danh mục dịch vụ ----------
INSERT INTO `service_catalog` (`code`,`name`,`group_name`,`description`,`icon`,`route`,`sort_order`) VALUES
('ai',        'Trung tâm AI',        'Dịch vụ số',   'Gộp mọi model AI vào 1 trang, hỗ trợ nhiều provider OpenAI-compatible.', 'fa-robot',           '/services/ai', 10),
('forum',     'Diễn đàn cộng đồng', 'Cộng đồng',    'Chia sẻ bài viết, hỏi đáp, đánh dấu lời giải.',                   'fa-comments',        '/services/forum', 11),
('cv',        'Tạo CV TopCV',        'Dịch vụ số',   'Tạo CV đa mẫu, xem trước và xuất PDF.',                              'fa-file-user',       '/services/cv', 12),
('media',     'Xem phim',            'Giải trí',     'Kho phim & trailer, phát trực tiếp trong web.',                        'fa-film',            '/services/media', 13),
('design',    'Thiết kế ảnh',        'Dịch vụ số',   'Nhập thông tin → tạo ảnh SVG, tải về ngay.',                          'fa-palette',         '/services/design', 14),
('infra',     'Domain &amp; Hosting', 'Hạ tầng',      'Mua domain, check domain còn hoạt động, hosting cPanel &amp; VPS.', 'fa-server',       '/services/infra', 15),
('bank',      'Tra cứu ngân hàng',   'Tài chính',    'Tra STK ngân hàng Việt Nam qua VietQR.',                                'fa-building-columns','/services/bank-lookup', 16),
('proxy',     'Kho proxy',           'Hạ tầng',      'HTTP, SOCKS5, residential, 4G mobile.',                                 'fa-network-wired',   '/services/proxy', 17),
('website',   'Thiết kế website',    'Dịch vụ số',   'Nạp tiền trước, nhận demo dự án, liên hệ Telegram admin.',          'fa-laptop-code',     '/services/website-builder', 18),
('pos',       'Phần mềm bán hàng',   'Kinh doanh',   'POS đa ngành nghề, kế toán, quản lý sinh viên (KiotViet-style).',     'fa-cash-register',  '/services/pos', 19),
('accounting', 'Phần mềm kế toán',   'Kinh doanh',   'Kế toán MISA-style, sao lưu dữ liệu tự động.',                        'fa-calculator',      '/services/accounting', 20),
('telegram',  'Telegram Mini App',   'Kênh bán hàng','Dựng mini app & bot Telegram riêng cho đối tác.',                      'fa-paper-plane',     '/services/telegram', 21),
('social',    'Dịch vụ mạng xã hội', 'Marketing',    'Buff tương tác, dịch vụ mạng xã hội.',                                 'fa-hashtag',         '/services/social', 22),
('sale',      'Dịch vụ sale',        'Marketing',    'Gửi tin nhắn hàng loạt qua proxy & tài khoản đã cấu hình.',           'fa-paper-plane',     '/services/sale', 23),
('payment-api','API thanh toán',     'Tài chính',    'Thuê API thanh toán & API ngân hàng (đang bảo trì).',                   'fa-money-bill-transfer','/services/payment-api', 24),
('ai-accounts','Tài khoản AI',        'Dịch vụ số',   'Bán tài khoản Cursor, ChatGPT, Claude, Gemini... qua API đối tác GenzShop, giao tài khoản tự động.', 'fa-robot', '/services/ai-accounts', 26),
('otp-rental', 'Thuê số nhận OTP',    'Dịch vụ số',   'Thuê số điện thoại tạm để nhận mã OTP (Facebook, Gmail, Shopee, TikTok...) qua API ViOTP, giao mã tự động.', 'fa-comment-sms', '/services/otp-rental', 27)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `group_name`=VALUES(`group_name`), `description`=VALUES(`description`), `icon`=VALUES(`icon`), `route`=VALUES(`route`), `sort_order`=VALUES(`sort_order`);

-- ---------- 8. Seed: kho công cụ tiện ích ----------
INSERT INTO `tools_registry` (`code`,`name`,`group_name`,`tool_type`,`description`,`icon`,`route`,`sort_order`) VALUES
('check-domain',  'Check domain',        'Kỹ thuật',  'network',   'Tra cứu DNS/RDAP xem domain còn hoạt động hay không.', 'fa-globe',        '/services/infra?domain=', 1),
('qr-code',       'Tạo QR ngân hàng',  'Tài chính', 'utility',   'Sinh mã QR VietQR để nạp tiền nhanh.',              'fa-qrcode',       '/wallet/deposit', 2),
('bank-lookup',   'Tra cứu STK',        'Tài chính', 'lookup',    'Tra cứu chủ tài khoản từ số tài khoản ngân hàng.', 'fa-building-columns','/services/bank-lookup', 3),
('md5-sha',       'Hash chuỗi',         'Kỹ thuật',  'security',  'Tạo hash MD5/SHA-1/SHA-256 cho mật khẩu & token.',  'fa-fingerprint',  '/tools/hash', 4),
('json-format',   'Format JSON',        'Kỹ thuật',  'utility',   'Format & validate JSON.',                            'fa-code',         '/tools/json', 5),
('password-gen',  'Sinh mật khẩu mạnh', 'Bảo mật',   'security',  'Sinh mật khẩu ngẫu nhiên độ mạnh cao.',              'fa-key',          '/tools/password', 6),
('unit-convert',  'Đổi đơn vị',         'Tiện ích',  'utility',   'Quy đổi đơn vị tiền tệ, dung lượng, kích thước.',    'fa-right-left',   '/tools/convert', 7),
('uuid',          'Sinh UUID',          'Kỹ thuật',  'utility',   'Sinh UUID v4 ngẫu nhiên.',                          'fa-fingerprint',  '/tools/uuid', 8),
('ip-info',       'Tra cứu IP',         'Kỹ thuật',  'network',   'Thông tin IP, quốc gia, ASN.',                        'fa-location-dot', '/tools/ip', 9),
('whois',         'Tra cứu WHOIS',      'Kỹ thuật',  'network',   'Tra cứu chủ sở hữu tên miền.',                     'fa-address-book', '/tools/whois', 10),
('seo-check',     'Kiểm tra SEO',       'Kỹ thuật',  'seo',       'Kiểm tra meta, canonical, sitemap của website.',   'fa-chart-line',   '/tools/seo', 11),
('speed-test',    'Đo tốc độ site',     'Kỹ thuật',  'network',   'Đo thời gian phản hồi của website.',               'fa-gauge-high',   '/tools/speed', 12),
('base64',        'Encode/Decode',      'Kỹ thuật',  'utility',   'Mã hoá / giải mã Base64.',                          'fa-shuffle',      '/tools/base64', 13)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `group_name`=VALUES(`group_name`), `tool_type`=VALUES(`tool_type`), `description`=VALUES(`description`), `icon`=VALUES(`icon`), `route`=VALUES(`route`), `sort_order`=VALUES(`sort_order`);

-- ---------- 9. Seed: model AI ----------
INSERT INTO `ai_models` (`provider`,`model`,`label`,`endpoint`,`api_key_ref`,`context_k`,`price_in`,`price_out`,`capabilities`,`sort_order`) VALUES
('deepseek', 'deepseek-chat',             'DeepSeek Chat',           'https://api.deepseek.com/chat/completions', 'DEEPSEEK_API_KEY', 64, 0.00027, 0.0011, '["chat","code","vision"]', 1),
('deepseek', 'deepseek-reasoner',         'DeepSeek Reasoner',       'https://api.deepseek.com/chat/completions', 'DEEPSEEK_API_KEY', 64, 0.00055, 0.00219,'["chat","reasoning"]', 2),
('gemini',   'gemini-2.0-flash',          'Gemini 2.0 Flash',        'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions', 'GEMINI_API_KEY', 1000, 0.0001, 0.0004, '["chat","vision","long-context"]', 3),
('gemini',   'gemini-2.0-pro',            'Gemini 2.0 Pro',          'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions', 'GEMINI_API_KEY', 1000, 0.00125, 0.005, '["chat","reasoning","long-context"]', 4),
('together', 'meta-llama-3.1-70b',        'Llama 3.1 70B (Together)', 'https://api.together.xyz/v1/chat/completions', 'TOGETHER_API_KEY', 128, 0.00088, 0.00088, '["chat","code"]', 5),
('together', 'qwen2.5-72b',               'Qwen 2.5 72B (Together)', 'https://api.together.xyz/v1/chat/completions', 'TOGETHER_API_KEY', 128, 0.0012, 0.0012, '["chat","code","vision"]', 6),
('ollama',   'llama3.1',                  'Ollama Llama 3.1',        'http://127.0.0.1:11434/v1/chat/completions', '', 128, 0, 0, '["chat","local"]', 7),
('lmstudio', 'local-model',               'LM Studio (local)',       'http://127.0.0.1:1234/v1/chat/completions', '', 32, 0, 0, '["chat","local"]', 8),
('openai',   'gpt-4o-mini',               'GPT-4o mini',             'https://api.openai.com/v1/chat/completions', 'OPENAI_API_KEY', 128, 0.00015, 0.0006, '["chat","vision"]', 9)
ON DUPLICATE KEY UPDATE `label`=VALUES(`label`), `endpoint`=VALUES(`endpoint`), `context_k`=VALUES(`context_k`), `price_in`=VALUES(`price_in`), `price_out`=VALUES(`price_out`), `capabilities`=VALUES(`capabilities`), `sort_order`=VALUES(`sort_order`);

-- ---------- 10. Seed: mẫu biến thể font ----------
INSERT INTO `design_fonts` (`family`,`file_path`,`weight`,`format`) VALUES
('Inter', 'assets/fonts/inter-700.ttf', '700', 'ttf'),
('Be Vietnam Pro', 'assets/fonts/bevietnampro-800.ttf', '800', 'ttf')
ON DUPLICATE KEY UPDATE `file_path`=VALUES(`file_path`);

INSERT INTO `design_variants` (`font_id`,`label`,`code`,`sample_text`,`price`,`slot_key`) VALUES
((SELECT id FROM design_fonts WHERE family='Inter' LIMIT 1),           'Họ Nguyễn Đậm', 'HO_NGUYEN_DAM', 'NGUYỄN VĂN A', 15000, 'headline'),
((SELECT id FROM design_fonts WHERE family='Be Vietnam Pro' LIMIT 1), 'Họ Nguyễn Đậm', 'HO_NGUYEN_DAM_BV', 'NGUYỄN VĂN A', 25000, 'headline'),
((SELECT id FROM design_fonts WHERE family='Inter' LIMIT 1),           'Chữ Thường',   'CHU_THUONG',     'Xin chào', 8000, 'body')
ON DUPLICATE KEY UPDATE `label`=VALUES(`label`), `price`=VALUES(`price`), `slot_key`=VALUES(`slot_key`);

-- ---------- 11. Seed: Telegram mini app ----------
INSERT INTO `telegram_apps` (`name`,`bot_username`,`app_url`,`description`) VALUES
('Casino Portal Bot', 'casino_portal_bot', 'https://t.me/casino_portal_bot', 'Bot chính thức hỗ trợ nạp/rút và vào sảnh.'),
('Công cụ AI', 'casino_ai_bot', 'https://t.me/casino_ai_bot', 'Mini app gộp toàn bộ model AI.'),
('POS Mini', 'casino_pos_bot', 'https://t.me/casino_pos_bot', 'Mini app bán hàng cho đối tác.')
ON DUPLICATE KEY UPDATE `app_url`=VALUES(`app_url`), `description`=VALUES(`description`);

-- ---------- 12. Seed: gói hosting / VPS ----------
INSERT INTO `infra_plans` (`kind`,`name`,`provider`,`spec`,`price_month`,`price_year`,`features`) VALUES
('hosting','cPanel Starter','Local','1 CPU / 2GB RAM / 25GB NVMe', 190000, 1900000, '["cPanel","SSL","Email","Backup 7 ngày"]'),
('hosting','cPanel Business','Local','2 CPU / 4GB RAM / 50GB NVMe', 350000, 3500000, '["cPanel","SSL","Email","Backup 30 ngày","Staging"]'),
('vps','VPS K1','Local','1 vCPU / 2GB RAM / 40GB SSD', 299000, 2990000, '["Ubuntu 22.04","1 IPv4","Snapshot","24h backup"]'),
('vps','VPS K2','Local','2 vCPU / 4GB RAM / 80GB SSD', 549000, 5490000, '["Ubuntu 22.04","2 IPv4","Snapshot","DDoS guard"]'),
('vps','VPS K3','Local','4 vCPU / 8GB RAM / 160GB SSD', 999000, 9990000, '["Ubuntu 22.04","4 IPv4","DDoS guard","Backup 2h"]')
ON DUPLICATE KEY UPDATE `price_month`=VALUES(`price_month`), `price_year`=VALUES(`price_year`), `features`=VALUES(`features`);

-- ---------- 13. Seed: mẫu phim ----------
INSERT INTO `media_items` (`title`,`slug`,`type`,`poster`,`trailer_url`,`description`,`year`,`rating`,`duration`,`genres`,`is_vip`) VALUES
('Cuộc Đua Vũ Trụ','cuoc-dua-vu-tru','movie','https://image.tmdb.org/t/p/w500/8Vt6mWEReuy4Of61Lnj5Xj704m8.jpg','https://www.youtube.com/embed/5ZCmTm8FE6U','Hành trình xuyên không gian đầy kịch tính.',2024,8.1,'2h 28m','["Sci-Fi","Action"]',0),
('Thành Phố Mưa','thanh-pho-mua','movie','https://image.tmdb.org/t/p/w500/qhb1qOilapbapxWQn9jtRCMwXJF.jpg','https://www.youtube.com/embed/5ZCmTm8FE6U','Bí mật của một thành phố không ngủ.',2023,7.4,'2h 05m','["Crime","Drama"]',1),
('Hành Trình Của Gió','hanh-trinh-cua-gio','trailer','https://image.tmdb.org/t/p/w500/4Y1WNkd88JXmGQEW2HSx2HqX1H.jpg','https://www.youtube.com/embed/5ZCmTm8FE6U','Trailer phim hành trình chiếu tháng này.',2025,7.9,'1h 52m','["Adventure"]',0)
ON DUPLICATE KEY UPDATE `poster`=VALUES(`poster`), `rating`=VALUES(`rating`), `description`=VALUES(`description`);

-- ---------- 14. Seed: sản phẩm POS ----------
INSERT INTO `pos_products` (`code`,`name`,`category`,`price`,`cost`,`stock`,`unit`) VALUES
('food-001','Phở Bò Tái','food',55000,32000,100,'tô'),
('food-002','Bún Chả','food',45000,26000,100,'tô'),
('food-003','Cà phê Đen','coffee',18000,7000,500,'ly'),
('food-004','Bạc xỉu','coffee',25000,11000,500,'ly'),
('food-005','Trà đào cam sả','drink',35000,15000,300,'ly'),
('ret-001','Khăn tắm Lotus','retail',35000,22000,200,'hộp'),
('ret-002','Sữa tươi Vinamilk','retail',28000,19000,300,'hộp'),
('ret-003','Dầu gội Clear','retail',65000,45000,150,'chai'),
('spa-001','Massage 60 phút','spa',450000,180000,20,'buổi'),
('spa-002','Chăm sóc da mặt','spa',320000,120000,25,'buổi'),
('pha-001','Paracetamol 500mg','pharmacy',5000,2500,1000,'vỉ'),
('pha-002','Siro ho Bổ sung','pharmacy',85000,55000,80,'chai')
ON DUPLICATE KEY UPDATE `price`=VALUES(`price`), `cost`=VALUES(`cost`), `stock`=VALUES(`stock`);

-- ---------- 15. Seed: gói proxy ----------
INSERT INTO `proxy_listings` (`user_id`,`title`,`kind`,`country`,`city`,`isp`,`duration_days`,`threads`,`price`,`stock`,`endpoint`) VALUES
(1,'Proxy HTTP Việt Nam tốc độ cao','http','Việt Nam','Hà Nội','VNPT',30,50,150000,-1,'proxy.vn:8080'),
(1,'Proxy SOCKS5 băng thông rộng','socks5','Việt Nam','TP.HCM','FPT',30,100,220000,-1,'proxy.vn:1080'),
(1,'Proxy Residential trọn đời','residential','Việt Nam','Toàn quốc','ISP khác nhau',90,10,890000,-1,'res.vn:8000'),
(1,'Proxy 4G Mobile dùng cho tài khoản','mobile','Việt Nam','Toàn quốc','Viettel',30,5,320000,-1,'m4g.vn:9000')
ON DUPLICATE KEY UPDATE `price`=VALUES(`price`), `stock`=VALUES(`stock`);

-- ---------- 16. Seed: settings mới ----------
INSERT INTO `settings` (`name`,`value`,`group_name`,`input_type`) VALUES
('maintenance_mode','0','system','switch'),
('maintenance_message','Hệ thống đang bảo trì. Vui lòng quay lại sau ít phút.','system','textarea'),
('maintenance_allow_admin','1','system','switch'),
('ddos_protection','1','security','switch'),
('rate_limit_enabled','1','security','switch'),
('fraud_detection','1','security','switch'),
('p2p_auto_release','1','finance','switch'),
('p2p_auto_release_hours','24','finance','number'),
('p2p_fee_percent','1.0','finance','number'),
('anti_debug_enabled','1','security','switch'),
('telegram_bot_token','','integration','text'),
('telegram_admin_chat_id','','integration','text'),
('backup_enabled','1','system','switch'),
('backup_interval_hours','1','system','number'),
('storage_driver','local','system','select'),
('driver_endpoint','','system','text'),
('driver_secret','','system','password')
ON DUPLICATE KEY UPDATE `group_name`=VALUES(`group_name`), `input_type`=VALUES(`input_type`);
