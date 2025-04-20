# AuthMiddleware 技术文档

## 概述

AuthMiddleware 是项目中统一处理认证和授权的中间件类，封装了常用的验证逻辑，提供一致的身份验证和权限检查接口。

## 功能说明

### 1. 管理员验证 (`verifyAdmin`)
验证当前用户是否具有管理员权限。

**实现逻辑：**
- 检查会话中是否存在用户信息
- 验证用户角色是否为'admin'

**抛出异常：**
- 当用户未登录或不是管理员时

### 2. 用户登录验证 (`verifyAuth`)
验证当前用户是否已登录。

**实现逻辑：**
- 检查会话中是否存在有效的用户ID

**抛出异常：**
- 当用户未登录时

### 3. 合同访问验证 (`verifyContractAccess`)
验证当前用户是否有权访问指定合同。

**实现逻辑：**
- 首先验证用户是否登录
- 查询数据库验证合同所有权
- 检查当前用户是否是合同创建者

**参数：**
- `$contractId` (string): 要验证的合同ID

**抛出异常：**
- 当用户无权访问合同时

## 使用指南

### 基本调用

```php
// 验证管理员权限
\Libs\AuthMiddleware::verifyAdmin();

// 验证用户登录
\Libs\AuthMiddleware::verifyAuth();

// 验证合同访问权限
\Libs\AuthMiddleware::verifyContractAccess($contractId);
```

### 最佳实践

1. 在控制器方法开始处调用验证
2. 捕获并处理可能抛出的异常
3. 对于需要多重验证的场景，可以链式调用：

```php
public function sensitiveAction($contractId) {
    \Libs\AuthMiddleware::verifyAuth();
    \Libs\AuthMiddleware::verifyAdmin();
    \Libs\AuthMiddleware::verifyContractAccess($contractId);
    
    // 业务逻辑...
}
```

## 实现细节

### 设计考虑

1. **单例模式**：保持全局唯一的验证实例
2. **静态方法**：便于直接调用，无需实例化
3. **集中管理**：所有验证逻辑统一维护
4. **明确职责**：每个方法只做一件事

### 类结构

```php
class AuthMiddleware {
    private static $instance = null;
    
    private function __construct() {}
    
    public static function getInstance() { /*...*/ }
    public static function verifyAdmin() { /*...*/ }
    public static function verifyAuth() { /*...*/ }
    public static function verifyContractAccess($contractId) { /*...*/ }
}
```

## 扩展建议

1. 添加更多验证方法：
   - 角色分组验证
   - 权限级别验证
   - 时间敏感操作验证

2. 支持多种认证方式：
   - API Token验证
   - OAuth集成
   - 双因素认证

3. 性能优化：
   - 缓存验证结果
   - 批量验证支持

4. 日志记录：
   - 记录验证失败详情
   - 审计跟踪

## 版本历史

| 版本 | 日期       | 描述               |
|------|------------|--------------------|
| 1.0  | 2023-11-15 | 初始版本，实现基本验证功能 |