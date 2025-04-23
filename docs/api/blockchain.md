# 区块链审计API参考

## 1. 基础信息
- **API版本**：v1
- **认证方式**：HMAC签名
- **响应格式**：JSON

## 2. 核心端点

### 2.1 多链配置
`PUT /api/v1/blockchain/config`

#### 请求示例
```yaml
chains:
  - name: fabric
    type: fabric
    endpoint: grpc://fabric.example.com
    channel: mychannel
    chaincode: audit
  - name: eth
    type: ethereum
    endpoint: https://mainnet.infura.io/v3/key
    contract: 0x123...abc
```

### 2.2 提交存证
`POST /api/v1/audit/evidence`

#### 请求参数
| 参数 | 说明 |
|------|------|
| chain | 目标链名称(可选) |
| async | 是否异步提交(默认false) |

#### 请求头
| 头部 | 说明 |
|------|------|
| X-Auth-Timestamp | 请求时间戳(毫秒) |
| X-Auth-Signature | HMAC-SHA256签名 |

#### 请求示例
```curl
curl -X POST \
  https://api.stanfai.com/v1/audit/evidence \
  -H 'X-Auth-Timestamp: 1672531200000' \
  -H 'X-Auth-Signature: sha256=abc123...' \
  -d '{
    "action": "USER_LOGIN",
    "data": {
      "user_id": "usr_123",
      "ip": "192.168.1.1"
    }
  }'
```

#### 签名生成
```python
import hmac
import hashlib
import time

timestamp = int(time.time() * 1000)
secret = "your_shared_secret"
message = f"{timestamp}{request_body}"

signature = hmac.new(
    secret.encode(),
    message.encode(),
    hashlib.sha256
).hexdigest()
```

### 2.2 验证存证
`GET /api/v1/audit/evidence/{txId}`

#### 响应参数
| 字段 | 类型 | 说明 |
|------|------|------|
| valid | bool | 存证有效性 |
| block_height | int | 区块高度 |
| timestamp | int | 存证时间戳 |
| merkle_proof | string[] | Merkle证明路径 |

### 2.3 批量存证
`POST /api/v1/audit/batch`

#### 请求示例
```json
{
  "entries": [
    {
      "action": "USER_LOGIN",
      "data": {"user": "usr1", "ip": "1.1.1.1"}
    },
    {
      "action": "FILE_ACCESS", 
      "data": {"file": "config.php"}
    }
  ],
  "chain": "fabric",
  "async": true
}
```

#### 响应参数
| 字段 | 类型 | 说明 |
|------|------|------|
| batch_id | string | 批次ID |
| pending | int | 处理中数量 |
| completed | int | 已完成数量 |

### 2.4 存证状态查询
`GET /api/v1/audit/status/{batch_id}`

#### 响应示例
```json
{
  "status": "completed",
  "success_count": 5,
  "failed_count": 0,
  "tx_hashes": ["0x123...", "0x456..."]
}
```

### 2.5 查询审计日志
`GET /api/v1/audit/logs`

#### 查询参数
| 参数 | 说明 |
|------|------|
| action | 操作类型过滤 |
| start_time | 开始时间戳 |
| end_time | 结束时间戳 |
| limit | 返回条数 |

## 3. 安全机制

### 3.1 防重放攻击
- 请求时间戳有效期±5分钟
- 服务端缓存已处理签名

### 3.2 权限控制
| 操作 | 所需权限 |
|------|----------|
| 提交存证 | audit:write |
| 查询日志 | audit:read |

## 4. 错误处理

### 4.1 常见错误
| 代码 | 说明 |
|------|------|
| 401 | 签名无效 |
| 403 | 权限不足 |
| 429 | 请求频率限制 |

[返回API目录](../api.md)