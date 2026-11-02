-- =====================================================================
-- MIGRATION V11 — dịch vụ SMM (mua like/view/comment) — API trungtammmo.vn
-- Base URL: https://trungtammmo.vn/api/external/smm
-- + Hệ API key dùng chung cho SMM & Auto MXH (ttmmo_api_keys)
-- =====================================================================

-- ---------- Hệ API key dùng chung (SMM + Auto MXH) ----------
CREATE TABLE IF NOT EXISTS `ttmmo_api_keys` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      BIGINT UNSIGNED NOT NULL,
  `apikey`       VARCHAR(190) NOT NULL,
  `label`        VARCHAR(150) DEFAULT NULL,
  `is_active`    TINYINT(1) NOT NULL DEFAULT 1,
  `agent_discount_percent` DECIMAL(6,2) NOT NULL DEFAULT 0,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ttmmo_key` (`apikey`),
  KEY `idx_ttmmo_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Chuyển key đã lưu của Auto MXH sang hệ key chung
INSERT IGNORE INTO `ttmmo_api_keys` (`user_id`, `apikey`, `label`, `is_active`, `created_at`, `updated_at`)
SELECT `user_id`, `apikey`, `label`, `is_active`, `created_at`, `updated_at` FROM `automxh_api_keys`;

-- ---------- Nhóm dịch vụ (card category như web SMM) ----------
CREATE TABLE IF NOT EXISTS `smm_categories` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_slug`  VARCHAR(190) NOT NULL,
  `category`       VARCHAR(300) NOT NULL,
  `clean_category` VARCHAR(300) DEFAULT NULL,
  `platform`       VARCHAR(120) DEFAULT NULL,
  `service_count`  INT NOT NULL DEFAULT 0,
  `min`            INT NOT NULL DEFAULT 0,
  `max`            INT NOT NULL DEFAULT 0,
  `price_min_per_unit` DECIMAL(18,4) NOT NULL DEFAULT 0,
  `price_max_per_unit` DECIMAL(18,4) NOT NULL DEFAULT 0,
  `display_per_unit` VARCHAR(120) DEFAULT NULL,
  `sort_order`     INT NOT NULL DEFAULT 0,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_smmc_slug` (`category_slug`),
  KEY `idx_smmc_plat` (`platform`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Dịch vụ ----------
CREATE TABLE IF NOT EXISTS `smm_services` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `service_id`     INT NOT NULL,
  `name`           VARCHAR(400) NOT NULL,
  `type`           VARCHAR(120) DEFAULT NULL,
  `category`       VARCHAR(300) DEFAULT NULL,
  `category_slug`  VARCHAR(190) DEFAULT NULL,
  `clean_category` VARCHAR(300) DEFAULT NULL,
  `platform`       VARCHAR(120) DEFAULT NULL,
  `min`            INT NOT NULL DEFAULT 0,
  `max`            INT NOT NULL DEFAULT 0,
  `refill`         TINYINT(1) NOT NULL DEFAULT 0,
  `price_per_1k_vnd` DECIMAL(18,4) NOT NULL DEFAULT 0,
  `price_per_unit_vnd` DECIMAL(18,4) NOT NULL DEFAULT 0,
  `list_price_per_1k_vnd` DECIMAL(18,4) DEFAULT NULL,
  `agent_discount_percent` DECIMAL(6,2) NOT NULL DEFAULT 0,
  `is_comment_service` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active`      TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order`     INT NOT NULL DEFAULT 0,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_smms_sid` (`service_id`),
  KEY `idx_smms_cat` (`category_slug`),
  KEY `idx_smms_plat` (`platform`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Đơn SMM ----------
CREATE TABLE IF NOT EXISTS `smm_orders` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        BIGINT UNSIGNED NOT NULL,
  `remote_order`   VARCHAR(60) DEFAULT NULL,
  `service_id`     INT NOT NULL DEFAULT 0,
  `service_name`   VARCHAR(400) DEFAULT NULL,
  `category_slug`  VARCHAR(190) DEFAULT NULL,
  `platform`       VARCHAR(120) DEFAULT NULL,
  `link`           VARCHAR(1000) DEFAULT NULL,
  `quantity`       INT NOT NULL DEFAULT 0,
  `comments`       TEXT DEFAULT NULL,
  `reaction`       VARCHAR(120) DEFAULT NULL,
  `price`          DECIMAL(18,4) NOT NULL DEFAULT 0,
  `amount`         DECIMAL(18,4) NOT NULL DEFAULT 0,
  `charge`         DECIMAL(18,4) NOT NULL DEFAULT 0,
  `status`         VARCHAR(40) NOT NULL DEFAULT 'pending',
  `start_count`    VARCHAR(60) DEFAULT NULL,
  `remains`        VARCHAR(60) DEFAULT NULL,
  `idempotency_key` VARCHAR(190) DEFAULT NULL,
  `message`        VARCHAR(500) DEFAULT NULL,
  `raw`            TEXT DEFAULT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_smmo_remote` (`remote_order`),
  KEY `idx_smmo_user` (`user_id`,`created_at`),
  KEY `idx_smmo_status` (`status`),
  KEY `idx_smmo_service` (`service_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Cấu hình dịch vụ ----------
INSERT INTO `settings` (`name`,`value`,`group_name`,`input_type`) VALUES
('smm_base_url',   'https://trungtammmo.vn/api/external/smm', 'SMM', 'text'),
('smm_service_on', '1',                                          'SMM', 'text'),
('smm_api_key',    '',                                           'SMM', 'password'),
('smm_markup',     '0',                                          'SMM', 'text'),
('smm_auto_sync',  '1',                                          'SMM', 'text'),
('smm_vat_percent','0',                                          'SMM', 'text')
ON DUPLICATE KEY UPDATE `group_name`=VALUES(`group_name`);

-- ---------- Danh mục dịch vụ ----------
INSERT INTO `service_catalog` (`code`,`name`,`group_name`,`description`,`icon`,`route`,`sort_order`) VALUES
('smm', 'SMM — Like, View, Comment', 'Mạng xã hội', 'Mua like, view, comment, share trên Facebook/TikTok/Instagram qua API SMM — giá theo số lượng, trừ ví chính, theo dõi tiến độ đơn.', 'fa-thumbs-up', '/services/smm', 30)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `group_name`=VALUES(`group_name`), `description`=VALUES(`description`), `icon`=VALUES(`icon`), `route`=VALUES(`route`), `sort_order`=VALUES(`sort_order`);
