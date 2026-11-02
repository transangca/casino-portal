-- =====================================================================
-- MIGRATION V14 — Nâng cấp dịch vụ Sale & tin nhắn hàng loạt
--   1. Bảng sale_account_verifications : lưu mã xác minh do ứng dụng gửi
--   2. Bảng sale_imports              : lịch sử import file .txt / .csv
--   3. Thêm cột an toàn cho sale_accounts
-- =====================================================================

CREATE TABLE IF NOT EXISTS `sale_account_verifications` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `account_id`  INT UNSIGNED DEFAULT NULL,
  `platform`    VARCHAR(40) NOT NULL DEFAULT 'telegram',
  `account`     VARCHAR(150) NOT NULL,
  `code_hash`   VARCHAR(255) NOT NULL,
  `attempts`    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `max_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 5,
  `expires_at`  DATETIME NOT NULL,
  `verified_at` DATETIME DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sav_user` (`user_id`,`created_at`),
  KEY `idx_sav_account` (`account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sale_imports` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `filename`    VARCHAR(255) NOT NULL,
  `platform`    VARCHAR(40) NOT NULL DEFAULT 'telegram',
  `total_rows`  INT UNSIGNED NOT NULL DEFAULT 0,
  `valid_rows`  INT UNSIGNED NOT NULL DEFAULT 0,
  `invalid_rows` INT UNSIGNED NOT NULL DEFAULT 0,
  `status`      ENUM('pending','done','failed') NOT NULL DEFAULT 'done',
  `error`       TEXT DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_si_user` (`user_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `sale_accounts`
  ADD COLUMN `status` ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending' AFTER `is_active`,
  ADD COLUMN `verified_at` DATETIME DEFAULT NULL AFTER `status`,
  ADD COLUMN `last_error` VARCHAR(255) DEFAULT NULL AFTER `verified_at`;

-- Cài đặt mặc định
INSERT INTO `settings` (`name`,`value`) VALUES
('sale_verify_required','1'),
('sale_import_max_rows','5000'),
('sale_import_max_size','5242880'),
('virtual_bank_apps','[]')
ON DUPLICATE KEY UPDATE `value`=`value`;
