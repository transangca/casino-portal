-- =====================================================================
-- MIGRATION V9 — dịch vụ Auto Bank (cổng thanh toán tự động api.sieuthicode.net)
-- =====================================================================

-- ---------- Tài khoản ngân hàng kết nối ----------
CREATE TABLE IF NOT EXISTS `auto_bank_accounts` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `label`          VARCHAR(150) NOT NULL,
  `bank_key`       VARCHAR(40) NOT NULL,
  `bank_name`      VARCHAR(120) DEFAULT NULL,
  `api_version`    VARCHAR(10) NOT NULL DEFAULT 'v1',
  `token_enc`      VARCHAR(500) DEFAULT NULL,
  `password_enc`   VARCHAR(500) DEFAULT NULL,
  `stk`            VARCHAR(40) DEFAULT NULL,
  `match_content`  VARCHAR(255) DEFAULT NULL,
  `min_amount`     DECIMAL(18,2) NOT NULL DEFAULT 0,
  `max_amount`     DECIMAL(18,2) NOT NULL DEFAULT 0,
  `auto_approve`   TINYINT(1) NOT NULL DEFAULT 1,
  `is_active`      TINYINT(1) NOT NULL DEFAULT 1,
  `is_sandbox`     TINYINT(1) NOT NULL DEFAULT 0,
  `note`           VARCHAR(255) DEFAULT NULL,
  `last_sync_at`   DATETIME DEFAULT NULL,
  `last_sync_count` INT NOT NULL DEFAULT 0,
  `last_sync_msg`  VARCHAR(255) DEFAULT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_aba_active` (`is_active`),
  KEY `idx_aba_bank` (`bank_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Giao dịch đọc từ ngân hàng ----------
CREATE TABLE IF NOT EXISTS `auto_bank_transactions` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_id`      INT UNSIGNED NOT NULL,
  `fingerprint`     CHAR(64) NOT NULL,
  `transaction_id`  VARCHAR(120) DEFAULT NULL,
  `amount`          DECIMAL(18,4) NOT NULL DEFAULT 0,
  `description`     VARCHAR(500) DEFAULT NULL,
  `transaction_date` DATETIME DEFAULT NULL,
  `type`            ENUM('IN','OUT') NOT NULL DEFAULT 'IN',
  `balance`         DECIMAL(18,4) DEFAULT NULL,
  `currency`        VARCHAR(10) NOT NULL DEFAULT 'VND',
  `raw`             TEXT DEFAULT NULL,
  `matched_deposit` INT UNSIGNED DEFAULT NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_abt_fp` (`fingerprint`),
  KEY `idx_abt_account` (`account_id`,`id`),
  KEY `idx_abt_type` (`type`),
  KEY `idx_abt_matched` (`matched_deposit`),
  CONSTRAINT `fk_abt_account` FOREIGN KEY (`account_id`) REFERENCES `auto_bank_accounts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Ghi chú đối soát trên phiếu nạp ----------
ALTER TABLE `deposits`
    ADD COLUMN `auto_txn_id` INT UNSIGNED DEFAULT NULL AFTER `processed_at`;

-- ---------- Cấu hình dịch vụ ----------
INSERT INTO `settings` (`name`,`value`,`group_name`,`input_type`) VALUES
('autobank_base_url',     'https://api.sieuthicode.net', 'Auto Bank', 'text'),
('autobank_service_on',   '1',                           'Auto Bank', 'text'),
('autobank_auto_approve', '0',                           'Auto Bank', 'text'),
('autobank_service_fee',  '0',                           'Auto Bank', 'text'),
('autobank_poll_interval','30',                          'Auto Bank', 'text')
ON DUPLICATE KEY UPDATE `group_name`=VALUES(`group_name`);

-- ---------- Danh mục dịch vụ ----------
INSERT INTO `service_catalog` (`code`,`name`,`group_name`,`description`,`icon`,`route`,`sort_order`) VALUES
('auto-bank', 'Auto Bank', 'Tài chính', 'Cổng thanh toán tự động — đọc lịch sử giao dịch 20+ ngân hàng (MB, VCB, ACB, BIDV, Techcom...) và tự động đối soát nạp tiền.', 'fa-building-columns', '/admin/auto-bank', 28)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `group_name`=VALUES(`group_name`), `description`=VALUES(`description`), `icon`=VALUES(`icon`), `route`=VALUES(`route`), `sort_order`=VALUES(`sort_order`);
