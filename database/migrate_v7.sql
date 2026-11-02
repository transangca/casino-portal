-- =====================================================================
-- MIGRATION V7 — dịch vụ bán tài khoản AI (API đối tác GenzShop)
-- =====================================================================

-- ---------- Danh mục sản phẩm (đồng bộ từ API) ----------
CREATE TABLE IF NOT EXISTS `ai_account_products` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id`   VARCHAR(80) NOT NULL,
  `name`         VARCHAR(150) NOT NULL,
  `description`  VARCHAR(500) DEFAULT NULL,
  `type`         VARCHAR(40) DEFAULT NULL,
  `type_label`   VARCHAR(60) DEFAULT NULL,
  `price`        DECIMAL(18,2) NOT NULL DEFAULT 0,
  `available`    INT NOT NULL DEFAULT 0,
  `icon`         VARCHAR(80) DEFAULT NULL,
  `sort_order`   INT NOT NULL DEFAULT 0,
  `is_active`    TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_aap_pid` (`product_id`),
  KEY `idx_aap_type` (`type`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Đơn hàng ----------
CREATE TABLE IF NOT EXISTS `ai_account_orders` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        INT UNSIGNED NOT NULL,
  `product_id`     VARCHAR(80) NOT NULL,
  `product_name`   VARCHAR(150) DEFAULT NULL,
  `type`           VARCHAR(40) DEFAULT NULL,
  `quantity`       INT UNSIGNED NOT NULL DEFAULT 1,
  `amount`         DECIMAL(18,2) NOT NULL DEFAULT 0,
  `order_code`     VARCHAR(40) DEFAULT NULL,
  `idempotency_key` VARCHAR(128) DEFAULT NULL,
  `status`         ENUM('pending','success','failed','replay') NOT NULL DEFAULT 'pending',
  `replay`         TINYINT(1) NOT NULL DEFAULT 0,
  `message`        VARCHAR(255) DEFAULT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_aao_idem` (`idempotency_key`),
  KEY `idx_aao_user` (`user_id`,`created_at`),
  KEY `idx_aao_code` (`order_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Tài khoản được giao (mật khẩu mã hoá) ----------
CREATE TABLE IF NOT EXISTS `ai_account_deliveries` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id`       INT UNSIGNED NOT NULL,
  `product_item_id` VARCHAR(60) DEFAULT NULL,
  `username`       VARCHAR(150) DEFAULT NULL,
  `password_enc`   VARCHAR(500) DEFAULT NULL,
  `verify_email`   VARCHAR(150) DEFAULT NULL,
  `is_redeemed`    TINYINT(1) NOT NULL DEFAULT 0,
  `redeemed_at`    DATETIME DEFAULT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_aad_order` (`order_id`),
  KEY `idx_aad_user` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Cấu hình dịch vụ ----------
INSERT INTO `settings` (`name`,`value`,`group_name`,`input_type`) VALUES
('genzshop_base_url',   'https://genzshop.vn/api/partner/v1', 'Tài khoản AI', 'text'),
('genzshop_api_key',    '',                                'Tài khoản AI', 'password'),
('genzshop_service_on', '1',                               'Tài khoản AI', 'text'),
('genzshop_auto_sync',  '1',                               'Tài khoản AI', 'text'),
('genzshop_markup',     '0',                               'Tài khoản AI', 'text')
ON DUPLICATE KEY UPDATE `group_name`=VALUES(`group_name`);

-- ---------- Danh mục dịch vụ ----------
INSERT INTO `service_catalog` (`code`,`name`,`group_name`,`description`,`icon`,`route`,`sort_order`) VALUES
('ai-accounts', 'Tài khoản AI',  'Dịch vụ số', 'Bán tài khoản Cursor, ChatGPT, Claude, Gemini... qua API đối tác GenzShop, giao tài khoản tự động.', 'fa-robot', '/services/ai-accounts', 26)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `group_name`=VALUES(`group_name`), `description`=VALUES(`description`), `icon`=VALUES(`icon`), `route`=VALUES(`route`), `sort_order`=VALUES(`sort_order`);
