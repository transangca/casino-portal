-- =====================================================================
-- MIGRATION V8 — dịch vụ thuê số nhận OTP (API ViOTP v1)
-- =====================================================================

-- ---------- Danh mục dịch vụ thuê số ----------
CREATE TABLE IF NOT EXISTS `otp_services` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `service_id` INT NOT NULL,
  `name`       VARCHAR(100) NOT NULL,
  `price`      DECIMAL(18,2) NOT NULL DEFAULT 0,
  `icon`       VARCHAR(80) DEFAULT NULL,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_os_sid` (`service_id`),
  KEY `idx_os_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Lượt thuê số ----------
CREATE TABLE IF NOT EXISTS `otp_rentals` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        BIGINT UNSIGNED NOT NULL,
  `rental_id`      BIGINT UNSIGNED NOT NULL,
  `service_id`     INT NOT NULL DEFAULT 0,
  `service_name`   VARCHAR(100) DEFAULT NULL,
  `phone`          VARCHAR(30) DEFAULT NULL,
  `carriers`       VARCHAR(120) DEFAULT NULL,
  `price`          DECIMAL(18,2) NOT NULL DEFAULT 0,
  `status`         TINYINT NOT NULL DEFAULT 0 COMMENT '0=đợi mã, 1=hoàn thành, 2=hết hạn',
  `otp_code`       VARCHAR(120) DEFAULT NULL,
  `content`        TEXT DEFAULT NULL,
  `is_audio`       TINYINT(1) NOT NULL DEFAULT 0,
  `balance_after`  DECIMAL(18,2) NOT NULL DEFAULT 0,
  `message`        VARCHAR(255) DEFAULT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_or_rid` (`rental_id`),
  KEY `idx_or_user` (`user_id`,`created_at`),
  KEY `idx_or_status` (`status`),
  CONSTRAINT `fk_or_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Cấu hình dịch vụ ----------
INSERT INTO `settings` (`name`,`value`,`group_name`,`input_type`) VALUES
('viotp_base_url',    'https://api.viotp.net', 'Thuê số OTP', 'text'),
('viotp_token',       '',                     'Thuê số OTP', 'password'),
('viotp_service_on',  '1',                    'Thuê số OTP', 'text'),
('viotp_markup',      '0',                    'Thuê số OTP', 'text'),
('viotp_wait_minutes','7',                    'Thuê số OTP', 'text')
ON DUPLICATE KEY UPDATE `group_name`=VALUES(`group_name`);

-- ---------- Danh mục dịch vụ ----------
INSERT INTO `service_catalog` (`code`,`name`,`group_name`,`description`,`icon`,`route`,`sort_order`) VALUES
('otp-rental', 'Thuê số nhận OTP', 'Dịch vụ số', 'Thuê số điện thoại tạm để nhận mã OTP (Facebook, Momo, Zalo, Shopee...) qua API ViOTP, giao mã tự động.', 'fa-comment-sms', '/services/otp-rental', 27)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `group_name`=VALUES(`group_name`), `description`=VALUES(`description`), `icon`=VALUES(`icon`), `route`=VALUES(`route`), `sort_order`=VALUES(`sort_order`);
