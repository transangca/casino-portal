-- =====================================================================
-- MIGRATION V16 — Dịch vụ mới: check SĐT, copy 1-1 web, tạo web A-Z,
--                spam SMS, bảng giá dịch vụ, tiện ích
--   1. service_prices     : bảng giá dịch vụ (admin quản lý, ăn chênh)
--   2. phone_checks       : nhật ký tra cứu thông tin qua số điện thoại
--   3. site_clones        : dự án copy 1-1 trang web
--   4. site_auto_builds   : dự án tạo website tự động A-Z
--   5. sms_campaigns      : chiến dịch spam SMS / tin nhắn điện thoại
--   6. sms_targets        : danh sách số điện thoại nhận
--   7. sms_logs           : log gửi tin nhắn
--   8. scraper_jobs       : job quét UI/UX (chạy nền, có tiến trình)
-- =====================================================================

-- ---------- 1. Bảng giá dịch vụ ----------
CREATE TABLE IF NOT EXISTS `service_prices` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`        VARCHAR(60) NOT NULL,
  `name`        VARCHAR(150) NOT NULL,
  `group_name`  VARCHAR(50) NOT NULL DEFAULT 'Dịch vụ',
  `price`       DECIMAL(18,2) NOT NULL DEFAULT 0,
  `unit`        VARCHAR(40) NOT NULL DEFAULT 'lần',
  `description` VARCHAR(255) DEFAULT NULL,
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order`  INT NOT NULL DEFAULT 0,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sp_code` (`code`),
  KEY `idx_sp_group` (`group_name`,`is_active`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 2. Nhật ký tra cứu số điện thoại ----------
CREATE TABLE IF NOT EXISTS `phone_checks` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED DEFAULT NULL,
  `phone`      VARCHAR(20) NOT NULL,
  `carrier`    VARCHAR(80) DEFAULT NULL,
  `full_name`  VARCHAR(150) DEFAULT NULL,
  `id_card`    VARCHAR(40) DEFAULT NULL,
  `address`    VARCHAR(255) DEFAULT NULL,
  `fb_url`     VARCHAR(255) DEFAULT NULL,
  `source`     VARCHAR(40) NOT NULL DEFAULT 'internal',
  `status`     ENUM('found','partial','not_found','error') NOT NULL DEFAULT 'found',
  `cost`       DECIMAL(18,2) NOT NULL DEFAULT 0,
  `balance_after` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `result_json` JSON DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pc_user` (`user_id`,`id`),
  KEY `idx_pc_phone` (`phone`),
  KEY `idx_pc_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 3. Dự án copy 1-1 trang web ----------
CREATE TABLE IF NOT EXISTS `site_clones` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED DEFAULT NULL,
  `source_url`  VARCHAR(500) NOT NULL,
  `host`        VARCHAR(120) DEFAULT NULL,
  `title`       VARCHAR(255) DEFAULT NULL,
  `status`      ENUM('pending','scanning','done','failed') NOT NULL DEFAULT 'pending',
  `pages_total` INT NOT NULL DEFAULT 0,
  `pages_done`  INT NOT NULL DEFAULT 0,
  `assets_total` INT NOT NULL DEFAULT 0,
  `zip_size`    BIGINT NOT NULL DEFAULT 0,
  `price`       DECIMAL(18,2) NOT NULL DEFAULT 0,
  `error_msg`   VARCHAR(255) DEFAULT NULL,
  `result_json` JSON DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sc_user` (`user_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 4. Dự án tạo website tự động A-Z ----------
CREATE TABLE IF NOT EXISTS `site_auto_builds` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED DEFAULT NULL,
  `name`        VARCHAR(150) NOT NULL,
  `domain`      VARCHAR(150) DEFAULT NULL,
  `category`    VARCHAR(60) NOT NULL DEFAULT 'casino',
  `template`    VARCHAR(60) NOT NULL DEFAULT 'casino',
  `pages`       JSON DEFAULT NULL,
  `keywords`    VARCHAR(255) DEFAULT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `theme`       VARCHAR(40) NOT NULL DEFAULT 'dark',
  `status`      ENUM('pending','building','done','failed') NOT NULL DEFAULT 'pending',
  `zip_path`    VARCHAR(500) DEFAULT NULL,
  `zip_size`    BIGINT NOT NULL DEFAULT 0,
  `price`       DECIMAL(18,2) NOT NULL DEFAULT 0,
  `error_msg`   VARCHAR(255) DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sab_user` (`user_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 5. Chiến dịch spam SMS ----------
CREATE TABLE IF NOT EXISTS `sms_campaigns` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED DEFAULT NULL,
  `name`         VARCHAR(150) NOT NULL,
  `message`      TEXT NOT NULL,
  `sender_id`    INT UNSIGNED DEFAULT NULL,
  `provider`     VARCHAR(60) NOT NULL DEFAULT 'sdt',
  `speed`        ENUM('slow','normal','fast') NOT NULL DEFAULT 'normal',
  `total`        INT NOT NULL DEFAULT 0,
  `sent`         INT NOT NULL DEFAULT 0,
  `failed`       INT NOT NULL DEFAULT 0,
  `daily_limit`  INT NOT NULL DEFAULT 200,
  `status`       ENUM('draft','running','paused','done','stopped') NOT NULL DEFAULT 'draft',
  `cost`         DECIMAL(18,2) NOT NULL DEFAULT 0,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_smc_user` (`user_id`,`id`),
  KEY `idx_smc_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 6. Số điện thoại nhận ----------
CREATE TABLE IF NOT EXISTS `sms_targets` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `campaign_id` INT UNSIGNED NOT NULL,
  `phone`       VARCHAR(20) NOT NULL,
  `name`        VARCHAR(120) DEFAULT NULL,
  `status`      ENUM('pending','sent','failed','blocked') NOT NULL DEFAULT 'pending',
  `error_msg`   VARCHAR(255) DEFAULT NULL,
  `sent_at`     DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_st_camp` (`campaign_id`,`status`),
  KEY `idx_st_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 7. Log gửi tin nhắn ----------
CREATE TABLE IF NOT EXISTS `sms_logs` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `campaign_id` INT UNSIGNED DEFAULT NULL,
  `target_id`   INT UNSIGNED DEFAULT NULL,
  `phone`       VARCHAR(20) NOT NULL,
  `message`     TEXT NOT NULL,
  `status`      ENUM('sent','failed') NOT NULL DEFAULT 'sent',
  `error_msg`   VARCHAR(255) DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sl_camp` (`campaign_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 8. Job quét UI/UX (nền, có tiến trình) ----------
CREATE TABLE IF NOT EXISTS `scraper_jobs` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED DEFAULT NULL,
  `url`         VARCHAR(500) NOT NULL,
  `host`        VARCHAR(120) DEFAULT NULL,
  `status`      ENUM('queued','running','done','failed') NOT NULL DEFAULT 'queued',
  `step`        VARCHAR(120) NOT NULL DEFAULT 'Đang chờ',
  `progress`    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `total_assets` INT NOT NULL DEFAULT 0,
  `done_assets`  INT NOT NULL DEFAULT 0,
  `counts`      JSON DEFAULT NULL,
  `error_msg`   VARCHAR(255) DEFAULT NULL,
  `zip_path`    VARCHAR(500) DEFAULT NULL,
  `result_json` JSON DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sj_user` (`user_id`,`id`),
  KEY `idx_sj_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Seed bảng giá ----------
INSERT INTO `service_prices` (`code`,`name`,`group_name`,`price`,`unit`,`description`,`sort_order`) VALUES
('ui-scraper',      'Đục UI/UX web',            'Kỹ thuật',      5000,   'lần',   'Quét toàn bộ CSS, ảnh, sprite, font của 1 website.', 10),
('site-clone',      'Copy 1-1 trang web',        'Kỹ thuật',      15000,  'lần',   'Tải toàn bộ index.html + assets, liên kết lại website hoàn chỉnh.', 20),
('site-auto',       'Tạo website tự động A-Z',  'Kỹ thuật',      99000,  'trang', 'Sinh website hoàn chỉnh: HTML, CSS, JS, SEO, responsive.', 30),
('phone-check',     'Check thông tin SĐT',       'Tra cứu',      3000,   'lần',   'Tra cứu tên, CCCD, địa chỉ, link Facebook từ số điện thoại.', 40),
('phone-check-batch','Check SĐT hàng loạt',       'Tra cứu',      2500,  'số',    'Tra cứu nhiều số trong 1 lần (tối đa 100 số).', 50),
('sms-send',        'Gửi tin nhắn SMS',          'Tin nhắn',     1500,   'tin',   'Gửi 1 tin nhắn đến số điện thoại Việt Nam.', 60),
('sms-package',     'Gói gửi tin hàng loạt',     'Tin nhắn',     12000,  '100 tin','Gói 100 tin nhắn, cron tự gửi dần theo giới hạn.', 70)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `group_name`=VALUES(`group_name`), `price`=VALUES(`price`), `unit`=VALUES(`unit`), `description`=VALUES(`description`);
