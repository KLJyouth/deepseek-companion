# stanfai-司单服Ai智能安全法务 程序模块结构说明

## 1. 配置与初始化
- `config.php`：全局配置，环境变量优先，安全、数据库、API、合同等
- `libs/Bootstrap.php`：系统初始化流程，依赖检查、加密、数据库、WebSocket等

## 2. 核心库（libs/）
- `CryptoHelper.php`：自研加密/解密、量子加密、CSRF、密码哈希、零知识证明、区块链存证
- `DatabaseHelper.php`：数据库操作、安全查询、审计日志、密码强度、历史密码
- `SanitizeHelper.php`：输入净化与过滤
- `SessionHelper.php`：会话管理
- `RedirectHelper.php`：安全重定向

## 3. 控制器（controllers/）
- `LoginController.php`：登录与认证流程，支持2FA、生物识别
- `MonitorController.php`：系统监控与指标查询
- `WebSocketController.php`：WebSocket令牌与认证
- `ContractController.php`：自研电子签约与法务流程控制器，合同模板、签名、存证、归档、流程自动化
- `refactor/LoginController.refactor.php`：登录控制器重构示例

## 4. 服务层（services/）
- `DeviceManagementService.php`：设备指纹、地理位置、设备告警
- `SystemMonitorService.php`：系统指标采集与推送
- `WebSocketService.php`：WebSocket消息、签名、压缩、广播
- `ApiSignService.php`：API签名与验证
- `LoginAnalyticsService.php`：登录行为分析
- `RateLimitService.php`：速率限制与动态调整
- `ContractService.php`：自研合同与法务服务，支持合同签名、归档、存证、流程自动化
- `aggregate_metrics.php`：指标聚合脚本

## 5. 中间件（middlewares/）
- `AuthMiddleware.php`：认证
- `RateLimitMiddleware.php`：速率限制
- `SecurityMiddleware.php`：CSRF与安全头
- `BiometricMiddleware.php`：生物识别

## 6. 前端与可视化
- `js/globe.js`：Three.js地球、WebSocket前端、交互动画
- `styles/`、`css/`：样式与主题

## 7. 管理后台与页面
- `admin.php`、`login.php`、`index.php`：主页面
- `admin/`：后台管理、历史监控、用户管理等
- `contracts/`：合同管理、签约归档、法务流程页面

## 8. 数据库与脚本
- `ai_companion_db.sql`：数据库结构，包含自研合同、签约、法务相关表
- `db_fix_and_repair.php`、`create_audit_logs_table.php`：修复与初始化脚本

## 9. 文档与说明
- `README.md`、`docs/`：开发、部署、API、安全、创新等文档

---
