<?php
use Libs\DatabaseHelper;

class InitDatabase {
    public function run(DatabaseHelper $db) {
        // 创建用户表
        $db->query("
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                email VARCHAR(100),
                is_admin BOOLEAN DEFAULT FALSE,
                is_locked BOOLEAN DEFAULT FALSE,
                failed_login_attempts INT DEFAULT 0,
                last_login_at DATETIME,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // 创建合同表
        $db->query("
            CREATE TABLE IF NOT EXISTS contracts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                content TEXT NOT NULL,
                created_by INT NOT NULL,
                status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
                blockchain_txid VARCHAR(100),
                blockchain_timestamp DATETIME,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (created_by) REFERENCES users(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // 创建合同签名表
        $db->query("
            CREATE TABLE IF NOT EXISTS contract_signatures (
                id INT AUTO_INCREMENT PRIMARY KEY,
                contract_id INT NOT NULL,
                user_id INT NOT NULL,
                signature TEXT NOT NULL,
                algorithm VARCHAR(20) NOT NULL,
                quantum_key_id VARCHAR(100),
                sm9_params TEXT,
                signed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (contract_id) REFERENCES contracts(id),
                FOREIGN KEY (user_id) REFERENCES users(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // 创建审计日志表
        $db->query("
            CREATE TABLE IF NOT EXISTS audit_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                action VARCHAR(50) NOT NULL,
                user_id INT,
                ip_address VARCHAR(45),
                user_agent TEXT,
                data JSON,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // 添加管理员用户
        $password = password_hash('admin123', PASSWORD_BCRYPT);
        $db->query("
            INSERT INTO users (username, password_hash, is_admin)
            VALUES ('admin', ?, TRUE)
        ", [$password]);
    }
}