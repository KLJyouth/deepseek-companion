<?php
/**
 * 数据库一键修复脚本
 * 功能：按正确顺序创建所有数据库表，修复缺失的表
 */

// 直接输出错误信息
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "=== AI Companion 数据库修复工具 ===\n";
echo "开始时间: " . date('Y-m-d H:i:s') . "\n\n";

// 1. 加载配置
function getDbConfig() {
    // 直接从环境变量或硬编码获取配置，避免依赖config.php
    return [
        'host' => defined('DB_HOST') ? DB_HOST : 'localhost',
        'user' => defined('DB_USER') ? DB_USER : 'deepseek_gxggm_c',
        'pass' => defined('DB_PASS') ? DB_PASS : 'fJD47Xw3E4XQ',
        'name' => defined('DB_NAME') ? DB_NAME : 'ai_companion'
    ];
}

// 2. 数据库连接
function connectDatabase() {
    $config = getDbConfig();
    
    $conn = new mysqli($config['host'], $config['user'], $config['pass'], $config['name']);
    
    if ($conn->connect_error) {
        die("数据库连接失败: " . $conn->connect_error);
    }

    // 设置字符集
    if (!$conn->set_charset('utf8mb4')) {
        die("设置字符集失败: " . $conn->error);
    }

    return $conn;
}

// 3. 表创建函数
function createTables($conn) {
    $tables = [
        'ac_users' => "
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
        ",
        
        'ac_audit_logs' => "
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
        ",
        
        'ac_two_factor_auth' => "
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
        ",
        
        'ac_recovery_codes' => "
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
        ",
        
        'ac_sessions' => "
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
        "
    ];

    // 按依赖关系顺序创建表
    $order = ['ac_users', 'ac_audit_logs', 'ac_two_factor_auth', 'ac_recovery_codes', 'ac_sessions'];
    
    foreach ($order as $table) {
        echo "正在创建表: {$table}...";
        
        if ($conn->query($tables[$table]) === TRUE) {
            echo "成功\n";
        } else {
            echo "失败: " . $conn->error . "\n";
        }
    }
}

// 主程序
try {
    $conn = connectDatabase();
    createTables($conn);
    $conn->close();
    
    echo "\n数据库修复完成!\n";
    echo "结束时间: " . date('Y-m-d H:i:s') . "\n";
    
} catch (Exception $e) {
    die("\n错误: " . $e->getMessage());
}
