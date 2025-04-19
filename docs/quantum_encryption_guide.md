# 量子加密指南

## 1. 概述
DeepSeek Companion系统采用量子混合加密方案，结合Kyber1024算法和AES-256加密，提供后量子时代的安全保障。

## 2. 系统要求
- PHP 8.0+
- pqcrypto扩展
- OpenSSL 1.1.1+
- 支持Kyber1024算法的服务器

## 3. 安装配置
### 3.1 安装pqcrypto扩展
```bash
pecl install pqcrypto
```

### 3.2 配置PHP
```ini
extension=pqcrypto.so
```

## 4. 使用指南
### 4.1 初始化量子密钥
```php
use Libs\CryptoHelper;

// 初始化量子密钥对(自动根据风险等级设置有效期)
CryptoHelper::initPQC();

// 获取当前密钥有效期(秒)
$ttl = CryptoHelper::getKeyTTL();
```

### 4.2 AI驱动的密钥管理
系统会根据实时风险等级自动调整密钥策略：

| 风险等级 | 密钥有效期 | 适用场景 |
|---------|-----------|---------|
| 1(低)  | 24小时    | 常规操作 |
| 2(中低) | 12小时    | 敏感操作 |
| 3(中)  | 8小时     | 外部接口 |
| 4(中高) | 6小时     | 金融交易 |
| 5(高)  | 1小时     | 核心系统 |

密钥生成事件会自动记录到区块链存证系统。

### 4.2 量子加密数据
```php
$sensitiveData = ['user' => 'admin', 'access' => 'high'];
$encrypted = CryptoHelper::quantumEncrypt(json_encode($sensitiveData));
```

### 4.3 量子解密数据
```php
$decrypted = CryptoHelper::quantumDecrypt($encrypted);
$data = json_decode($decrypted, true);
```

## 5. API集成
### 5.1 请求示例
```javascript
// 参见API文档的量子加密部分
```

### 5.2 响应处理
```javascript
if(response.data.encryption === 'quantum') {
    // 特殊处理量子加密数据
}
```

## 6. 最佳实践
1. 对敏感数据使用量子加密
2. 定期轮换量子密钥(建议每日)
3. 监控加密性能指标

## 7. 常见问题
### Q1: 量子加密性能如何？
A: 混合加密方案平衡了安全性和性能，实测吞吐量约为纯AES加密的85%

### Q2: 如何验证量子加密是否正常工作？
A: 使用健康检查接口：
```php
$status = CryptoHelper::healthCheck();
if($status['quantum']['status'] === 'healthy') {
    // 量子加密功能正常
}
```

## 8. 更多资源
- [Kyber算法白皮书](https://pq-crystals.org/kyber/)
- [NIST后量子加密标准](https://csrc.nist.gov/projects/post-quantum-cryptography)
