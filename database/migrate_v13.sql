-- =====================================================================
-- MIGRATION V13 — dịch vụ VPS StoreVPS / Virtualizor (XVPS)
-- Base URL: https://api.xvps.vn/api/virt/*
-- Tách biệt hoàn toàn với V12 (XVPS Agency API — VMware VPS)
-- =====================================================================

-- ---------- Gói VPS + addon (Virtualizor) ----------
CREATE TABLE IF NOT EXISTS `vps_vz_products` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id`     VARCHAR(190) NOT NULL,
  `group_name`     VARCHAR(300) DEFAULT NULL,
  `name`           VARCHAR(300) DEFAULT NULL,
  `display_name`   VARCHAR(300) DEFAULT NULL,
  `description`    VARCHAR(500) DEFAULT NULL,
  `type_vps`       VARCHAR(60)  DEFAULT NULL,
  `cpu`            INT NOT NULL DEFAULT 0,
  `ram`            INT NOT NULL DEFAULT 0,
  `disk`           INT NOT NULL DEFAULT 0,
  `bandwidth`      INT NOT NULL DEFAULT 0,
  `network_speed`  INT NOT NULL DEFAULT 0,
  `upload_speed`   INT NOT NULL DEFAULT -1,
  `ipv4`           INT NOT NULL DEFAULT 0,
  `ipv6`           INT NOT NULL DEFAULT 0,
  `ipv6_subnet`    INT NOT NULL DEFAULT 0,
  `type_addon`     VARCHAR(60)  DEFAULT NULL,
  `unit_value`     INT NOT NULL DEFAULT 1,
  `is_free`        TINYINT(1) NOT NULL DEFAULT 0,
  `is_addon`       TINYINT(1) NOT NULL DEFAULT 0,
  `price_monthly`  DECIMAL(18,4) NOT NULL DEFAULT 0,
  `price_quarterly` DECIMAL(18,4) NOT NULL DEFAULT 0,
  `price_semi_annually` DECIMAL(18,4) NOT NULL DEFAULT 0,
  `price_annually` DECIMAL(18,4) NOT NULL DEFAULT 0,
  `pricing_json`   TEXT DEFAULT NULL,
  `locations_json` TEXT DEFAULT NULL,
  `is_active`      TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order`     INT NOT NULL DEFAULT 0,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vzprod_pid` (`product_id`),
  KEY `idx_vzprod_group` (`group_name`),
  KEY `idx_vzprod_addon` (`is_addon`,`type_addon`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Hệ điều hành (Virtualizor) ----------
CREATE TABLE IF NOT EXISTS `vps_vz_operating_systems` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `os_id`          VARCHAR(60) NOT NULL,
  `os_name`        VARCHAR(190) NOT NULL,
  `distro`         VARCHAR(120) DEFAULT NULL,
  `is_active`      TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vzos_oid` (`os_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Địa điểm / datacenter (Virtualizor) ----------
CREATE TABLE IF NOT EXISTS `vps_vz_locations` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `location_id`    VARCHAR(120) NOT NULL,
  `code`           VARCHAR(60)  DEFAULT NULL,
  `name`           VARCHAR(190) DEFAULT NULL,
  `surcharge`      DECIMAL(18,4) NOT NULL DEFAULT 0,
  `product_id`     VARCHAR(190) DEFAULT NULL,
  `is_active`      TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vzloc_lid` (`location_id`),
  KEY `idx_vzloc_prod` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Đơn VPS (Virtualizor) ----------
CREATE TABLE IF NOT EXISTS `vps_vz_orders` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        BIGINT UNSIGNED NOT NULL,
  `remote_order`   VARCHAR(120) DEFAULT NULL,
  `instance_id`    VARCHAR(120) DEFAULT NULL,
  `order_code`     VARCHAR(190) DEFAULT NULL,
  `product_id`     VARCHAR(190) DEFAULT NULL,
  `product_name`   VARCHAR(300) DEFAULT NULL,
  `billing_cycle`  VARCHAR(60) DEFAULT NULL,
  `os_id`          VARCHAR(60) DEFAULT NULL,
  `os_name`        VARCHAR(190) DEFAULT NULL,
  `location_id`    VARCHAR(120) DEFAULT NULL,
  `location_name`  VARCHAR(190) DEFAULT NULL,
  `hostname`       VARCHAR(190) DEFAULT NULL,
  `quantity`       INT NOT NULL DEFAULT 1,
  `addons_json`    TEXT DEFAULT NULL,
  `price`          DECIMAL(18,4) NOT NULL DEFAULT 0,
  `amount`         DECIMAL(18,4) NOT NULL DEFAULT 0,
  `status`         VARCHAR(40) NOT NULL DEFAULT 'pending',
  `vps_status`     VARCHAR(60) DEFAULT NULL,
  `ip`             VARCHAR(120) DEFAULT NULL,
  `username`       VARCHAR(190) DEFAULT NULL,
  `password`       VARCHAR(500) DEFAULT NULL,
  `date_create`    VARCHAR(60) DEFAULT NULL,
  `next_due_date`  VARCHAR(60) DEFAULT NULL,
  `idempotency_key` VARCHAR(190) DEFAULT NULL,
  `message`        VARCHAR(500) DEFAULT NULL,
  `raw`            TEXT DEFAULT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vzord_remote` (`remote_order`),
  KEY `idx_vzord_user` (`user_id`,`created_at`),
  KEY `idx_vzord_status` (`status`),
  KEY `idx_vzord_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- VPS đang quản lý (Virtualizor) ----------
CREATE TABLE IF NOT EXISTS `vps_vz_instances` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        BIGINT UNSIGNED NOT NULL,
  `vps_id`         VARCHAR(120) NOT NULL,
  `hostname`       VARCHAR(190) DEFAULT NULL,
  `order_code`     VARCHAR(190) DEFAULT NULL,
  `product_id`     VARCHAR(190) DEFAULT NULL,
  `product_name`   VARCHAR(300) DEFAULT NULL,
  `ip`             VARCHAR(120) DEFAULT NULL,
  `ipv6`           VARCHAR(190) DEFAULT NULL,
  `username`       VARCHAR(190) DEFAULT NULL,
  `password`       VARCHAR(500) DEFAULT NULL,
  `os_name`        VARCHAR(190) DEFAULT NULL,
  `cpu`            INT NOT NULL DEFAULT 0,
  `ram`            INT NOT NULL DEFAULT 0,
  `disk`           INT NOT NULL DEFAULT 0,
  `text_config`    VARCHAR(190) DEFAULT NULL,
  `type_vps`       VARCHAR(60) DEFAULT NULL,
  `billing_cycle`  VARCHAR(60) DEFAULT NULL,
  `day_left`       VARCHAR(60) DEFAULT NULL,
  `vps_status`     VARCHAR(60) DEFAULT 'unknown',
  `amount`         DECIMAL(18,4) NOT NULL DEFAULT 0,
  `auto_renew`     TINYINT(1) NOT NULL DEFAULT 0,
  `description`    VARCHAR(500) DEFAULT NULL,
  `date_create`    VARCHAR(60) DEFAULT NULL,
  `next_due_date`  VARCHAR(60) DEFAULT NULL,
  `last_sync_at`   DATETIME DEFAULT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vzinst_vpsid` (`vps_id`),
  KEY `idx_vzinst_user` (`user_id`),
  KEY `idx_vzinst_status` (`vps_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Lịch sử thao tác trên VPS (Virtualizor) ----------
CREATE TABLE IF NOT EXISTS `vps_vz_actions` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        BIGINT UNSIGNED DEFAULT NULL,
  `vps_id`         VARCHAR(120) NOT NULL,
  `action`         VARCHAR(60) NOT NULL,
  `status`         VARCHAR(40) NOT NULL DEFAULT 'success',
  `message`        VARCHAR(500) DEFAULT NULL,
  `meta`           TEXT DEFAULT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_vzact_vps` (`vps_id`,`created_at`),
  KEY `idx_vzact_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Mở rộng ENUM loại giao dịch ví ----------
ALTER TABLE `wallet_transactions`
  MODIFY COLUMN `type` ENUM(
    'deposit','withdraw','bet','win','refund','bonus','adjustment','giftcode',
    'vps_order','vps_refund','vps_renew','vps_addon',
    'vps_vz_order','vps_vz_refund','vps_vz_renew','vps_vz_addon'
  ) NOT NULL;

-- ---------- Cấu hình dịch vụ ----------
INSERT INTO `settings` (`name`,`value`,`group_name`,`input_type`) VALUES
('vps_vz_base_url',     'https://api.xvps.vn',            'XVPS Virtualizor', 'text'),
('vps_vz_service_on',   '1',                            'XVPS Virtualizor', 'text'),
('vps_vz_api_username', '',                             'XVPS Virtualizor', 'text'),
('vps_vz_api_app',      '',                             'XVPS Virtualizor', 'text'),
('vps_vz_api_secret',   '',                             'XVPS Virtualizor', 'password'),
('vps_vz_markup',       '0',                            'XVPS Virtualizor', 'text'),
('vps_vz_vat_percent',  '0',                            'XVPS Virtualizor', 'text'),
('vps_vz_auto_sync',    '1',                            'XVPS Virtualizor', 'text'),
('vps_vz_sandbox',      '0',                            'XVPS Virtualizor', 'text')
ON DUPLICATE KEY UPDATE `group_name`=VALUES(`group_name`);

-- ---------- Danh mục dịch vụ ----------
INSERT INTO `service_catalog` (`code`,`name`,`group_name`,`description`,`icon`,`route`,`sort_order`) VALUES
('vps-storevps', 'VPS StoreVPS (Virtualizor)', 'Hạ tầng', 'Thuê VPS StoreVPS qua API Virtualizor (api.xvps.vn/api/virt/*) — đặt đơn, quản lý nguồn, rebuild, đổi mật khẩu, hostname, gia hạn, nâng cấp addon, tự động gia hạn và hủy dịch vụ.', 'fa-layer-group', '/services/vps-storevps', 41)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `group_name`=VALUES(`group_name`), `description`=VALUES(`description`), `icon`=VALUES(`icon`), `route`=VALUES(`route`), `sort_order`=VALUES(`sort_order`);
