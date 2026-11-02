-- =====================================================================
-- MIGRATION V5 — kế toán, tools registry, mở rộng hạ tầng
-- =====================================================================

-- ---------- Kế toán ----------
CREATE TABLE IF NOT EXISTS `accounting_documents` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `doc_no`        VARCHAR(32) NOT NULL,
  `doc_type`      ENUM('journal','receipt','payment','invoice','bill') NOT NULL DEFAULT 'journal',
  `doc_date`      DATE NOT NULL,
  `partner`       VARCHAR(150) DEFAULT NULL,
  `note`          TEXT DEFAULT NULL,
  `total_debit`   DECIMAL(18,2) NOT NULL DEFAULT 0,
  `total_credit`  DECIMAL(18,2) NOT NULL DEFAULT 0,
  `user_id`       INT UNSIGNED DEFAULT NULL,
  `status`        ENUM('draft','posted','locked') NOT NULL DEFAULT 'posted',
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ad_docno` (`doc_no`),
  KEY `idx_ad_date` (`doc_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `accounting_lines` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `document_id`  INT UNSIGNED NOT NULL,
  `account_code` VARCHAR(20) NOT NULL,
  `account_name` VARCHAR(150) DEFAULT NULL,
  `debit`        DECIMAL(18,2) NOT NULL DEFAULT 0,
  `credit`       DECIMAL(18,2) NOT NULL DEFAULT 0,
  `note`         VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_al_doc` (`document_id`),
  KEY `idx_al_acc` (`account_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `accounting_accounts` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`       VARCHAR(20) NOT NULL,
  `name`       VARCHAR(150) NOT NULL,
  `parent_code` VARCHAR(20) DEFAULT NULL,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_aa_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Backup định kỳ (system_backups đã có sẵn cột) ----------
-- Cột hiện có: scope, label, driver, path, size_bytes, checksum, status, error, created_at

-- ---------- Công cụ: kết quả ----------
CREATE TABLE IF NOT EXISTS `tool_results` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED DEFAULT NULL,
  `tool_code`  VARCHAR(50) NOT NULL,
  `input`      TEXT DEFAULT NULL,
  `output`     TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tr_code` (`tool_code`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Hạ tầng lưu trữ (driver) ----------
CREATE TABLE IF NOT EXISTS `storage_drivers` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`       VARCHAR(40) NOT NULL,
  `name`       VARCHAR(120) NOT NULL,
  `kind`       ENUM('local','s3','drive','ftp','backblaze') NOT NULL DEFAULT 'local',
  `endpoint`   VARCHAR(500) DEFAULT NULL,
  `secret`     VARCHAR(500) DEFAULT NULL,
  `bucket`     VARCHAR(150) DEFAULT NULL,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `is_default` TINYINT(1) NOT NULL DEFAULT 0,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sd_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Dịch vụ social / buff ----------
CREATE TABLE IF NOT EXISTS `social_services` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`       VARCHAR(50) NOT NULL,
  `name`       VARCHAR(150) NOT NULL,
  `platform`   VARCHAR(40) NOT NULL DEFAULT 'facebook',
  `description` VARCHAR(500) DEFAULT NULL,
  `icon`       VARCHAR(80) DEFAULT NULL,
  `unit_price` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `min_qty`    INT UNSIGNED NOT NULL DEFAULT 100,
  `max_qty`    INT UNSIGNED NOT NULL DEFAULT 100000,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ss_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `social_orders` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `service_id` INT UNSIGNED DEFAULT NULL,
  `target_url` VARCHAR(500) NOT NULL,
  `quantity`   INT UNSIGNED NOT NULL DEFAULT 0,
  `amount`     DECIMAL(18,2) NOT NULL DEFAULT 0,
  `status`     ENUM('pending','running','done','failed','refunded') NOT NULL DEFAULT 'pending',
  `note`       VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_so_user` (`user_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Dịch vụ sale / spam ----------
CREATE TABLE IF NOT EXISTS `sale_accounts` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `platform`   VARCHAR(40) NOT NULL DEFAULT 'telegram',
  `account`    VARCHAR(150) NOT NULL,
  `secret`     VARCHAR(500) DEFAULT NULL,
  `proxy_id`   INT UNSIGNED DEFAULT NULL,
  `daily_limit` INT UNSIGNED NOT NULL DEFAULT 500,
  `sent_today` INT UNSIGNED NOT NULL DEFAULT 0,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sa_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sale_campaigns` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `account_id`  INT UNSIGNED DEFAULT NULL,
  `message`     TEXT NOT NULL,
  `target_list` TEXT DEFAULT NULL,
  `total`       INT UNSIGNED NOT NULL DEFAULT 0,
  `sent`        INT UNSIGNED NOT NULL DEFAULT 0,
  `failed`      INT UNSIGNED NOT NULL DEFAULT 0,
  `status`      ENUM('draft','running','paused','done','stopped') NOT NULL DEFAULT 'draft',
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sc_user` (`user_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Seed: tài khoản kế toán ----------
INSERT INTO `accounting_accounts` (`code`,`name`) VALUES
('111','Tiền mặt'),('112','Tiền gửi ngân hàng'),('113','Tiền đang chuyển'),
('131','Phải thu khách hàng'),('133','Thuế GTGT được khấu trừ'),
('140','Hàng tồn kho'),('142','Chi phí dự án'),
('211','Phải trả người bán'),('214','Phải trả người lao động'),('219','Phải trả khác'),
('401','Vốn góp chủ sở hữu'),('411','Lợi nhuận chưa phân phối'),
('421','Doanh thu bán hàng'),('511','Giá vốn bán hàng'),
('632','Chi phí bán hàng'),('642','Chi phí quản lý doanh nghiệp'),
('711','Thuế TNDN'),('731','Chi phát sinh khác')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- ---------- Seed: storage driver ----------
INSERT INTO `storage_drivers` (`code`,`name`,`kind`,`is_active`,`is_default`,`sort_order`) VALUES
('local', 'Ổ đĩa máy chủ',   'local', 1, 1, 1),
('s3',    'Amazon S3 / MinIO','s3',    1, 0, 2),
('drive', 'Google Drive',     'drive', 1, 0, 3),
('ftp',   'FTP / SFTP',       'ftp',   1, 0, 4)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `is_active`=VALUES(`is_active`);

-- ---------- Seed: dịch vụ social ----------
INSERT INTO `social_services` (`code`,`name`,`platform`,`description`,`icon`,`unit_price`,`min_qty`,`max_qty`,`sort_order`) VALUES
('fb-like',      'Like Facebook',        'facebook','Tăng lượt thích bằng hồ sơ thật, không giả.',      'fa-thumbs-up',   1500, 100, 500000, 1),
('fb-comment',   'Comment Facebook',     'facebook','Comment chất lượng, có thể kèm ảnh.',              'fa-comment',    12000, 50, 10000, 2),
('fb-follow',    'Follow Facebook',      'facebook','Follow hồ sơ theo tên hoặc URL.',                    'fa-user-plus',  3500, 100, 200000, 3),
('tiktok-view',  'View TikTok',          'tiktok',  'Lượt xem video TikTok, tốc độ tự nhiên.',            'fa-eye',        800, 1000, 5000000, 4),
('tiktok-like',  'Like TikTok',          'tiktok',  'Like TikTok theo video.',                             'fa-heart',      1200, 100, 1000000, 5),
('ig-like',      'Like Instagram',       'instagram','Like Instagram theo ảnh.',                          'fa-heart',      1500, 100, 500000, 6),
('yt-view',      'View YouTube',         'youtube', 'Lượt xem YouTube, giữ độ watch time thật.',           'fa-play',       1000, 1000, 5000000, 7),
('yt-sub',       'Subscribe YouTube',    'youtube', 'Đăng ký kênh YouTube.',                               'fa-youtube',    45000, 100, 50000, 8),
('web-traffic',  'Traffic website',      'web',     'Lượt truy cập từ nhiều IP vị trí khác nhau.',         'fa-globe',      2500, 500, 1000000, 9),
('review',       'Đánh giá Google',      'google',  'Đánh giá sản phẩm/dịch vụ trên Google.',             'fa-star',      18000, 10, 5000, 10)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `unit_price`=VALUES(`unit_price`), `description`=VALUES(`description`);
