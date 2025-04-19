# 系统架构设计

## 1. 总体架构图
```mermaid
graph TD
    A[客户端] --> B[Web服务器]
    B --> C[应用层]
    C --> D[服务层]
    D --> E[数据访问层]
    E --> F[数据库]
    C --> G[第三方API]
```

## 2. 技术栈
- **前端**: Bootstrap 5 + Chart.js
- **后端**: PHP 8.1
- **数据库**: MySQL 8.0
- **缓存**: Redis
- **安全**: OpenSSL加密

## 3. 分层架构

### 3.1 表现层
- 基于Bootstrap的响应式UI
- 管理后台界面
- 数据可视化组件

### 3.2 应用层
- 控制器(Controllers)
- 中间件(Middlewares)
- 路由系统
- **DeepSeek集成控制器**：处理AI分析请求和响应

### 3.3 服务层
- 加密服务(CryptoHelper)
- 认证服务(AuthService)
- 数据统计服务
- **DeepSeek分析服务**：
  - 威胁数据预处理
  - API调用封装
  - 结果缓存管理
  - 错误处理和重试机制

### 3.4 数据访问层
- 数据库访问(DatabaseHelper)
- 缓存管理
- 数据模型
- **DeepSeek数据适配器**：格式化数据供AI分析

## 4. 关键流程

### 4.3 DeepSeek集成流程
```mermaid
sequenceDiagram
    系统->>+DeepSeek服务: 提交分析请求
    DeepSeek服务->>+AI引擎: 处理数据
    AI引擎-->>-DeepSeek服务: 返回分析结果
    DeepSeek服务-->>-系统: 返回格式化结果
    系统->>+日志服务: 记录分析过程
    日志服务-->>-系统: 确认记录成功
```

## 4. 关键流程

### 4.1 用户认证流程
```mermaid
sequenceDiagram
    用户->>+前端: 提交凭证
    前端->>+后端: POST /login
    后端->>+数据库: 验证用户
    数据库-->>-后端: 用户数据
    后端->>+加密服务: 验证密码
    加密服务-->>-后端: 验证结果
    后端-->>-前端: 返回令牌
```

### 4.2 数据加密流程
1. 初始化加密参数
2. 数据序列化
3. AES-256-CBC加密
4. 自定义编码
5. 存储/传输
