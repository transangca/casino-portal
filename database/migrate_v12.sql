-- =====================================================================
-- MIGRATION V12 — dịch vụ VPS (XVPS Agency API — VMware VPS)
-- Base URL: https://api.xvps.vn
-- + Tái sử dụng hệ API key dùng chung ttmmo_api_keys cho khách & đối tác
-- =====================================================================

-- ---------- Gói VPS (product) ----------
CREATE TABLE IF NOT EXISTS `vps_products` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id`     VARCHAR(120) NOT NULL,
  `group_name`     VARCHAR(300) DEFAULT NULL,
  `name`           VARCHAR(300) DEFAULT NULL,
  `special`        TINYINT(1) NOT NULL DEFAULT 0,
  `cpu`            INT NOT NULL DEFAULT 0,
  `ram`            INT NOT NULL DEFAULT 0,
  `disk`           INT NOT NULL DEFAULT 0,
  `ip_info`        VARCHAR(190) DEFAULT NULL,
  `os_support`     VARCHAR(190) DEFAULT NULL,
  `bandwidth`      VARCHAR(190) DEFAULT NULL,
  `type_addon`     VARCHAR(120) DEFAULT NULL,
  `is_addon`       TINYINT(1) NOT NULL DEFAULT 0,
  `price_monthly`  DECIMAL(18,4) NOT NULL DEFAULT 0,
  `price_quarterly` DECIMAL(18,4) NOT NULL DEFAULT 0,
  `price_semi_annually` DECIMAL(18,4) NOT NULL DEFAULT 0,
  `price_annually` DECIMAL(18,4) NOT NULL DEFAULT 0,
  `pricing_json`   TEXT DEFAULT NULL,
  `is_active`      TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order`     INT NOT NULL DEFAULT 0,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vpsp_pid` (`product_id`),
  KEY `idx_vpsp_group` (`group_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Hệ điều hành ----------
CREATE TABLE IF NOT EXISTS `vps_operating_systems` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `os_id`          VARCHAR(60) NOT NULL,
  `os_name`        VARCHAR(190) NOT NULL,
  `os_icon`        VARCHAR(500) DEFAULT NULL,
  `distro`         VARCHAR(120) DEFAULT NULL,
  `is_active`      TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vpsos_oid` (`os_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Đơn VPS ----------
CREATE TABLE IF NOT EXISTS `vps_orders` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        BIGINT UNSIGNED NOT NULL,
  `remote_order`   VARCHAR(60) DEFAULT NULL,
  `vps_id`         VARCHAR(120) DEFAULT NULL,
  `order_code`     VARCHAR(120) DEFAULT NULL,
  `product_id`     VARCHAR(120) DEFAULT NULL,
  `product_name`   VARCHAR(300) DEFAULT NULL,
  `billing_cycle`  VARCHAR(60) DEFAULT NULL,
  `os_id`          VARCHAR(60) DEFAULT NULL,
  `os_name`        VARCHAR(190) DEFAULT NULL,
  `quantity`       INT NOT NULL DEFAULT 1,
  `addon_cpu`      INT NOT NULL DEFAULT 0,
  `addon_ram`      INT NOT NULL DEFAULT 0,
  `addon_disk`     INT NOT NULL DEFAULT 0,
  `hostname`       VARCHAR(190) DEFAULT NULL,
  `ip`             VARCHAR(120) DEFAULT NULL,
  `username`       VARCHAR(190) DEFAULT NULL,
  `password`       VARCHAR(500) DEFAULT NULL,
  `price`          DECIMAL(18,4) NOT NULL DEFAULT 0,
  `amount`         DECIMAL(18,4) NOT NULL DEFAULT 0,
  `status`         VARCHAR(40) NOT NULL DEFAULT 'pending',
  `vps_status`     VARCHAR(60) DEFAULT NULL,
  `date_create`    VARCHAR(60) DEFAULT NULL,
  `next_due_date`  VARCHAR(60) DEFAULT NULL,
  `idempotency_key` VARCHAR(190) DEFAULT NULL,
  `message`        VARCHAR(500) DEFAULT NULL,
  `raw`            TEXT DEFAULT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vpso_remote` (`remote_order`),
  KEY `idx_vpso_user` (`user_id`,`created_at`),
  KEY `idx_vpso_status` (`status`),
  KEY `idx_vpso_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- VPS đang quản lý ----------
