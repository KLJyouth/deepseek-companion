# 加密服务API参考

## 1. 基础信息
- **API版本**：v1
- **认证方式**：Bearer Token
- **响应格式**：JSON

## 2. 端点列表

### 2.1 数据加密
`POST /api/v1/encrypt`

#### 请求示例
```curl
curl -X POST \
  https://api.stanfai.com/v1/encrypt \
  -H 'Authorization: Bearer your_token' \
  -H 'Content-Type: application/json' \
  -d '{
    "algorithm": "KYBER1024",
    "data": "Sensitive data to encrypt"
  }'
```

#### 响应参数
| 字段 | 类型 | 说明 |
|------|------|------|
| status | string | 执行状态 |
| encrypted | string | 加密数据(Base64) |
| key_id | string | 密钥版本ID |
| timestamp | int | 加密时间戳 |

#### 状态码
| 代码 | 说明 |
|------|------|
| 200 | 加密成功 |
| 400 | 无效请求 |
| 401 | 未授权 |
| 500 | 服务器错误 |

### 2.2 数据解密
`POST /api/v1/decrypt`

#### 请求示例
```curl
curl -X POST \
  https://api.stanfai.com/v1/decrypt \
  -H 'Authorization: Bearer your_token' \
  -H 'Content-Type: application/json' \
  -d '{
    "encrypted": "aGVsbG8gd29ybGQ=",
    "key_id": "key_v1_2023"
  }'
```

## 3. 错误处理

### 3.1 错误响应
```json
{
  "error": {
    "code": "INVALID_KEY",
    "message": "The provided key is expired or invalid",
    "details": {
      "key_id": "key_v1_2022",
      "valid_until": "2023-01-01T00:00:00Z"
    }
  }
}
```

### 3.2 常见错误码
| 错误码 | 说明 | 解决方案 |
|--------|------|----------|
| INVALID_ALGO | 不支持的算法 | 检查algorithm参数 |
| KEY_EXPIRED | 密钥已过期 | 使用key_id获取新密钥 |
| DECRYPT_FAIL | 解密失败 | 验证加密数据和密钥版本 |

## 4. 最佳实践

1. **密钥管理**：
```php
// 定期检查密钥状态
$client->checkKeyStatus($keyId);
```

2. **错误处理**：
```php
try {
    $decrypted = $client->decrypt($data);
} catch (ApiException $e) {
    if ($e->getCode() == 'KEY_EXPIRED') {
        // 处理密钥过期
    }
}
```

[查看完整API文档](../api.md)