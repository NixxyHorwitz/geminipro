-- Google AI Pro Sales Platform — Database Schema
-- Run this after setup wizard installs the database

SET NAMES utf8mb4;
SET time_zone = '+07:00';

-- -------------------------------------------------------
-- Table: config
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `config` (
  `key`        VARCHAR(100) NOT NULL PRIMARY KEY,
  `value`      TEXT         NULL,
  `updated_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- Table: users
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `email`        VARCHAR(255) NOT NULL UNIQUE,
  `name`         VARCHAR(255) NULL,
  `phone`        VARCHAR(30)  NULL,
  `method`       ENUM('sso','link') NOT NULL DEFAULT 'link',
  `sso_provider` VARCHAR(50)  NULL,
  `sso_id`       VARCHAR(255) NULL,
  `created_at`   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- Table: orders
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `orders` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `order_code`      VARCHAR(20)  NOT NULL UNIQUE,
  `user_id`         INT UNSIGNED NULL,
  `email`           VARCHAR(255) NOT NULL,
  `amount`          INT UNSIGNED NOT NULL DEFAULT 309000,
  `method`          ENUM('sso','link') NOT NULL DEFAULT 'link',
  `sso_email`       VARCHAR(255) NULL,
  `activation_email`VARCHAR(255) NULL,
  `status`          ENUM('pending','paid','confirmed','rejected','expired') NOT NULL DEFAULT 'pending',
  `qris_data`       TEXT         NULL,
  `payment_proof`   TEXT         NULL,
  `confirmed_at`    TIMESTAMP    NULL,
  `rejected_reason` TEXT         NULL,
  `ip_address`      VARCHAR(45)  NULL,
  `user_agent`      TEXT         NULL,
  `expires_at`      TIMESTAMP    NOT NULL,
  `created_at`      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- Table: activation_links
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `activation_links` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `order_id`   INT UNSIGNED NOT NULL,
  `token`      VARCHAR(64)  NOT NULL UNIQUE,
  `email`      VARCHAR(255) NOT NULL,
  `used`       TINYINT(1)   NOT NULL DEFAULT 0,
  `used_at`    TIMESTAMP    NULL,
  `expires_at` TIMESTAMP    NOT NULL,
  `created_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- Table: traffic_logs
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `traffic_logs` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `session_id`  VARCHAR(64)  NULL,
  `page`        VARCHAR(255) NOT NULL,
  `action`      VARCHAR(100) NULL,
  `ip_address`  VARCHAR(45)  NULL,
  `country`     VARCHAR(5)   NULL,
  `user_agent`  TEXT         NULL,
  `referer`     TEXT         NULL,
  `data`        JSON         NULL,
  `created_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- Table: bot_sessions
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bot_sessions` (
  `chat_id`    BIGINT       NOT NULL PRIMARY KEY,
  `state`      VARCHAR(100) NULL,
  `data`       JSON         NULL,
  `updated_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- Table: qris_templates
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `qris_templates` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `raw_string`   TEXT         NOT NULL,
  `merchant_name`VARCHAR(255) NULL,
  `image_path`   VARCHAR(255) NULL,
  `active`       TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default config values
INSERT IGNORE INTO `config` (`key`, `value`) VALUES
('setup_complete', '0'),
('product_price', '309000'),
('product_name', 'Google AI Pro'),
('product_duration', '12'),
('payment_timeout_minutes', '15'),
('site_url', ''),
('telegram_bot_token', ''),
('telegram_admin_chat_id', ''),
('telegram_webhook_secret', ''),
('google_client_id', ''),
('google_client_secret', ''),
('smtp_host', ''),
('smtp_port', '587'),
('smtp_user', ''),
('smtp_pass', ''),
('smtp_from', ''),
('smtp_from_name', 'Google AI Pro');
