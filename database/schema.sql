-- ===========================================================
-- CASINO PLATFORM - Database Schema
-- Engine: InnoDB / utf8mb4
-- ===========================================================

CREATE DATABASE IF NOT EXISTS `casino_platform`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `casino_platform`;

-- ---------- Users ----------
CREATE TABLE IF NOT EXISTS `users` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`       VARCHAR(50)  NOT NULL,
  `email`          VARCHAR(150) NOT NULL,
  `password_hash`  VARCHAR(255) NOT NULL,
  `full_name`      VARCHAR(150) DEFAULT NULL,
  `phone`          VARCHAR(30)  DEFAULT NULL,
  `role`           ENUM('user','admin','operator') NOT NULL DEFAULT 'user',
  `status`         ENUM('active','inactive','banned') NOT NULL DEFAULT 'active',
  `balance`        DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `bonus_balance`  DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `vip_level`      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `total_deposit`  DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `total_withdraw` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `last_login_at`  DATETIME DEFAULT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Wallet transactions (ledger) ----------
CREATE TABLE IF NOT EXISTS `wallet_transactions` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        BIGINT UNSIGNED NOT NULL,
  `type`           ENUM('deposit','withdraw','bet','win','refund','bonus','adjustment','giftcode','vps_order','vps_refund','vps_renew','vps_addon') NOT NULL,
  `amount`         DECIMAL(18,2) NOT NULL,
  `balance_after`  DECIMAL(18,2) NOT NULL,
  `bonus_after`    DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `ref_type`       VARCHAR(40) DEFAULT NULL,
  `ref_id`         VARCHAR(120) DEFAULT NULL,
  `description`    VARCHAR(255) DEFAULT NULL,
  `metadata`       JSON DEFAULT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wt_user` (`user_id`, `created_at`),
  KEY `idx_wt_type` (`type`),
  KEY `idx_wt_ref` (`ref_type`, `ref_id`),
  CONSTRAINT `fk_wt_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Deposit requests ----------
CREATE TABLE IF NOT EXISTS `deposits` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        BIGINT UNSIGNED NOT NULL,
  `amount`         DECIMAL(18,2) NOT NULL,
  `bonus_amount`   DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `method`         VARCHAR(40) NOT NULL,
  `transaction_code` VARCHAR(80) DEFAULT NULL,
  `status`         ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `note`           VARCHAR(255) DEFAULT NULL,
  `processed_by`   BIGINT UNSIGNED DEFAULT NULL,
  `processed_at`   DATETIME DEFAULT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_dep_user` (`user_id`),
  KEY `idx_dep_status` (`status`),
  CONSTRAINT `fk_dep_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Deposit transfer codes (VietQR) ----------
-- Mỗi lần tạo QR sinh 1 mã nội dung chuyển khoản riêng để admin đối soát.
CREATE TABLE IF NOT EXISTS `deposit_codes` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `deposit_id`     BIGINT UNSIGNED NOT NULL,
  `user_id`        BIGINT UNSIGNED NOT NULL,
  `code`           VARCHAR(40) NOT NULL,
  `amount`         DECIMAL(18,2) NOT NULL,
  `qr_data`        TEXT DEFAULT NULL,
  `qr_image`       MEDIUMTEXT DEFAULT NULL,
  `qr_url`         VARCHAR(500) DEFAULT NULL,
  `bank_code`      VARCHAR(20) DEFAULT NULL,
  `status`         ENUM('active','used','expired') NOT NULL DEFAULT 'active',
  `expires_at`     DATETIME DEFAULT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dc_code` (`code`),
  KEY `idx_dc_user` (`user_id`),
  KEY `idx_dc_status` (`status`),
  CONSTRAINT `fk_dc_deposit` FOREIGN KEY (`deposit_id`) REFERENCES `deposits`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dc_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- ---------- Withdraw requests ----------
