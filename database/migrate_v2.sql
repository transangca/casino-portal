-- ===========================================================
-- CASINO PLATFORM - Migration v2
-- Bổ sung: multi-category provider, demo tab, 2FA, OAuth,
--           ads, VIP 60, child site, transfer, marketplace,
--           mail, news, theme, language, install log
-- ===========================================================

USE `casino_platform`;

-- ---------- 1. Providers: mở rộng cho đa danh mục ----------
ALTER TABLE `providers`
  ADD COLUMN IF NOT EXISTS `category` VARCHAR(30) NOT NULL DEFAULT 'slot' AFTER `driver`,
  ADD COLUMN IF NOT EXISTS `wallet_mode` ENUM('seamless','demo_only','operator') NOT NULL DEFAULT 'demo_only' AFTER `category`,
  ADD COLUMN IF NOT EXISTS `lobby_url` VARCHAR(500) DEFAULT NULL AFTER `wallet_mode`,
  ADD COLUMN IF NOT EXISTS `promo_image` VARCHAR(500) DEFAULT NULL AFTER `logo`,
  ADD COLUMN IF NOT EXISTS `promo_video` VARCHAR(500) DEFAULT NULL AFTER `promo_image`,
  ADD COLUMN IF NOT EXISTS `video_poster` VARCHAR(500) DEFAULT NULL AFTER `promo_video`,
  ADD COLUMN IF NOT EXISTS `description` TEXT DEFAULT NULL AFTER `video_poster`,
  ADD COLUMN IF NOT EXISTS `is_featured` TINYINT(1) NOT NULL DEFAULT 0 AFTER `sort_order`,
  ADD COLUMN IF NOT EXISTS `config` JSON DEFAULT NULL AFTER `api_secret`;

-- ---------- 2. Games: cờ demo + video ----------
ALTER TABLE `games`
  ADD COLUMN IF NOT EXISTS `is_demo` TINYINT(1) NOT NULL DEFAULT 1 AFTER `is_free`,
  ADD COLUMN IF NOT EXISTS `trailer` VARCHAR(500) DEFAULT NULL AFTER `thumb`,
  ADD COLUMN IF NOT EXISTS `big_icon` VARCHAR(500) DEFAULT NULL AFTER `icon`;

