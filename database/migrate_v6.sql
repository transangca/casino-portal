-- =====================================================================
-- MIGRATION V6 — dịch vụ Bank ảo (HeyBank API v2)
-- =====================================================================

-- ---------- Tài khoản ngân hàng ảo (bản sao cục bộ) ----------
CREATE TABLE IF NOT EXISTS `virtual_bank_accounts` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        INT UNSIGNED NOT NULL,
  `remote_id`      INT UNSIGNED DEFAULT NULL,
  `bank_type`      VARCHAR(30) NOT NULL DEFAULT 'MB',
  `bank_name`      VARCHAR(150) DEFAULT NULL,
  `bank_username`  VARCHAR(150) DEFAULT NULL,
  `phone`          VARCHAR(30) DEFAULT NULL,
  `bank_number`    VARCHAR(40) DEFAULT NULL,
  `account_balance` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `avatar`         VARCHAR(500) DEFAULT NULL,
  `password_enc`   VARCHAR(500) DEFAULT NULL,
  `otp_enc`        VARCHAR(500) DEFAULT NULL,
  `status`         TINYINT NOT NULL DEFAULT 1,
  `expired_at`     DATETIME DEFAULT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vba_remote` (`remote_id`),
  KEY `idx_vba_user` (`user_id`,`created_at`),
  KEY `idx_vba_expired` (`expired_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Giao dịch của tài khoản ảo ----------
CREATE TABLE IF NOT EXISTS `virtual_bank_transactions` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        INT UNSIGNED NOT NULL,
  `account_id`     INT UNSIGNED DEFAULT NULL,
  `remote_id`      INT UNSIGNED DEFAULT NULL,
  `transaction_code` VARCHAR(40) DEFAULT NULL,
  `amount`         DECIMAL(18,2) NOT NULL DEFAULT 0,
  `from_name`      VARCHAR(150) DEFAULT NULL,
  `from_number`    VARCHAR(40) DEFAULT NULL,
  `recipient_name` VARCHAR(150) DEFAULT NULL,
  `recipient_account_number` VARCHAR(40) DEFAULT NULL,
  `bank_name`      VARCHAR(120) DEFAULT NULL,
  `bank_code_id`   VARCHAR(30) DEFAULT NULL,
  `type`           VARCHAR(20) DEFAULT 'RECEIVE',
  `note`           VARCHAR(255) DEFAULT NULL,
  `transaction_note` VARCHAR(255) DEFAULT NULL,
  `created_at`     DATETIME DEFAULT NULL,
  `synced_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_vbt_user` (`user_id`,`created_at`),
  KEY `idx_vbt_account` (`account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Đơn thuê / mua tài khoản ảo (đối soát ví) ----------
CREATE TABLE IF NOT EXISTS `virtual_bank_orders` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `account_id`  INT UNSIGNED DEFAULT NULL,
  `action`      VARCHAR(30) NOT NULL DEFAULT 'create',
  `period`      VARCHAR(30) DEFAULT NULL,
  `price`       DECIMAL(18,2) NOT NULL DEFAULT 0,
  `status`      ENUM('pending','success','failed') NOT NULL DEFAULT 'pending',
  `message`     VARCHAR(255) DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_vbo_user` (`user_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Cấu hình dịch vụ ----------
INSERT INTO `settings` (`name`,`value`,`group_name`,`input_type`) VALUES
('heybank_base_url',   'https://heybank.pro/api/v2', 'Bank ảo', 'text'),
('heybank_access_key', '',                          'Bank ảo', 'password'),
('heybank_auto_sync',  '1',                        'Bank ảo', 'text'),
('heybank_service_on',  '1',                        'Bank ảo', 'text')
ON DUPLICATE KEY UPDATE `group_name`=VALUES(`group_name`);

-- ---------- Danh mục dịch vụ ----------
INSERT INTO `service_catalog` (`code`,`name`,`group_name`,`description`,`icon`,`route`,`sort_order`) VALUES
('virtual-bank', 'Bank ảo',        'Tài chính', 'Thuê tài khoản ngân hàng ảo (MB, VCB, ACB, BIDV...) qua API HeyBank, gia hạn & tạo thông báo biến động số dư.', 'fa-vault', '/services/virtual-bank', 25)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `group_name`=VALUES(`group_name`), `description`=VALUES(`description`), `icon`=VALUES(`icon`), `route`=VALUES(`route`), `sort_order`=VALUES(`sort_order`);
