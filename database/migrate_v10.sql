-- =====================================================================
-- MIGRATION V10 — dịch vụ Auto MXH (mở khóa tài khoản) — API trungtammmo.vn
-- Base URL: https://trungtammmo.vn/api/external/automxh
-- =====================================================================

-- ---------- Danh mục category / product ----------
CREATE TABLE IF NOT EXISTS `automxh_categories` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id`  INT NOT NULL,
  `slug`         VARCHAR(120) NOT NULL,
  `name`         VARCHAR(200) NOT NULL,
  `icon`         VARCHAR(255) DEFAULT NULL,
  `gif`          VARCHAR(255) DEFAULT NULL,
  `status`       VARCHAR(30) DEFAULT 'active',
  `sort_order`   INT NOT NULL DEFAULT 0,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_amxc_cat` (`category_id`),
  KEY `idx_amxc_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Sản phẩm (gói cha) ----------
CREATE TABLE IF NOT EXISTS `automxh_products` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id`   INT NOT NULL,
  `category_id`  INT NOT NULL DEFAULT 0,
  `category_slug` VARCHAR(120) DEFAULT NULL,
  `name`         VARCHAR(300) NOT NULL,
  `min_price`    DECIMAL(18,2) NOT NULL DEFAULT 0,
  `variant_count` INT NOT NULL DEFAULT 0,
  `is_active`    TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order`   INT NOT NULL DEFAULT 0,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_amxp_prod` (`product_id`),
  KEY `idx_amxp_cat` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Gói/variant (đơn vị đặt đơn) ----------
CREATE TABLE IF NOT EXISTS `automxh_variants` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `variant_id`    INT NOT NULL,
  `product_id`    INT NOT NULL DEFAULT 0,
  `category_id`   INT NOT NULL DEFAULT 0,
  `category_slug` VARCHAR(120) DEFAULT NULL,
  `product_name`  VARCHAR(300) DEFAULT NULL,
  `name`          VARCHAR(300) NOT NULL,
  `type`          VARCHAR(80) DEFAULT NULL,
  `badge`         VARCHAR(80) DEFAULT NULL,
  `quantity`      INT NOT NULL DEFAULT 1,
  `price`         DECIMAL(18,2) NOT NULL DEFAULT 0,
  `vat_amount`    DECIMAL(18,2) NOT NULL DEFAULT 0,
  `total_to_pay`  DECIMAL(18,2) NOT NULL DEFAULT 0,
  `currency`      VARCHAR(10) NOT NULL DEFAULT 'VND',
  `list_price`    DECIMAL(18,2) DEFAULT NULL,
  `agent_discount_percent` DECIMAL(6,2) NOT NULL DEFAULT 0,
  `allow_avatar`  TINYINT(1) NOT NULL DEFAULT 0,
  `allow_files`   TINYINT(1) NOT NULL DEFAULT 0,
  `description`   TEXT DEFAULT NULL,
  `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order`    INT NOT NULL DEFAULT 0,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_amxv_var` (`variant_id`),
  KEY `idx_amxv_prod` (`product_id`),
  KEY `idx_amxv_cat` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Đơn Auto MXH ----------
CREATE TABLE IF NOT EXISTS `automxh_orders` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`       BIGINT UNSIGNED NOT NULL,
  `remote_order`  BIGINT UNSIGNED DEFAULT NULL,
  `variant_id`    INT NOT NULL DEFAULT 0,
  `product_id`    INT NOT NULL DEFAULT 0,
  `category_slug` VARCHAR(120) DEFAULT NULL,
  `variant_name`  VARCHAR(300) DEFAULT NULL,
  `product_name`  VARCHAR(300) DEFAULT NULL,
  `link`          VARCHAR(1000) DEFAULT NULL,
  `buyer_info`    VARCHAR(500) DEFAULT NULL,
  `custom_value`  VARCHAR(1000) DEFAULT NULL,
  `quantity`      INT NOT NULL DEFAULT 1,
  `price`         DECIMAL(18,2) NOT NULL DEFAULT 0,
  `amount`        DECIMAL(18,2) NOT NULL DEFAULT 0,
  `charge`        DECIMAL(18,2) NOT NULL DEFAULT 0,
  `status`        VARCHAR(30) NOT NULL DEFAULT 'pending',
  `progress_status` VARCHAR(60) DEFAULT NULL,
  `progress_remains` VARCHAR(60) DEFAULT NULL,
  `balance_after` DECIMAL(18,2) DEFAULT NULL,
  `idempotency_key` VARCHAR(190) DEFAULT NULL,
  `message`       VARCHAR(500) DEFAULT NULL,
  `raw`           TEXT DEFAULT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_amxo_remote` (`remote_order`),
  KEY `idx_amxo_user` (`user_id`,`created_at`),
  KEY `idx_amxo_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- API key của từng user (dùng chung hệ key với SMM) ----------
CREATE TABLE IF NOT EXISTS `automxh_api_keys` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      BIGINT UNSIGNED NOT NULL,
  `apikey`       VARCHAR(190) NOT NULL,
  `label`        VARCHAR(150) DEFAULT NULL,
  `is_active`    TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_amxk_key` (`apikey`),
  KEY `idx_amxk_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Cấu hình dịch vụ ----------
INSERT INTO `settings` (`name`,`value`,`group_name`,`input_type`) VALUES
('automxh_base_url',     'https://trungtammmo.vn/api/external/automxh', 'Auto MXH', 'text'),
('automxh_service_on',   '1',                                              'Auto MXH', 'text'),
('automxh_api_key',      '',                                               'Auto MXH', 'password'),
('automxh_markup',       '0',                                              'Auto MXH', 'text'),
('automxh_auto_sync',    '1',                                              'Auto MXH', 'text'),
('automxh_fee_rate',     '0',                                              'Auto MXH', 'text')
ON DUPLICATE KEY UPDATE `group_name`=VALUES(`group_name`);

-- ---------- Danh mục dịch vụ ----------
INSERT INTO `service_catalog` (`code`,`name`,`group_name`,`description`,`icon`,`route`,`sort_order`) VALUES
('automxh', 'Auto MXH', 'Mở khóa', 'Mở khóa & bảo vệ tài khoản Facebook, Instagram, TikTok... qua API Auto MXH — đặt đơn tự động, trừ ví chính, theo dõi tiến độ.', 'fa-unlock-keyhole', '/services/automxh', 29)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `group_name`=VALUES(`group_name`), `description`=VALUES(`description`), `icon`=VALUES(`icon`), `route`=VALUES(`route`), `sort_order`=VALUES(`sort_order`);