-- ---------- 3. Cấu hình nhóm danh mục ----------
CREATE TABLE IF NOT EXISTS `game_categories` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`       VARCHAR(40) NOT NULL,
  `name`       VARCHAR(120) NOT NULL,
  `name_en`    VARCHAR(120) DEFAULT NULL,
  `icon`       VARCHAR(80) DEFAULT NULL,
  `banner`     VARCHAR(500) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_gc_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 4. Banner quảng cáo ----------
CREATE TABLE IF NOT EXISTS `ads_banners` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`       VARCHAR(150) NOT NULL,
  `slot`        VARCHAR(40) NOT NULL DEFAULT 'home_top',
  `image_url`   VARCHAR(500) DEFAULT NULL,
  `video_url`   VARCHAR(500) DEFAULT NULL,
  `link_url`    VARCHAR(500) DEFAULT NULL,
  `position`    VARCHAR(40) DEFAULT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order`  INT NOT NULL DEFAULT 0,
  `start_at`    DATETIME DEFAULT NULL,
  `end_at`      DATETIME DEFAULT NULL,
  `clicks`      INT UNSIGNED NOT NULL DEFAULT 0,
  `impressions` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ad_slot` (`slot`,`is_active`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 5. 2FA (TOTP) ----------
CREATE TABLE IF NOT EXISTS `user_twofa` (
  `user_id`      BIGINT UNSIGNED NOT NULL,
  `secret`       VARCHAR(64) NOT NULL,
  `recovery_codes` TEXT DEFAULT NULL,
  `is_enabled`   TINYINT(1) NOT NULL DEFAULT 0,
  `confirmed_at` DATETIME DEFAULT NULL,
  `last_used_at` DATETIME DEFAULT NULL,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_2fa_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 6. Liên kết mạng xã hội ----------
CREATE TABLE IF NOT EXISTS `social_accounts` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `provider`   VARCHAR(30) NOT NULL,
  `provider_id` VARCHAR(120) NOT NULL,
  `email`      VARCHAR(150) DEFAULT NULL,
  `name`       VARCHAR(150) DEFAULT NULL,
  `avatar`     VARCHAR(500) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sa_provider` (`provider`,`provider_id`),
  KEY `idx_sa_user` (`user_id`),
  CONSTRAINT `fk_sa_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 7. Mở rộng users: avatar, tick xanh, ngôn ngữ, theme ----------
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `avatar` VARCHAR(500) DEFAULT NULL AFTER `phone`,
  ADD COLUMN IF NOT EXISTS `is_verified` TINYINT(1) NOT NULL DEFAULT 0 AFTER `avatar`,
  ADD COLUMN IF NOT EXISTS `language` VARCHAR(10) NOT NULL DEFAULT 'vi' AFTER `is_verified`,
  ADD COLUMN IF NOT EXISTS `theme` VARCHAR(20) NOT NULL DEFAULT 'system' AFTER `language`;

-- ---------- 8. Chuyển tiền giữa thành viên ----------
CREATE TABLE IF NOT EXISTS `transfers` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `from_user_id` BIGINT UNSIGNED NOT NULL,
  `to_user_id`  BIGINT UNSIGNED NOT NULL,
  `amount`      DECIMAL(18,2) NOT NULL,
  `fee`         DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `note`        VARCHAR(255) DEFAULT NULL,
  `status`      ENUM('pending','success','cancelled') NOT NULL DEFAULT 'success',
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tr_from` (`from_user_id`,`created_at`),
  KEY `idx_tr_to` (`to_user_id`,`created_at`),
  CONSTRAINT `fk_tr_from` FOREIGN KEY (`from_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tr_to` FOREIGN KEY (`to_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 9. Marketplace (mua bán tài nguyên) ----------
CREATE TABLE IF NOT EXISTS `market_products` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `seller_id`   BIGINT UNSIGNED NOT NULL,
  `title`       VARCHAR(200) NOT NULL,
  `slug`        VARCHAR(200) NOT NULL,
  `category`    VARCHAR(40) NOT NULL DEFAULT 'account',
  `description` TEXT DEFAULT NULL,
  `price`       DECIMAL(18,2) NOT NULL DEFAULT 0,
  `stock`       INT NOT NULL DEFAULT -1,
  `image`       VARCHAR(500) DEFAULT NULL,
  `contact`     VARCHAR(255) DEFAULT NULL,
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  `sold_count`  INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mp_slug` (`slug`),
  KEY `idx_mp_cat` (`category`,`is_active`),
  CONSTRAINT `fk_mp_seller` FOREIGN KEY (`seller_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `market_orders` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `buyer_id`   BIGINT UNSIGNED NOT NULL,
  `seller_id`  BIGINT UNSIGNED NOT NULL,
  `amount`     DECIMAL(18,2) NOT NULL,
  `status`     ENUM('pending','paid','delivered','cancelled','refunded') NOT NULL DEFAULT 'pending',
  `note`       VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mo_product` (`product_id`),
  KEY `idx_mo_buyer` (`buyer_id`),
  CONSTRAINT `fk_mo_product` FOREIGN KEY (`product_id`) REFERENCES `market_products`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mo_buyer` FOREIGN KEY (`buyer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 10. Website con (child site) ----------
CREATE TABLE IF NOT EXISTS `child_sites` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     BIGINT UNSIGNED NOT NULL,
  `name`        VARCHAR(150) NOT NULL,
  `slug`        VARCHAR(100) NOT NULL,
  `domain`      VARCHAR(255) DEFAULT NULL,
  `logo`        VARCHAR(500) DEFAULT NULL,
  `banner`      VARCHAR(500) DEFAULT NULL,
  `theme`       VARCHAR(20) NOT NULL DEFAULT 'dark',
  `rev_share`   DECIMAL(6,3) NOT NULL DEFAULT 0.000,
  `require_deposit` TINYINT(1) NOT NULL DEFAULT 1,
  `min_deposit` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `status`      ENUM('pending','active','suspended') NOT NULL DEFAULT 'pending',
  `hits`        INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cs_slug` (`slug`),
  KEY `idx_cs_user` (`user_id`),
  CONSTRAINT `fk_cs_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `child_site_visits` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `child_id`   BIGINT UNSIGNED NOT NULL,
  `user_id`    BIGINT UNSIGNED DEFAULT NULL,
  `ip`         VARCHAR(45) DEFAULT NULL,
  `referrer`   VARCHAR(500) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_csv_child` (`child_id`,`created_at`),
  CONSTRAINT `fk_csv_child` FOREIGN KEY (`child_id`) REFERENCES `child_sites`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 11. Tin tức / sự kiện ----------
CREATE TABLE IF NOT EXISTS `news_posts` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`      VARCHAR(250) NOT NULL,
  `slug`       VARCHAR(250) NOT NULL,
  `excerpt`    VARCHAR(500) DEFAULT NULL,
  `content`    LONGTEXT DEFAULT NULL,
  `banner`     VARCHAR(500) DEFAULT NULL,
  `type`       ENUM('news','event','promotion') NOT NULL DEFAULT 'news',
  `is_pinned`  TINYINT(1) NOT NULL DEFAULT 0,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `published_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_np_slug` (`slug`),
  KEY `idx_np_active` (`is_active`,`published_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 12. Mail log + campaign ----------
CREATE TABLE IF NOT EXISTS `mail_logs` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `to_email`   VARCHAR(150) NOT NULL,
  `to_user_id` BIGINT UNSIGNED DEFAULT NULL,
  `subject`    VARCHAR(255) NOT NULL,
  `template`   VARCHAR(80) DEFAULT NULL,
  `status`     ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
  `error`      TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ml_to` (`to_email`),
  KEY `idx_ml_status` (`status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mail_campaigns` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`       VARCHAR(200) NOT NULL,
  `subject`     VARCHAR(255) NOT NULL,
  `body`        LONGTEXT DEFAULT NULL,
  `banner`      VARCHAR(500) DEFAULT NULL,
  `target`      ENUM('all','vip','active','deposited') NOT NULL DEFAULT 'all',
  `status`      ENUM('draft','sending','sent') NOT NULL DEFAULT 'draft',
  `total`       INT UNSIGNED NOT NULL DEFAULT 0,
  `sent_count`  INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 13. Nhật ký hoạt động (API / hệ thống) ----------
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    BIGINT UNSIGNED DEFAULT NULL,
  `scope`      VARCHAR(40) NOT NULL DEFAULT 'system',
  `action`     VARCHAR(120) NOT NULL,
  `subject`    VARCHAR(255) DEFAULT NULL,
  `detail`     JSON DEFAULT NULL,
  `ip`         VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_al_scope` (`scope`,`created_at`),
  KEY `idx_al_user` (`user_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 14. Cài đặt / hệ thống ----------
CREATE TABLE IF NOT EXISTS `install_meta` (
  `name`       VARCHAR(80) NOT NULL,
  `value`      TEXT DEFAULT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 15. Ngôn ngữ ----------
CREATE TABLE IF NOT EXISTS `languages` (
  `code`       VARCHAR(10) NOT NULL,
  `name`       VARCHAR(80) NOT NULL,
  `flag`       VARCHAR(20) DEFAULT NULL,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 16. Mở rộng vip_tiers cho 60 cấp ----------
ALTER TABLE `vip_tiers`
  ADD COLUMN IF NOT EXISTS `perks` JSON DEFAULT NULL AFTER `icon`,
  ADD COLUMN IF NOT EXISTS `badge_3d` MEDIUMTEXT DEFAULT NULL AFTER `perks`,
  ADD COLUMN IF NOT EXISTS `color` VARCHAR(20) DEFAULT NULL AFTER `badge_3d`,
  ADD COLUMN IF NOT EXISTS `can_child_site` TINYINT(1) NOT NULL DEFAULT 0 AFTER `color`,
  ADD COLUMN IF NOT EXISTS `rev_share_max` DECIMAL(6,3) NOT NULL DEFAULT 0.000 AFTER `can_child_site`;

-- ---------- Seed danh mục ----------
INSERT IGNORE INTO `languages` (`code`,`name`,`flag`,`sort_order`) VALUES
  ('vi','Tiếng Việt','🇻🇳',1),
  ('en','English','🇬🇧',2),
  ('zh','中文','🇨🇳',3),
  ('ko','한국어','🇰🇷',4),
  ('ja','日本語','🇯🇵',5),
  ('th','ไทย','🇹🇭',6),
  ('id','Bahasa Indonesia','🇮🇩',7),
  ('es','Español','🇪🇸',8),
  ('ru','Русский','🇷🇺',9),
  ('pt','Português','🇧🇷',10);

INSERT IGNORE INTO `game_categories` (`code`,`name`,`name_en`,`icon`,`sort_order`) VALUES
  ('casino','Casino Live','Casino Live','fa-diamond',1),
  ('slot','Slot','Slots','fa-coins',2),
  ('fishing','Bắn Cá','Fishing','fa-fish',3),
  ('blockchain','Blockchain','Crash','fa-chart-line',4),
  ('sports','Thể Thao','Sports','fa-futbol',5),
  ('card','Game Bài','Card Games','fa-diamond',6),
  ('lottery','Xổ Số','Lottery','fa-ticket',7);

INSERT IGNORE INTO `settings` (`name`,`value`) VALUES
  ('theme_style','system'),
  ('default_language','vi'),
  ('site_slogan','Sảnh game uy tín - đồng bộ ví tức thì'),
  ('child_site_min_vip','5'),
  ('child_site_max_rev_share','0.300'),
  ('referral_deposit_percent','0.500'),
  ('referral_bonus_fixed','50000');
