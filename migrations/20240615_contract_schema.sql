-- 合同模板表
CREATE TABLE IF NOT EXISTS ac_contract_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_name VARCHAR(255) NOT NULL,
    template_content LONGTEXT NOT NULL,
    created_by INT NOT NULL,
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES ac_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 合同实例表
CREATE TABLE IF NOT EXISTS ac_contracts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL,
    parties JSON NOT NULL,
    contract_content LONGTEXT NOT NULL,
    status ENUM('pending', 'signed', 'rejected', 'expired') DEFAULT 'pending',
    fadada_evidence_id VARCHAR(255),
    deepseek_audit_log TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    signed_at TIMESTAMP NULL,
    FOREIGN KEY (template_id) REFERENCES ac_contract_templates(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 签名记录表
CREATE TABLE IF NOT EXISTS ac_signatures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contract_id INT NOT NULL,
    user_id INT NOT NULL,
    sign_position JSON NOT NULL,
    sign_image VARCHAR(255),
    audit_trail TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contract_id) REFERENCES ac_contracts(id),
    FOREIGN KEY (user_id) REFERENCES ac_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;