CREATE TABLE IF NOT EXISTS `withdrawals` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        BIGINT UNSIGNED NOT NULL,
  `amount`         DECIMAL(18,2) NOT NULL,
  `fee`            DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `net_amount`     DECIMAL(18,2) NOT NULL,
  `bank_name`      VARCHAR(120) DEFAULT NULL,
  `account_number` VARCHAR(60) DEFAULT NULL,
  `account_name`   VARCHAR(150) DEFAULT NULL,
  `status`         ENUM('pending','processing','paid','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `note`           VARCHAR(255) DEFAULT NULL,
  `processed_by`   BIGINT UNSIGNED DEFAULT NULL,
  `processed_at`   DATETIME DEFAULT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wd_user` (`user_id`),
  KEY `idx_wd_status` (`status`),
  CONSTRAINT `fk_wd_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Gift codes ----------
CREATE TABLE IF NOT EXISTS `giftcodes` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`           VARCHAR(60) NOT NULL,
  `amount`         DECIMAL(18,2) NOT NULL,
  `bonus_amount`   DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `min_deposit`    DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `max_uses`       INT UNSIGNED NOT NULL DEFAULT 1,
  `used_count`     INT UNSIGNED NOT NULL DEFAULT 0,
  `expires_at`     DATETIME DEFAULT NULL,
  `is_active`      TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_gift_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Providers (sảnh game) ----------
CREATE TABLE IF NOT EXISTS `providers` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`           VARCHAR(40) NOT NULL,
  `name`           VARCHAR(120) NOT NULL,
  `driver`         VARCHAR(60) NOT NULL,
  `logo`           VARCHAR(255) DEFAULT NULL,
  `base_url`       VARCHAR(255) DEFAULT NULL,
  `api_key`        VARCHAR(255) DEFAULT NULL,
  `api_secret`     VARCHAR(255) DEFAULT NULL,
  `wallet_endpoint` VARCHAR(255) DEFAULT NULL,
  `callback_url`   VARCHAR(255) DEFAULT NULL,
  `status`         ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `sort_order`     INT NOT NULL DEFAULT 0,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_provider_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Games ----------
CREATE TABLE IF NOT EXISTS `games` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `provider_id`    INT UNSIGNED NOT NULL,
  `provider_game_id` VARCHAR(80) NOT NULL,
  `slug`           VARCHAR(160) NOT NULL,
  `name`           VARCHAR(200) NOT NULL,
  `description`    TEXT DEFAULT NULL,
  `icon`           VARCHAR(500) DEFAULT NULL,
  `thumb`          VARCHAR(500) DEFAULT NULL,
  `embed_url`      VARCHAR(500) DEFAULT NULL,
  `demo_url`       VARCHAR(500) DEFAULT NULL,
  `game_type`      ENUM('slot','live','card','table','crash','jackpot','other') NOT NULL DEFAULT 'slot',
  `is_free`        TINYINT(1) NOT NULL DEFAULT 0,
  `is_featured`    TINYINT(1) NOT NULL DEFAULT 0,
  `is_hot`         TINYINT(1) NOT NULL DEFAULT 0,
  `is_new`         TINYINT(1) NOT NULL DEFAULT 0,
  `rtp`            DECIMAL(5,2) DEFAULT NULL,
  `min_bet`        DECIMAL(18,2) NOT NULL DEFAULT 0,
  `max_bet`        DECIMAL(18,2) NOT NULL DEFAULT 0,
  `sort_order`     INT NOT NULL DEFAULT 0,
  `game_config`    JSON DEFAULT NULL,
  `status`         ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_game_provider_slug` (`provider_id`, `slug`),
  UNIQUE KEY `uq_game_provider_gid` (`provider_id`, `provider_game_id`),
  KEY `idx_game_slug` (`slug`),
  KEY `idx_game_flags` (`is_featured`,`is_hot`,`is_new`,`is_free`),
  KEY `idx_game_type` (`game_type`),
  CONSTRAINT `fk_game_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Packages (gói chơi / trải nghiệm) ----------
CREATE TABLE IF NOT EXISTS `packages` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`           VARCHAR(150) NOT NULL,
  `slug`           VARCHAR(160) NOT NULL,
  `description`    VARCHAR(255) DEFAULT NULL,
  `duration_minutes` INT UNSIGNED NOT NULL,
  `price`          DECIMAL(18,2) NOT NULL,
  `original_price` DECIMAL(18,2) DEFAULT NULL,
  `provider_code`  VARCHAR(40) DEFAULT NULL,
  `is_free_trial`  TINYINT(1) NOT NULL DEFAULT 0,
  `trial_limit_per_user` INT UNSIGNED NOT NULL DEFAULT 1,
  `is_active`      TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order`     INT NOT NULL DEFAULT 0,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pkg_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- User packages (gói đã mua / đang hiệu lực) ----------
CREATE TABLE IF NOT EXISTS `user_packages` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        BIGINT UNSIGNED NOT NULL,
  `package_id`     BIGINT UNSIGNED NOT NULL,
  `session_token`  VARCHAR(120) DEFAULT NULL,
  `started_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at`     DATETIME NOT NULL,
  `remaining_seconds` INT UNSIGNED NOT NULL DEFAULT 0,
  `status`         ENUM('active','expired','revoked') NOT NULL DEFAULT 'active',
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_up_user` (`user_id`, `status`),
  KEY `idx_up_token` (`session_token`),
  CONSTRAINT `fk_up_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_up_pkg` FOREIGN KEY (`package_id`) REFERENCES `packages`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Game sessions (phiên chơi) ----------
CREATE TABLE IF NOT EXISTS `game_sessions` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        BIGINT UNSIGNED NOT NULL,
  `game_id`        BIGINT UNSIGNED NOT NULL,
  `provider_id`    INT UNSIGNED NOT NULL,
  `session_token`  VARCHAR(120) NOT NULL,
  `operator_token` VARCHAR(120) DEFAULT NULL,
  `balance_before` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `balance_after`  DECIMAL(18,2) NOT NULL DEFAULT 0,
  `wagered`        DECIMAL(18,2) NOT NULL DEFAULT 0,
  `status`         ENUM('active','closed','expired') NOT NULL DEFAULT 'active',
  `started_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ended_at`       DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_gs_token` (`session_token`),
  KEY `idx_gs_user` (`user_id`, `status`),
  CONSTRAINT `fk_gs_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gs_game` FOREIGN KEY (`game_id`) REFERENCES `games`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Game rounds (vòng quay, đồng bộ kết quả về ví) ----------
CREATE TABLE IF NOT EXISTS `game_rounds` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_id`     BIGINT UNSIGNED NOT NULL,
  `round_id`       VARCHAR(120) DEFAULT NULL,
  `bet`            DECIMAL(18,2) NOT NULL DEFAULT 0,
  `win`            DECIMAL(18,2) NOT NULL DEFAULT 0,
  `balance_after`  DECIMAL(18,2) NOT NULL DEFAULT 0,
  `raw`            JSON DEFAULT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_gr_session` (`session_id`),
  KEY `idx_gr_round` (`round_id`),
  CONSTRAINT `fk_gr_session` FOREIGN KEY (`session_id`) REFERENCES `game_sessions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- CSO / affiliate (đối tác) ----------
CREATE TABLE IF NOT EXISTS `cso_accounts` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        BIGINT UNSIGNED NOT NULL,
  `code`           VARCHAR(40) NOT NULL,
  `name`           VARCHAR(150) NOT NULL,
  `commission_rate` DECIMAL(6,3) NOT NULL DEFAULT 0.000,
  `total_revenue`  DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `total_commission` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `is_active`      TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cso_code` (`code`),
  UNIQUE KEY `uq_cso_user` (`user_id`),
  CONSTRAINT `fk_cso_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- CSO commissions ----------
CREATE TABLE IF NOT EXISTS `cso_commissions` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cso_id`         BIGINT UNSIGNED NOT NULL,
  `user_id`        BIGINT UNSIGNED NOT NULL,
  `source_user_id` BIGINT UNSIGNED NOT NULL,
  `amount`         DECIMAL(18,2) NOT NULL,
  `rate`           DECIMAL(6,3) NOT NULL DEFAULT 0.000,
  `type`           ENUM('deposit','bet','win','adjustment') NOT NULL DEFAULT 'deposit',
  `status`         ENUM('pending','approved','paid') NOT NULL DEFAULT 'pending',
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cc_cso` (`cso_id`, `status`),
  CONSTRAINT `fk_cc_cso` FOREIGN KEY (`cso_id`) REFERENCES `cso_accounts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Referrals (user giới thiệu) ----------
CREATE TABLE IF NOT EXISTS `referrals` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `referrer_id`    BIGINT UNSIGNED NOT NULL,
  `referred_id`    BIGINT UNSIGNED NOT NULL,
  `bonus_amount`   DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ref_referred` (`referred_id`),
  KEY `idx_ref_referrer` (`referrer_id`),
  CONSTRAINT `fk_ref_referrer` FOREIGN KEY (`referrer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ref_referred` FOREIGN KEY (`referred_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- VIP tiers ----------
CREATE TABLE IF NOT EXISTS `vip_tiers` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `level`          TINYINT UNSIGNED NOT NULL,
  `name`           VARCHAR(60) NOT NULL,
  `deposit_required` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `bonus_rate`     DECIMAL(6,3) NOT NULL DEFAULT 0.000,
  `cashback_rate`  DECIMAL(6,3) NOT NULL DEFAULT 0.000,
  `icon`           VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vip_level` (`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Admin settings (key/value) ----------
CREATE TABLE IF NOT EXISTS `settings` (
  `name`           VARCHAR(80) NOT NULL,
  `value`          TEXT DEFAULT NULL,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Admin roles (phân quyền nhiều vai trò) ----------
CREATE TABLE IF NOT EXISTS `admin_roles` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`           VARCHAR(80) NOT NULL,
  `slug`           VARCHAR(40) NOT NULL,
  `permissions`    JSON DEFAULT NULL,
  `description`    VARCHAR(255) DEFAULT NULL,
  `is_active`      TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ar_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Payment banks (tài khoản nhận tiền cho VietQR) ----------
CREATE TABLE IF NOT EXISTS `payment_banks` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bank_code`      VARCHAR(20) NOT NULL,
  `bank_name`      VARCHAR(150) NOT NULL,
  `account_no`     VARCHAR(40) NOT NULL,
  `account_name`   VARCHAR(150) NOT NULL,
  `acq_id`         VARCHAR(20) NOT NULL,
  `logo`           VARCHAR(500) DEFAULT NULL,
  `is_active`      TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order`     INT NOT NULL DEFAULT 0,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pb_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- API packages (gói thuê API cho dev) ----------
CREATE TABLE IF NOT EXISTS `api_packages` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`           VARCHAR(150) NOT NULL,
  `slug`           VARCHAR(160) NOT NULL,
  `description`    VARCHAR(255) DEFAULT NULL,
  `duration_days`  INT UNSIGNED NOT NULL DEFAULT 30,
  `price`          DECIMAL(18,2) NOT NULL DEFAULT 0,
  `daily_quota`    INT UNSIGNED NOT NULL DEFAULT 100000,
  `rate_limit`     INT UNSIGNED NOT NULL DEFAULT 60,
  `scopes`         JSON DEFAULT NULL,
  `is_active`      TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order`     INT NOT NULL DEFAULT 0,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ap_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- API clients (khách hàng đấu API) ----------
CREATE TABLE IF NOT EXISTS `api_clients` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        BIGINT UNSIGNED NOT NULL,
  `package_id`     INT UNSIGNED DEFAULT NULL,
  `name`           VARCHAR(150) NOT NULL,
  `email`          VARCHAR(150) DEFAULT NULL,
  `website`        VARCHAR(255) DEFAULT NULL,
  `key_hash`       VARCHAR(255) NOT NULL,
  `key_prefix`     VARCHAR(16) NOT NULL,
  `key_encrypted`  TEXT DEFAULT NULL,
  `status`         ENUM('active','suspended','expired') NOT NULL DEFAULT 'active',
  `expires_at`     DATETIME DEFAULT NULL,
  `quota_used`     INT UNSIGNED NOT NULL DEFAULT 0,
  `quota_limit`    INT UNSIGNED NOT NULL DEFAULT 100000,
  `rate_limit`     INT UNSIGNED NOT NULL DEFAULT 60,
  `last_used_at`   DATETIME DEFAULT NULL,
  `last_ip`        VARCHAR(45) DEFAULT NULL,
  `total_requests` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ac_hash` (`key_hash`),
  KEY `idx_ac_user` (`user_id`),
  KEY `idx_ac_prefix` (`key_prefix`),
  KEY `idx_ac_status` (`status`,`expires_at`),
  CONSTRAINT `fk_ac_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- API client logs ----------
CREATE TABLE IF NOT EXISTS `api_client_logs` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `client_id`      BIGINT UNSIGNED NOT NULL,
  `endpoint`       VARCHAR(120) NOT NULL,
  `method`         VARCHAR(10) NOT NULL,
  `status_code`    SMALLINT UNSIGNED NOT NULL DEFAULT 200,
  `ip`             VARCHAR(45) DEFAULT NULL,
  `duration_ms`    INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_acl_client` (`client_id`,`created_at`),
  CONSTRAINT `fk_acl_client` FOREIGN KEY (`client_id`) REFERENCES `api_clients`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Game asset cache (ảnh tải về project) ----------
CREATE TABLE IF NOT EXISTS `game_assets` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `provider_id`    INT UNSIGNED DEFAULT NULL,
  `provider_game_id` VARCHAR(80) NOT NULL,
  `kind`           VARCHAR(20) NOT NULL DEFAULT 'icon',
  `remote_url`     VARCHAR(500) NOT NULL,
  `local_path`     VARCHAR(255) DEFAULT NULL,
  `hash`           VARCHAR(64) DEFAULT NULL,
  `size_bytes`     INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ga_asset` (`provider_game_id`,`kind`),
  KEY `idx_ga_provider` (`provider_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Audit log ----------
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        BIGINT UNSIGNED DEFAULT NULL,
  `action`         VARCHAR(80) NOT NULL,
  `entity`         VARCHAR(60) DEFAULT NULL,
  `entity_id`      VARCHAR(80) DEFAULT NULL,
  `detail`         JSON DEFAULT NULL,
  `ip`             VARCHAR(45) DEFAULT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_al_user` (`user_id`),
  KEY `idx_al_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Seed ----------
INSERT IGNORE INTO `settings` (`name`, `value`) VALUES
  ('site_name', 'Casino Portal'),
  ('site_logo', ''),
  ('deposit_bonus_rate', '0.10'),
  ('trial_minutes', '5'),
  ('trial_price', '0'),
  ('require_deposit_for_play', '1'),
  ('currency', 'VND'),
  ('currency_symbol', 'đ');

INSERT IGNORE INTO `vip_tiers` (`level`,`name`,`deposit_required`,`bonus_rate`,`cashback_rate`) VALUES
  (0,'Thường',0,0.000,0.000),
  (1,'Bạc',1000000,0.010,0.010),
  (2,'Vàng',5000000,0.020,0.015),
  (3,'Bạch kim',20000000,0.030,0.020),
  (4,'Kim cương',100000000,0.050,0.030);
