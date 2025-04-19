-- AI伴侣数据库初始化脚本
-- 版本: 2.1
-- 更新时间: 2025-04-15

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `sessions`;
DROP TABLE IF EXISTS `api_usage`;
DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `admin_bypass_logs`;
SET FOREIGN_KEY_CHECKS = 1;

-- 用户表
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `status` tinyint NOT NULL DEFAULT '1' COMMENT '0-禁用,1-正常',
  `last_login` datetime DEFAULT NULL,
  `is_online` tinyint DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 会话表
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` int NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `payload` text NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- API调用记录
CREATE TABLE `api_usage` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `endpoint` varchar(255) NOT NULL,
  `call_count` int NOT NULL DEFAULT '1',
  `date` date NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 审计日志表
CREATE TABLE `audit_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `description` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 管理员跳过日志
CREATE TABLE `admin_bypass_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 登录尝试记录表
CREATE TABLE `login_attempts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `user_id` int DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `reason` varchar(50) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `username` (`username`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 初始化管理员账户
INSERT INTO `users` 
(`username`, `password`, `email`, `role`, `status`) 
VALUES 
('admin', '$2y$12$8eJwWqJzN4hY5bV9mQzZ3uB7d6fT2hKpL0vX1cR3yA4nZ5x7v8C9', 'admin@example.com', 'admin', 1);

-- 创建视图
CREATE VIEW `user_stats` AS
SELECT 
  u.id,
  u.username,
  COUNT(a.id) as api_calls,
  MAX(a.date) as last_active
FROM users u
LEFT JOIN api_usage a ON u.id = a.user_id
GROUP BY u.id;
