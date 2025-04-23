# 安全部署指南

## 1. 系统要求

### 1.1 服务器配置
- **操作系统**: Linux (推荐Ubuntu 20.04+)
- **内存**: ≥4GB (生产环境建议8GB+)
- **存储**: 
  - 系统: 50GB+
  - 审计日志: 额外100MB/day
- **网络**: 
  - 区块链节点访问
  - 加密服务连接

### 1.2 软件依赖
- PHP 7.4+ (推荐8.0+)
- 扩展要求:
  - OpenSSL 1.1.1+
  - Sodium
  - gRPC (区块链连接)
- 数据库: MySQL 5.7+/MariaDB 10.3+

## 2. 安全配置

### 2.1 加密设置
```php
// config.php
// 路径加密配置
define('PATH_ENCRYPTION_ENABLED', true);
define('PATH_KEY_ROTATION_INTERVAL', 86400); // 24小时
define('PATH_SENSITIVE_PATTERNS', [
    '/\.env$/i',
    '/config\.php$/i',
    '/\/admin\//i'
]);

// 量子加密配置
define('QUANTUM_ENCRYPTION_KEY', env('QUANTUM_KEY')); // 必须从环境变量加载
define('QUANTUM_ALGORITHM', 'KYBER1024-AES256');
```

### 2.2 环境变量
```bash
# .env 示例
QUANTUM_KEY=your_secure_key_here
BLOCKCHAIN_NODE_URL=https://node.example.com
AUDIT_LOG_PATH=/var/log/stanfai/audit
```

## 3. 部署步骤

### 3.1 初始化部署
```bash
# 1. 安装依赖
composer install --optimize-autoloader --no-dev

# 2. 配置权限
chmod -R 750 storage bootstrap/cache
chown -R www-data:www-data .

# 3. 启动服务
php artisan serve --host=0.0.0.0 --port=8080
```

### 3.2 安全加固
1. 配置防火墙:
   ```bash
   ufw allow 8080/tcp
   ufw enable
   ```
2. 设置自动密钥轮换:
   ```bash
   crontab -e
   # 每天轮换密钥
   0 3 * * * php artisan security:rotate-keys
   ```

## 4. 验证部署

### 4.1 健康检查
```bash
curl -I http://localhost:8080/health
# 应返回200 OK和以下头部
# X-Security-Status: active
# X-Encryption-Version: KYBER1024
```

### 4.2 安全测试
```bash
# 运行安全测试套件
php artisan test --group=security
```

[返回主文档](../README.md#部署指南)
```</content>
</write_to_file>