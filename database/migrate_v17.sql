-- =====================================================================
-- MIGRATION V17 — Dịch vụ bán tài khoản & nick (API đối tác Vuavia)
--   1. vuavia_products   : danh mục sản phẩm đồng bộ từ API đối tác
--   2. vuavia_orders     : đơn hàng (AUTO giao ngay / MANUAL chờ admin giao)
--   3. vuavia_deliveries : hàng đã giao (mật khẩu mã hoá AES-256-GCM)
--   4. vuavia_categories : danh mục sản phẩm để lọc
-- =====================================================================

-- ---------- Danh mục sản phẩm ----------
CREATE TABLE IF NOT EXISTS `vuavia_categories` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` INT UNSIGNED NOT NULL,
  `name`        VARCHAR(150) NOT NULL,
  `sort_order`  INT NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vcat_cid` (`category_id`),
  KEY `idx_vcat_active` (`is_active`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Sản phẩm ----------
CREATE TABLE IF NOT EXISTS `vuavia_products` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id`    INT UNSIGNED NOT NULL,
  `name`          VARCHAR(255) NOT NULL,
  `price`         DECIMAL(18,2) NOT NULL DEFAULT 0,
  `stock_count`   INT NOT NULL DEFAULT 0,
  `delivery_type` VARCHAR(20) NOT NULL DEFAULT 'AUTO',
  `category_id`   INT UNSIGNED DEFAULT NULL,
  `category_name` VARCHAR(150) DEFAULT NULL,
  `sort_order`    INT NOT NULL DEFAULT 0,
  `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vp_pid` (`product_id`),
  KEY `idx_vp_cat` (`category_id`,`is_active`),
  KEY `idx_vp_active` (`is_active`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Đơn hàng ----------
CREATE TABLE IF NOT EXISTS `vuavia_orders` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        INT UNSIGNED NOT NULL,
  `product_id`     INT UNSIGNED NOT NULL,
  `product_name`   VARCHAR(255) DEFAULT NULL,
  `quantity`       INT UNSIGNED NOT NULL DEFAULT 1,
  `total_amount`   DECIMAL(18,2) NOT NULL DEFAULT 0,
  `unit_price`     DECIMAL(18,2) NOT NULL DEFAULT 0,
  `status`         VARCHAR(30) NOT NULL DEFAULT 'PENDING',
  `partner_order_id` INT UNSIGNED DEFAULT NULL,
  `delivery_note`  VARCHAR(500) DEFAULT NULL,
  `error_msg`      VARCHAR(500) DEFAULT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_vo_user` (`user_id`,`id`),
  KEY `idx_vo_status` (`status`),
  KEY `idx_vo_pid` (`partner_order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Hàng đã giao (mật khẩu mã hoá) ----------
CREATE TABLE IF NOT EXISTS `vuavia_deliveries` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id`       INT UNSIGNED NOT NULL,
  `account`        VARCHAR(255) DEFAULT NULL,
  `password_enc`   TEXT DEFAULT NULL,
  `is_redeemed`    TINYINT(1) NOT NULL DEFAULT 0,
  `redeemed_at`    DATETIME DEFAULT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_vd_order` (`order_id`),
  KEY `idx_vd_acct` (`account`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Cấu hình dịch vụ ----------
INSERT INTO `settings` (`name`,`value`,`group_name`,`input_type`) VALUES
('vuavia_base_url',    'https://api.vuavia.io/api/v2', 'Tài khoản & Nick', 'text'),
('vuavia_api_key',     '',                              'Tài khoản & Nick', 'password'),
('vuavia_service_on',  '1',                             'Tài khoản & Nick', 'text'),
('vuavia_auto_sync',   '1',                             'Tài khoản & Nick', 'text'),
('vuavia_markup',      '0',                             'Tài khoản & Nick', 'text')
ON DUPLICATE KEY UPDATE `group_name`=VALUES(`group_name`);

-- ---------- Danh mục dịch vụ ----------
INSERT INTO `service_catalog` (`code`,`name`,`group_name`,`description`,`icon`,`route`,`sort_order`) VALUES
('vuavia-accounts', 'Tài khoản & Nick', 'Dịch vụ số', 'Mua tài khoản, nick, clone tài khoản mạng xã hội — giao hàng tự động ngay sau khi thanh toán.', 'fa-user-secret', '/services/vuavia-accounts', 42)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `group_name`=VALUES(`group_name`), `description`=VALUES(`description`), `icon`=VALUES(`icon`), `route`=VALUES(`route`), `sort_order`=VALUES(`sort_order`);

-- ---------- Seed bảng giá dịch vụ ----------
INSERT INTO `service_prices` (`code`,`name`,`group_name`,`price`,`unit`,`description`,`sort_order`) VALUES
('vuavia-order', 'Mua tài khoản & nick', 'Tài khoản', 0, 'sản phẩm', 'Giá bán = giá đối tác + biên lợi nhuận (admin cấu hình).', 80)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `group_name`=VALUES(`group_name`), `description`=VALUES(`description`);
