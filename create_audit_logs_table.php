<?php
/**
 * 创建audit_logs表的脚本
 */

require_once 'config.php';

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception("数据库连接失败: " . $conn->connect_error);
    }

    $sql = "CREATE TABLE IF NOT EXISTS `ac_audit_logs` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($sql) === TRUE) {
        echo "audit_logs表创建成功\n";
    } else {
        throw new Exception("创建表失败: " . $conn->error);
    }

    // 创建two_factor_auth表
    $sql = "CREATE TABLE IF NOT EXISTS `ac_two_factor_auth` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `secret` TEXT NOT NULL COMMENT '加密存储的TOTP密钥',
        `recovery_codes` TEXT COMMENT '加密的恢复代码JSON数组',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`user_id`) REFERENCES `ac_users`(`id`) ON DELETE CASCADE,
        UNIQUE KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($sql) === TRUE) {
        echo "two_factor_auth表创建成功\n";
    } else {
        throw new Exception("创建two_factor_auth表失败: " . $conn->error);
    }

    // 创建recovery_codes表
    $sql = "CREATE TABLE IF NOT EXISTS `ac_recovery_codes` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($sql) === TRUE) {
        echo "recovery_codes表创建成功\n";
    } else {
        throw new Exception("创建recovery_codes表失败: " . $conn->error);
    }

    $conn->close();
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    exit(1);
}

echo "数据库表检查完成\n";
