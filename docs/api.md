# API参考总览

## 1. API版本控制
- **当前稳定版**：v1
- **版本策略**：
  - 语义化版本(MAJOR.MINOR.PATCH)
  - 重大变更通过新MAJOR版本发布
  - 旧版本维护周期：12个月

## 2. 核心API

### 2.1 加密服务
[加密/解密API](./api/encryption.md)
- 数据加密
- 数据解密
- 密钥管理

### 2.2 区块链审计
[审计存证API](./api/blockchain.md)
- 提交存证
- 验证存证
- 查询审计日志

### 2.3 安全监控
[监控告警API](./api/monitoring.md)
- 安全事件上报
- 实时告警配置
- 威胁指标查询

## 3. 通用规范

### 3.1 请求头
| 头部 | 说明 | 示例 |
|------|------|------|
| Authorization | 认证凭证 | Bearer xxxx |
| X-Request-ID | 请求追踪ID | uuid-v4 |
| X-Api-Version | API版本 | v1 |

### 3.2 响应格式
```json
{
  "data": {},
  "error": null,
  "meta": {
    "request_id": "req_123",
    "timestamp": 1672531200
  }
}
```

## 4. 最佳实践

### 4.1 错误处理
```php
try {
    $response = $client->request('/v1/encrypt', $data);
} catch (ApiException $e) {
    // 根据error.code处理特定错误
    if ($e->getErrorCode() == 'RATE_LIMITED') {
        // 重试逻辑
    }
}
```

### 4.2 重试机制
- 指数退避重试
- 最大重试次数：3次
- 可重试错误码：429, 503

[返回文档首页](../index.md)