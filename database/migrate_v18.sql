-- =====================================================================
-- MIGRATION V18 — Kho proxy: đơn mua + mã ưu đãi (voucher)
--   1. proxy_orders  : đơn mua proxy (trừ ví, ghi lịch sử)
--   2. proxy_vouchers: mã giảm giá cho kho proxy
-- =====================================================================

CREATE TABLE IF NOT EXISTS `proxy_orders` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     BIGINT UNSIGNED NOT NULL,
  `listing_id`  BIGINT UNSIGNED NOT NULL,
  `title`       VARCHAR(200) NOT NULL,
  `kind`        VARCHAR(20) NOT NULL DEFAULT 'http',
  `quantity`    INT UNSIGNED NOT NULL DEFAULT 1,
  `unit_price`  DECIMAL(18,2) NOT NULL DEFAULT 0,
  `total_amount` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `endpoint`    VARCHAR(500) DEFAULT NULL,
  `status`      VARCHAR(20) NOT NULL DEFAULT 'pending',
  `note`        VARCHAR(500) DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_po_user` (`user_id`,`id`),
  KEY `idx_po_listing` (`listing_id`),
  KEY `idx_po_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `proxy_vouchers` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`        VARCHAR(32) NOT NULL,
  `discount`    DECIMAL(5,2) NOT NULL DEFAULT 0,
  `max_uses`    INT UNSIGNED NOT NULL DEFAULT 0,
  `used_count`  INT UNSIGNED NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pv_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cấu hình phí P2P / phí đổi kho proxy
INSERT INTO `settings` (`name`,`value`,`group_name`,`input_type`) VALUES
('proxy_fee_percent', '0', 'Kho proxy', 'number')
ON DUPLICATE KEY UPDATE `group_name`=VALUES(`group_name`);

-- ---------- Cột ảnh bill chuyển khoản trên phiếu nạp ----------
ALTER TABLE `deposits`
  ADD COLUMN `bill_image` VARCHAR(255) DEFAULT NULL AFTER `transaction_code`,
  ADD COLUMN `bill_note` VARCHAR(500) DEFAULT NULL AFTER `bill_image`;

-- ---------- CV Builder: avatar + mục tùy chỉnh ----------
ALTER TABLE `cv_profiles`
  ADD COLUMN `avatar` VARCHAR(255) DEFAULT NULL AFTER `title`,
  ADD COLUMN `sections` JSON DEFAULT NULL AFTER `languages`,
  ADD COLUMN `headline` VARCHAR(200) DEFAULT NULL AFTER `full_name`,
  ADD COLUMN `links` JSON DEFAULT NULL AFTER `address`,
  ADD COLUMN `font_family` VARCHAR(60) DEFAULT NULL AFTER `accent`;

-- ---------- Xem phim: token API phim chính thức + cột nguồn ----------
INSERT INTO `settings` (`name`,`value`,`group_name`,`input_type`) VALUES
('tmdb_api_token', '', 'Xem phim', 'password'),
('tmdb_auto_sync', '1', 'Xem phim', 'switch')
ON DUPLICATE KEY UPDATE `group_name`=VALUES(`group_name`);

ALTER TABLE `media_items`
  ADD COLUMN `source` VARCHAR(40) DEFAULT NULL AFTER `genres`;
