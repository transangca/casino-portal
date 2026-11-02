-- =====================================================================
-- MIGRATION V15 — Thẻ cào (card) + Bank ảo tải app + fix input trắng
--   1. Bảng card_denominations : danh mục mệnh giá thẻ cào (admin quản lý)
--   2. Bảng card_codes        : mã thẻ cào đã phát hành (serial/pin)
--   3. Bảng virtual_bank_apps : danh mục app ngân hàng tải về (link + file)
-- =====================================================================

CREATE TABLE IF NOT EXISTS `card_denominations` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`       VARCHAR(40) NOT NULL,
  `name`       VARCHAR(120) NOT NULL,
  `amount`     DECIMAL(18,2) NOT NULL,
  `type`       ENUM('viettel','mobifone','vinaphone','game','telco','other') NOT NULL DEFAULT 'other',
  `logo`       VARCHAR(255) DEFAULT NULL,
  `price`      DECIMAL(18,2) NOT NULL DEFAULT 0,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cd_code` (`code`),
  KEY `idx_cd_active` (`is_active`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `card_codes` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `denomination_id` INT UNSIGNED DEFAULT NULL,
  `serial`         VARCHAR(60) NOT NULL,
  `pin`            VARCHAR(60) NOT NULL,
  `amount`         DECIMAL(18,2) NOT NULL,
  `status`         ENUM('available','used','expired','blocked') NOT NULL DEFAULT 'available',
  `used_by`        INT UNSIGNED DEFAULT NULL,
  `used_at`        DATETIME DEFAULT NULL,
  `expires_at`     DATETIME DEFAULT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cc_serial` (`serial`),
  KEY `idx_cc_status` (`status`),
  KEY `idx_cc_denom` (`denomination_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `virtual_bank_apps` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bank_code`  VARCHAR(40) NOT NULL,
  `bank_name`  VARCHAR(120) NOT NULL,
  `platform`   ENUM('android','ios','apk','web') NOT NULL DEFAULT 'android',
  `version`    VARCHAR(40) DEFAULT NULL,
  `file_url`   VARCHAR(500) DEFAULT NULL,
  `link_url`   VARCHAR(500) DEFAULT NULL,
  `logo`       VARCHAR(255) DEFAULT NULL,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_vba_bank` (`bank_code`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Danh mục mệnh giá thẻ cào mặc định
INSERT INTO `card_denominations` (`code`,`name`,`amount`,`type`,`price`,`sort_order`) VALUES
('VIETTEL_10K','Viettel 10.000đ',10000,'viettel',10000,10),
('VIETTEL_20K','Viettel 20.000đ',20000,'viettel',20000,20),
('VIETTEL_50K','Viettel 50.000đ',50000,'viettel',50000,30),
('VIETTEL_100K','Viettel 100.000đ',100000,'viettel',100000,40),
('VIETTEL_200K','Viettel 200.000đ',200000,'viettel',200000,50),
('VIETTEL_500K','Viettel 500.000đ',500000,'viettel',500000,60),
('MOBIFONE_50K','Mobifone 50.000đ',50000,'mobifone',50000,70),
('MOBIFONE_100K','Mobifone 100.000đ',100000,'mobifone',100000,80),
('MOBIFONE_200K','Mobifone 200.000đ',200000,'mobifone',200000,90),
('MOBIFONE_500K','Mobifone 500.000đ',500000,'mobifone',500000,100),
('VINAPHONE_50K','Vinaphone 50.000đ',50000,'vinaphone',50000,110),
('VINAPHONE_100K','Vinaphone 100.000đ',100000,'vinaphone',100000,120),
('VINAPHONE_200K','Vinaphone 200.000đ',200000,'vinaphone',200000,130),
('VINAPHONE_500K','Vinaphone 500.000đ',500000,'vinaphone',500000,140),
('GAME_10K','Game 10.000đ',10000,'game',10000,150),
('GAME_50K','Game 50.000đ',50000,'game',50000,160),
('GAME_100K','Game 100.000đ',100000,'game',100000,170),
('GAME_200K','Game 200.000đ',200000,'game',200000,180),
('GAME_500K','Game 500.000đ',500000,'game',500000,190),
('GAME_1M','Game 1.000.000đ',1000000,'game',1000000,200)
ON DUPLICATE KEY UPDATE `name`=`name`;