CREATE TABLE IF NOT EXISTS `vps_instances` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        BIGINT UNSIGNED NOT NULL,
  `vps_id`         VARCHAR(120) NOT NULL,
  `order_code`     VARCHAR(120) DEFAULT NULL,
  `product_id`     VARCHAR(120) DEFAULT NULL,
  `product_name`   VARCHAR(300) DEFAULT NULL,
  `ip`             VARCHAR(120) DEFAULT NULL,
  `main_ipv4`      VARCHAR(120) DEFAULT NULL,
  `username`       VARCHAR(190) DEFAULT NULL,
  `password`       VARCHAR(500) DEFAULT NULL,
  `os`             VARCHAR(190) DEFAULT NULL,
  `cpu`            INT NOT NULL DEFAULT 0,
  `ram`            INT NOT NULL DEFAULT 0,
  `disk`           INT NOT NULL DEFAULT 0,
  `text_config`    VARCHAR(190) DEFAULT NULL,
  `type_vps`       VARCHAR(60) DEFAULT NULL,
  `billing_cycle`  VARCHAR(60) DEFAULT NULL,
  `day_left`       INT NOT NULL DEFAULT 0,
  `vps_status`     VARCHAR(60) DEFAULT 'unknown',
  `date_create`    VARCHAR(60) DEFAULT NULL,
  `next_due_date`  VARCHAR(60) DEFAULT NULL,
  `intro`          VARCHAR(300) DEFAULT NULL,
  `last_sync_at`   DATETIME DEFAULT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vpsi_vpsid` (`vps_id`),
  KEY `idx_vpsi_user` (`user_id`),
  KEY `idx_vpsi_status` (`vps_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Cấu hình dịch vụ ----------
-- Mở rộng ENUM loại giao dịch ví để phục vụ dịch vụ VPS
-- (đặt VPS = vps_order, hoàn tiền khi lỗi = vps_refund)
ALTER TABLE `wallet_transactions`
  MODIFY COLUMN `type` ENUM(
    'deposit','withdraw','bet','win','refund','bonus','adjustment','giftcode',
    'vps_order','vps_refund','vps_renew','vps_addon'
  ) NOT NULL;

INSERT INTO `settings` (`name`,`value`,`group_name`,`input_type`) VALUES
('xvps_base_url',   'https://api.xvps.vn',            'XVPS', 'text'),
('xvps_service_on', '1',                            'XVPS', 'text'),
('xvps_api_username', '',                           'XVPS', 'text'),
('xvps_api_app',    '',                             'XVPS', 'text'),
('xvps_api_secret', '',                             'XVPS', 'password'),
('xvps_markup',     '0',                            'XVPS', 'text'),
('xvps_vat_percent','0',                            'XVPS', 'text'),
('xvps_auto_sync',  '1',                            'XVPS', 'text')
ON DUPLICATE KEY UPDATE `group_name`=VALUES(`group_name`);

-- ---------- Danh mục dịch vụ ----------
INSERT INTO `service_catalog` (`code`,`name`,`group_name`,`description`,`icon`,`route`,`sort_order`) VALUES
('vps', 'VPS VMware — Thuê máy chủ ảo', 'Hạ tầng', 'Thuê VPS VMware theo tháng/quý/năm qua API XVPS — trừ ví chính, quản lý start/stop/rebuild/renew ngay trên web.', 'fa-server', '/services/vps', 40)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `group_name`=VALUES(`group_name`), `description`=VALUES(`description`), `icon`=VALUES(`icon`), `route`=VALUES(`route`), `sort_order`=VALUES(`sort_order`);
