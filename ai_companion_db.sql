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
  `password_changed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `password_strength` tinyint DEFAULT 0 COMMENT '密码强度1-5',
  `password_history` text DEFAULT NULL COMMENT 'JSON格式的密码历史记录',
  `email` varchar(255) NOT NULL,
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `status` tinyint NOT NULL DEFAULT '1' COMMENT '0-禁用,1-正常',
  `last_login` datetime DEFAULT NULL,
  `is_online` tinyint DEFAULT '0',
  `tfa_secret` varchar(255) DEFAULT NULL COMMENT '加密的2FA密钥',
  `biometric_data` text DEFAULT NULL COMMENT '加密的生物识别数据',
  `biometric_enabled` tinyint DEFAULT '0' COMMENT '是否启用生物识别',
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

-- 管理员操作日志表
CREATE TABLE `admin_operation_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `admin_id` int NOT NULL,
  `operation_type` varchar(50) NOT NULL COMMENT '操作类型',
  `target_id` int DEFAULT NULL COMMENT '操作目标ID',
  `target_type` varchar(50) DEFAULT NULL COMMENT '操作目标类型',
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text NOT NULL,
  `operation_data` json DEFAULT NULL COMMENT '操作详情',
  `status` tinyint NOT NULL DEFAULT '1' COMMENT '1-成功,0-失败',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_admin` (`admin_id`),
  KEY `idx_operation` (`operation_type`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `fk_admin_op_log` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 系统指标表
CREATE TABLE IF NOT EXISTS `system_metrics` (
  `id` int NOT NULL AUTO_INCREMENT,
  `metric` varchar(20) NOT NULL COMMENT '指标类型',
  `value` float NOT NULL COMMENT '指标值',
  `timestamp` int NOT NULL COMMENT '时间戳',
  PRIMARY KEY (`id`),
  KEY `idx_metric` (`metric`),
  KEY `idx_timestamp` (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='系统监控指标表';

-- 小时聚合表
CREATE TABLE IF NOT EXISTS `metric_hourly` (
  `id` int NOT NULL AUTO_INCREMENT,
  `metric` varchar(20) NOT NULL,
  `timestamp` int NOT NULL,
  `avg_value` float NOT NULL,
  `min_value` float NOT NULL,
  `max_value` float NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_metric_time` (`metric`, `timestamp`),
  KEY `idx_timestamp` (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 日聚合表
CREATE TABLE IF NOT EXISTS `metric_daily` (
  `id` int NOT NULL AUTO_INCREMENT,
  `metric` varchar(20) NOT NULL,
  `timestamp` int NOT NULL,
  `avg_value` float NOT NULL,
  `min_value` float NOT NULL,
  `max_value` float NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_metric_time` (`metric`, `timestamp`),
  KEY `idx_timestamp` (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  `id` int NOT NULL AUTO_INCREMENT,
  `metric` varchar(20) NOT NULL COMMENT '指标类型',
  `value` float NOT NULL COMMENT '指标值',
  `timestamp` int NOT NULL COMMENT '时间戳',
  PRIMARY KEY (`id`),
  KEY `idx_metric` (`metric`),
  KEY `idx_timestamp` (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='系统监控指标表';

-- 服务操作日志表
CREATE TABLE `service_operation_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `admin_id` int NOT NULL,
  `service` varchar(50) NOT NULL,
  `action` varchar(50) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `user_agent` text NOT NULL,
  `data` json DEFAULT NULL,
  `timestamp` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_admin` (`admin_id`),
  KEY `idx_service` (`service`),
  KEY `idx_action` (`action`),
  KEY `idx_timestamp` (`timestamp`),
  CONSTRAINT `fk_service_log_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 初始化管理员账户
INSERT INTO `users` 
(`username`, `password`, `email`, `role`, `status`) 
VALUES 
('admin', '$2y$12$8eJwWqJzN4hY5bV9mQzZ3uB7d6fT2hKpL0vX1cR3yA4nZ5x7v8C9', 'admin@example.com', 'admin', 1);

-- WebSocket认证令牌表
CREATE TABLE IF NOT EXISTS `ws_auth_tokens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_token` (`token`),
  KEY `idx_user` (`user_id`),
  KEY `idx_expires` (`expires_at`),
  CONSTRAINT `fk_ws_token_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='WebSocket认证令牌';

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