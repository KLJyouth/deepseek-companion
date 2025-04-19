-- AI Companion 完整数据库结构
-- 创建时间: 2025-04-17

-- 1. 首先创建基础用户表
CREATE TABLE IF NOT EXISTS `ac_users` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `username` varchar(50) NOT NULL,
    `email` varchar(100) NOT NULL,
    `password_hash` varchar(255) NOT NULL,
    `salt` varchar(32) NOT NULL,
    `status` tinyint(1) DEFAULT 1 COMMENT '1-活跃,0-禁用',
    `last_login` datetime DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `username` (`username`),
    UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. 创建审计日志表
CREATE TABLE IF NOT EXISTS `ac_audit_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) DEFAULT NULL,
    `action` varchar(50) NOT NULL,
    `description` text,
    `ip_address` varchar(45) DEFAULT NULL,
    `user_agent` varchar(255) DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_action` (`action`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. 创建双因素认证表
CREATE TABLE IF NOT EXISTS `ac_two_factor_auth` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `secret` TEXT NOT NULL COMMENT '加密存储的TOTP密钥',
    `recovery_codes` TEXT COMMENT '加密的恢复代码JSON数组',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `ac_users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. 创建恢复代码表
CREATE TABLE IF NOT EXISTS `ac_recovery_codes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `code_hash` VARCHAR(255) NOT NULL COMMENT '哈希后的恢复代码',
    `used` TINYINT(1) DEFAULT 0 COMMENT '是否已使用',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP NULL DEFAULT NULL COMMENT '过期时间',
    FOREIGN KEY (`user_id`) REFERENCES `ac_users`(`id`) ON DELETE CASCADE,
    KEY `idx_user_id` (`user_id`),
    KEY `idx_used` (`used`),
    KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. 创建会话表
CREATE TABLE IF NOT EXISTS `ac_sessions` (
    `session_id` varchar(128) NOT NULL,
    `user_id` int(11) DEFAULT NULL,
    `ip_address` varchar(45) NOT NULL,
    `user_agent` text,
    `payload` text NOT NULL,
    `last_activity` int(11) NOT NULL,
    PRIMARY KEY (`session_id`),
    KEY `user_id` (`user_id`),
    KEY `last_activity` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
