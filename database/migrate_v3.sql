-- ===========================================================
-- CASINO PLATFORM - Migration v3
-- Nhóm dịch vụ mở rộng: P2P escrow, phát hiện gian lận,
-- phòng thủ DDoS/hacker, bảo trì, công cụ, AI, forum,
-- CV, xem phim, domain/hosting/VPS, POS kế toán, thiết kế ảnh,
-- telegram mini app, website builder, proxy, tra cứu ngân hàng.
-- ===========================================================

USE `casino_platform`;

-- ---------- 1. Tài khoản dự phòng (escrow) cho P2P & quản trị ----------
CREATE TABLE IF NOT EXISTS `reserve_accounts` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `owner_user_id` BIGINT UNSIGNED DEFAULT NULL,
  `label`       VARCHAR(120) NOT NULL,
  `type`        ENUM('p2p_escrow','admin_reserve','child_site','market') NOT NULL DEFAULT 'p2p_escrow',
  `balance`     DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `currency`    VARCHAR(10) NOT NULL DEFAULT 'VND',
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  `note`        VARCHAR(255) DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ra_owner` (`owner_user_id`),
  KEY `idx_ra_type` (`type`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 2. Sàn P2P (kiểu Binance P2P) ----------
CREATE TABLE IF NOT EXISTS `p2p_orders` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_no`      VARCHAR(32) NOT NULL,
  `seller_id`     BIGINT UNSIGNED NOT NULL,
  `buyer_id`      BIGINT UNSIGNED DEFAULT NULL,
  `title`         VARCHAR(200) NOT NULL,
  `description`   TEXT DEFAULT NULL,
  `category`      VARCHAR(40) NOT NULL DEFAULT 'account',
  `price`         DECIMAL(18,2) NOT NULL,
  `quantity`      INT UNSIGNED NOT NULL DEFAULT 1,
  `sold_count`    INT UNSIGNED NOT NULL DEFAULT 0,
  `payment_method` VARCHAR(60) DEFAULT NULL,
  `reserve_account_id` INT UNSIGNED DEFAULT NULL,
  `min_amount`    DECIMAL(18,2) NOT NULL DEFAULT 0,
  `max_amount`    DECIMAL(18,2) NOT NULL DEFAULT 0,
  `status`        ENUM('open','reserved','paid','delivered','buyer_confirmed','completed','disputed','cancelled','refunded') NOT NULL DEFAULT 'open',
  `terms`         TEXT DEFAULT NULL,
  `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_p2p_no` (`order_no`),
  KEY `idx_p2p_seller` (`seller_id`,`status`),
  KEY `idx_p2p_buyer` (`buyer_id`,`status`),
  KEY `idx_p2p_status` (`status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `p2p_trades` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `trade_no`      VARCHAR(32) NOT NULL,
  `order_id`      BIGINT UNSIGNED NOT NULL,
  `buyer_id`      BIGINT UNSIGNED NOT NULL,
  `seller_id`     BIGINT UNSIGNED NOT NULL,
  `amount`        DECIMAL(18,2) NOT NULL,
  `fee`           DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `reserve_account_id` INT UNSIGNED DEFAULT NULL,
  `status`        ENUM('created','paid','seller_delivered','buyer_confirmed','seller_confirmed','completed','disputed','refunded','cancelled') NOT NULL DEFAULT 'created',
  `buyer_paid_at`      DATETIME DEFAULT NULL,
  `seller_delivered_at` DATETIME DEFAULT NULL,
  `buyer_confirmed_at`  DATETIME DEFAULT NULL,
  `seller_confirmed_at` DATETIME DEFAULT NULL,
  `completed_at`        DATETIME DEFAULT NULL,
  `auto_released`  TINYINT(1) NOT NULL DEFAULT 0,
  `note`           VARCHAR(255) DEFAULT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_p2p_trade_no` (`trade_no`),
  KEY `idx_p2p_trade_order` (`order_id`),
  KEY `idx_p2p_trade_buyer` (`buyer_id`,`status`),
  KEY `idx_p2p_trade_seller` (`seller_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `p2p_events` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `trade_id`   BIGINT UNSIGNED NOT NULL,
  `actor_id`   BIGINT UNSIGNED DEFAULT NULL,
  `event`      VARCHAR(60) NOT NULL,
  `from_status` VARCHAR(40) DEFAULT NULL,
  `to_status`  VARCHAR(40) DEFAULT NULL,
  `detail`     JSON DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_p2p_ev_trade` (`trade_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `p2p_messages` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `trade_id`   BIGINT UNSIGNED NOT NULL,
  `sender_id`  BIGINT UNSIGNED NOT NULL,
  `body`       TEXT NOT NULL,
  `is_system`  TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_p2p_msg` (`trade_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `p2p_disputes` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `trade_id`    BIGINT UNSIGNED NOT NULL,
  `opened_by`   BIGINT UNSIGNED NOT NULL,
  `reason`      VARCHAR(255) NOT NULL,
  `evidence`    JSON DEFAULT NULL,
  `status`      ENUM('open','reviewing','resolved_buyer','resolved_seller','closed') NOT NULL DEFAULT 'open',
  `resolved_by` BIGINT UNSIGNED DEFAULT NULL,
  `resolution`  TEXT DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `resolved_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_p2p_disp` (`trade_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 3. Phát hiện gian lận & phòng thủ ----------
CREATE TABLE IF NOT EXISTS `security_events` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     BIGINT UNSIGNED DEFAULT NULL,
  `event_type`  VARCHAR(60) NOT NULL,
  `severity`    ENUM('low','medium','high','critical') NOT NULL DEFAULT 'low',
  `score`       INT NOT NULL DEFAULT 0,
  `ip`          VARCHAR(45) DEFAULT NULL,
  `user_agent`  VARCHAR(255) DEFAULT NULL,
  `path`        VARCHAR(255) DEFAULT NULL,
  `detail`      JSON DEFAULT NULL,
  `status`      ENUM('open','reviewing','resolved','ignored','blocked') NOT NULL DEFAULT 'open',
  `resolved_by` BIGINT UNSIGNED DEFAULT NULL,
  `resolved_at` DATETIME DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_se_type` (`event_type`,`created_at`),
  KEY `idx_se_user` (`user_id`,`created_at`),
  KEY `idx_se_status` (`status`,`severity`),
  KEY `idx_se_ip` (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ip_blacklist` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip`         VARCHAR(45) NOT NULL,
  `reason`     VARCHAR(255) DEFAULT NULL,
  `expires_at` DATETIME DEFAULT NULL,
  `hits`       INT UNSIGNED NOT NULL DEFAULT 0,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ibl_ip` (`ip`),
  KEY `idx_ibl_active` (`is_active`,`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rate_limits` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bucket`       VARCHAR(80) NOT NULL,
  `ident`        VARCHAR(120) NOT NULL,
  `hits`         INT UNSIGNED NOT NULL DEFAULT 0,
  `window_start` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `blocked_until` DATETIME DEFAULT NULL,
  `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rl_bucket_ident` (`bucket`,`ident`),
  KEY `idx_rl_blocked` (`blocked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `attack_signatures` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pattern`    VARCHAR(255) NOT NULL,
  `type`       VARCHAR(40) NOT NULL DEFAULT 'sqli',
  `action`     ENUM('log','block','challenge','throttle') NOT NULL DEFAULT 'log',
  `hits`       INT UNSIGNED NOT NULL DEFAULT 0,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_as_pattern` (`pattern`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 4. Nhật ký hệ thống / backup ----------
CREATE TABLE IF NOT EXISTS `system_backups` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `scope`       VARCHAR(40) NOT NULL DEFAULT 'db',
  `label`       VARCHAR(150) NOT NULL,
  `driver`      VARCHAR(20) NOT NULL DEFAULT 'local',
  `path`        VARCHAR(500) DEFAULT NULL,
  `size_bytes`  INT UNSIGNED NOT NULL DEFAULT 0,
  `checksum`    VARCHAR(64) DEFAULT NULL,
  `status`      ENUM('running','ok','failed') NOT NULL DEFAULT 'running',
  `error`       TEXT DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sb_scope` (`scope`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 5. Danh mục dịch vụ mới ----------
CREATE TABLE IF NOT EXISTS `service_catalog` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`        VARCHAR(50) NOT NULL,
  `name`        VARCHAR(150) NOT NULL,
  `group_name`  VARCHAR(50) NOT NULL DEFAULT 'Khác',
  `description` VARCHAR(500) DEFAULT NULL,
  `icon`        VARCHAR(80) DEFAULT NULL,
  `route`       VARCHAR(255) NOT NULL,
  `is_maintenance` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order`  INT NOT NULL DEFAULT 0,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sc_code` (`code`),
  KEY `idx_sc_group` (`group_name`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 6. Công cụ tiện ích ----------
CREATE TABLE IF NOT EXISTS `tools_registry` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`        VARCHAR(50) NOT NULL,
  `name`        VARCHAR(150) NOT NULL,
  `group_name`  VARCHAR(50) NOT NULL DEFAULT 'Tiện ích',
  `description` VARCHAR(500) DEFAULT NULL,
  `icon`        VARCHAR(80) DEFAULT NULL,
  `route`       VARCHAR(255) NOT NULL,
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order`  INT NOT NULL DEFAULT 0,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tr_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 7. AI: model + agent ----------
CREATE TABLE IF NOT EXISTS `ai_models` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `provider`    VARCHAR(60) NOT NULL,
  `model`       VARCHAR(120) NOT NULL,
  `label`       VARCHAR(150) NOT NULL,
  `endpoint`    VARCHAR(500) DEFAULT NULL,
  `api_key_ref` VARCHAR(120) DEFAULT NULL,
  `context_k`   INT UNSIGNED NOT NULL DEFAULT 8,
  `price_in`    DECIMAL(12,6) NOT NULL DEFAULT 0,
  `price_out`   DECIMAL(12,6) NOT NULL DEFAULT 0,
  `capabilities` JSON DEFAULT NULL,
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order`  INT NOT NULL DEFAULT 0,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_aim_model` (`provider`,`model`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_agents` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(150) NOT NULL,
  `description` VARCHAR(500) DEFAULT NULL,
  `model_id`    INT UNSIGNED DEFAULT NULL,
  `system_prompt` TEXT DEFAULT NULL,
  `tools`       JSON DEFAULT NULL,
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 8. Forum ----------
CREATE TABLE IF NOT EXISTS `forum_topics` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     BIGINT UNSIGNED NOT NULL,
  `category`    VARCHAR(50) NOT NULL DEFAULT 'general',
  `title`       VARCHAR(250) NOT NULL,
  `slug`        VARCHAR(250) NOT NULL,
  `body`        LONGTEXT DEFAULT NULL,
  `is_pinned`   TINYINT(1) NOT NULL DEFAULT 0,
  `is_locked`   TINYINT(1) NOT NULL DEFAULT 0,
  `views`       INT UNSIGNED NOT NULL DEFAULT 0,
  `reply_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `last_post_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ft_slug` (`slug`),
  KEY `idx_ft_cat` (`category`,`last_post_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `forum_posts` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `topic_id`   BIGINT UNSIGNED NOT NULL,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `body`       LONGTEXT NOT NULL,
  `is_solution` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fp_topic` (`topic_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 9. CV Builder ----------
CREATE TABLE IF NOT EXISTS `cv_profiles` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     BIGINT UNSIGNED NOT NULL,
  `title`       VARCHAR(150) NOT NULL,
  `full_name`   VARCHAR(150) DEFAULT NULL,
  `email`       VARCHAR(150) DEFAULT NULL,
  `phone`       VARCHAR(30) DEFAULT NULL,
  `address`     VARCHAR(255) DEFAULT NULL,
  `summary`     TEXT DEFAULT NULL,
  `experience`  JSON DEFAULT NULL,
  `education`   JSON DEFAULT NULL,
  `skills`      JSON DEFAULT NULL,
  `languages`   JSON DEFAULT NULL,
  `template`    VARCHAR(40) NOT NULL DEFAULT 'modern',
  `accent`      VARCHAR(20) NOT NULL DEFAULT '#f5c542',
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cv_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 10. Xem phim ----------
CREATE TABLE IF NOT EXISTS `media_items` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`       VARCHAR(250) NOT NULL,
  `slug`        VARCHAR(250) NOT NULL,
  `type`        ENUM('movie','series','trailer') NOT NULL DEFAULT 'movie',
  `poster`      VARCHAR(500) DEFAULT NULL,
  `backdrop`    VARCHAR(500) DEFAULT NULL,
  `trailer_url` VARCHAR(500) DEFAULT NULL,
  `video_url`   VARCHAR(500) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `year`        SMALLINT UNSIGNED DEFAULT NULL,
  `rating`      DECIMAL(3,1) DEFAULT NULL,
  `duration`    VARCHAR(20) DEFAULT NULL,
  `genres`      JSON DEFAULT NULL,
  `is_vip`      TINYINT(1) NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  `views`       INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mi_slug` (`slug`),
  KEY `idx_mi_active` (`is_active`,`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 11. Domain / Hosting / VPS ----------
CREATE TABLE IF NOT EXISTS `domain_orders` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     BIGINT UNSIGNED NOT NULL,
  `domain`      VARCHAR(255) NOT NULL,
  `tld`         VARCHAR(20) NOT NULL,
  `registrar`   VARCHAR(60) DEFAULT NULL,
  `years`       TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `price`       DECIMAL(18,2) NOT NULL,
  `status`      ENUM('checking','available','unavailable','ordered','active','expired','cancelled') NOT NULL DEFAULT 'checking',
  `dns_checked_at` DATETIME DEFAULT NULL,
  `expires_at`  DATE DEFAULT NULL,
  `note`        VARCHAR(255) DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_do_user` (`user_id`,`created_at`),
  KEY `idx_do_domain` (`domain`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `infra_plans` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kind`       ENUM('hosting','vps','mail','ssl') NOT NULL,
  `name`       VARCHAR(150) NOT NULL,
  `provider`   VARCHAR(80) DEFAULT NULL,
  `spec`       VARCHAR(255) DEFAULT NULL,
  `price_month` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `price_year`  DECIMAL(18,2) NOT NULL DEFAULT 0,
  `features`   JSON DEFAULT NULL,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_ip_kind` (`kind`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 12. Thiết kế ảnh: font, biến thể, dự án ----------
CREATE TABLE IF NOT EXISTS `design_fonts` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `family`     VARCHAR(120) NOT NULL,
  `file_path`  VARCHAR(500) NOT NULL,
  `weight`     VARCHAR(20) NOT NULL DEFAULT '400',
  `format`     VARCHAR(20) NOT NULL DEFAULT 'ttf',
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `design_variants` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `font_id`    INT UNSIGNED DEFAULT NULL,
  `label`      VARCHAR(150) NOT NULL,
  `code`       VARCHAR(60) NOT NULL,
  `sample_text` VARCHAR(255) DEFAULT NULL,
  `price`      DECIMAL(18,2) NOT NULL DEFAULT 0,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dv_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `design_projects` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `name`       VARCHAR(150) NOT NULL,
  `category`   VARCHAR(50) NOT NULL DEFAULT 'poster',
  `template`   VARCHAR(50) DEFAULT NULL,
  `canvas`     JSON DEFAULT NULL,
  `price`      DECIMAL(18,2) NOT NULL DEFAULT 0,
  `status`     ENUM('draft','rendering','done','failed') NOT NULL DEFAULT 'draft',
  `output_path` VARCHAR(500) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_dp_user` (`user_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 13. Telegram mini app ----------
CREATE TABLE IF NOT EXISTS `telegram_apps` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(150) NOT NULL,
  `bot_username` VARCHAR(80) DEFAULT NULL,
  `app_url`     VARCHAR(500) DEFAULT NULL,
  `description` VARCHAR(500) DEFAULT NULL,
  `theme_json`  JSON DEFAULT NULL,
  `menu_button` JSON DEFAULT NULL,
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 14. Website builder (demo dự án) ----------
CREATE TABLE IF NOT EXISTS `website_projects` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     BIGINT UNSIGNED NOT NULL,
  `name`        VARCHAR(150) NOT NULL,
  `platform`    VARCHAR(60) NOT NULL DEFAULT 'laravel',
  `domain`      VARCHAR(255) DEFAULT NULL,
  `email`       VARCHAR(150) DEFAULT NULL,
  `telegram`    VARCHAR(150) DEFAULT NULL,
  `contact_info` JSON DEFAULT NULL,
  `demo_url`    VARCHAR(500) DEFAULT NULL,
  `images`      JSON DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `price`       DECIMAL(18,2) NOT NULL DEFAULT 0,
  `status`      ENUM('draft','demo','review','active','rejected') NOT NULL DEFAULT 'draft',
  `notified`    TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wp_user` (`user_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 15. Proxy & tài khoản dịch vụ ----------
CREATE TABLE IF NOT EXISTS `proxy_listings` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `title`      VARCHAR(200) NOT NULL,
  `kind`       ENUM('http','https','socks5','residential','mobile','mail') NOT NULL DEFAULT 'http',
  `country`    VARCHAR(60) DEFAULT NULL,
  `city`       VARCHAR(60) DEFAULT NULL,
  `isp`        VARCHAR(120) DEFAULT NULL,
  `duration_days` INT UNSIGNED NOT NULL DEFAULT 30,
  `threads`    INT UNSIGNED NOT NULL DEFAULT 1,
  `price`      DECIMAL(18,2) NOT NULL,
  `stock`      INT NOT NULL DEFAULT -1,
  `endpoint`   VARCHAR(500) DEFAULT NULL,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pl_kind` (`kind`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 16. Tra cứu ngân hàng ----------
CREATE TABLE IF NOT EXISTS `bank_accounts` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bank_code`  VARCHAR(20) NOT NULL,
  `bank_name`  VARCHAR(150) NOT NULL,
  `account_no` VARCHAR(40) NOT NULL,
  `account_name` VARCHAR(150) NOT NULL,
  `branch`     VARCHAR(150) DEFAULT NULL,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ba_account` (`bank_code`,`account_no`),
  KEY `idx_ba_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 17. POS / Kế toán (KiotViet-like) ----------
CREATE TABLE IF NOT EXISTS `pos_products` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`       VARCHAR(60) NOT NULL,
  `name`       VARCHAR(200) NOT NULL,
  `category`   VARCHAR(60) DEFAULT NULL,
  `price`      DECIMAL(18,2) NOT NULL DEFAULT 0,
  `cost`       DECIMAL(18,2) NOT NULL DEFAULT 0,
  `stock`      DECIMAL(18,2) NOT NULL DEFAULT 0,
  `unit`       VARCHAR(30) DEFAULT NULL,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pos_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pos_orders` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_no`   VARCHAR(32) NOT NULL,
  `user_id`    BIGINT UNSIGNED DEFAULT NULL,
  `customer_name` VARCHAR(150) DEFAULT NULL,
  `customer_phone` VARCHAR(30) DEFAULT NULL,
  `items`      JSON NOT NULL,
  `subtotal`   DECIMAL(18,2) NOT NULL DEFAULT 0,
  `discount`   DECIMAL(18,2) NOT NULL DEFAULT 0,
  `total`      DECIMAL(18,2) NOT NULL DEFAULT 0,
  `payment_method` VARCHAR(40) NOT NULL DEFAULT 'cash',
  `status`     ENUM('open','paid','cancelled','refunded') NOT NULL DEFAULT 'open',
  `note`       VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pos_no` (`order_no`),
  KEY `idx_pos_status` (`status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pos_customers` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    BIGINT UNSIGNED DEFAULT NULL,
  `name`       VARCHAR(150) NOT NULL,
  `phone`      VARCHAR(30) DEFAULT NULL,
  `email`       VARCHAR(150) DEFAULT NULL,
  `address`    VARCHAR(255) DEFAULT NULL,
  `total_spent` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `debt`       DECIMAL(18,2) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pc_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 18. Gia hạn hạ tầng: khoá cache ----------
CREATE TABLE IF NOT EXISTS `cache_store` (
  `cache_key`  VARCHAR(190) NOT NULL,
  `cache_value` MEDIUMTEXT DEFAULT NULL,
  `expires_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`cache_key`),
  KEY `idx_cs_exp` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Seed cấu hình bảo mật & bảo trì ----------
INSERT IGNORE INTO `settings` (`name`,`value`) VALUES
  ('maintenance_mode','0'),
  ('maintenance_message','Hệ thống đang bảo trì. Vui lòng quay lại sau ít phút.'),
  ('maintenance_allow_admin','1'),
  ('ddos_protection','1'),
  ('rate_limit_enabled','1'),
  ('api_history_enabled','1'),
  ('api_history_limit','200'),
  ('fraud_detection','1'),
  ('p2p_escrow_enabled','1'),
  ('p2p_auto_release','1'),
  ('p2p_auto_release_hours','24'),
  ('p2p_fee_percent','1.000'),
  ('brand_name','Casino Portal'),
  ('seo_title','Casino Portal - Sảnh game uy tín, đồng bộ ví tức thì'),
  ('seo_description','Sảnh casino live, slot, bắn cá, thể thao, xổ số đồng bộ ví tức thì. Nạp tiền qua VietQR, rút tiền nhanh 24/7.'),
  ('seo_keywords','casino, slot, bắn cá, thể thao, xổ số, game bài, nạp tiền, rút tiền'),
  ('backup_driver','local'),
  ('backup_interval_hours','2'),
  ('backup_keep','24');

-- ---------- Seed chữ ký tấn công ----------
INSERT IGNORE INTO `attack_signatures` (`pattern`,`type`,`action`) VALUES
  ('union select','sqli','block'),
  ('\' or \'1\'=\'1','sqli','block'),
  ('<script','xss','block'),
  ('javascript:','xss','block'),
  ('../etc/passwd','lfi','block'),
  ('../../windows','lfi','block'),
  ('select * from users','sqli','log'),
  ('eval(','rce','block');

-- ---------- 19. Users: IP đăng ký & lần đăng nhập cuối ----------
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `ip_register` VARCHAR(45) DEFAULT NULL AFTER `status`,
  ADD COLUMN IF NOT EXISTS `last_ip` VARCHAR(45) DEFAULT NULL AFTER `ip_register`,
  ADD COLUMN IF NOT EXISTS `login_count` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `last_ip`;

-- ---------- 20. Telegram bot cấu hình ----------
CREATE TABLE IF NOT EXISTS `telegram_bots` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `label`       VARCHAR(120) NOT NULL,
  `bot_token`   VARCHAR(255) DEFAULT NULL,
  `chat_id`     VARCHAR(60) DEFAULT NULL,
  `is_default`  TINYINT(1) NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
