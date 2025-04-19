DeepSeek-Companion 技术架构文档
1. 系统概述

DeepSeek-Companion 是一个安全智能管理系统，集成了威胁检测和电子签约功能，采用三维可视化技术展示安全数据。系统采用 PHP + Node.js 混合架构，具有多层安全防护机制和量子安全加密体系。

2. 系统架构

2.1 整体架构

![系统架构图]

(注：此处应插入架构图)

前端服务：Node.js + Three.js 可视化
后端服务：PHP + MySQL
实时通信：WebSocket + Ratchet
安全层：量子加密 + 传统加密混合方案
2.2 技术栈

组件	技术选型
前端	Node.js, Webpack, Three.js
后端	PHP 7.4+, MySQL 8.0+
安全	AES-256-GCM, Kyber1024
实时通信	WebSocket, Ratchet
缓存	Redis, Memcached
3. 核心模块

3.1 认证授权系统

3.1.1 认证流程

1. 基础认证（用户名+密码）

2. 设备指纹验证

3. 地理位置验证

4. 多因素认证（TOTP/生物识别）

3.1.2 安全特性

会话固定防护（session_regenerate_id）
会话劫持检测（IP+UserAgent验证）
定期会话ID更新（30分钟）
Cookie安全设置（HttpOnly/Secure/SameSite）
3.2 电子签约系统

3.2.1 签约流程

1. 合同模板创建（管理员）

2. 合同实例生成

3. 法大大签约集成

4. 签约回调处理

3.2.2 安全机制

合同内容加密存储
模板ID加密传输
参与方信息JSON编码
法大大回调签名验证
3.3 实时通信系统

3.3.1 WebSocket实现

基于Ratchet库
认证机制：
会话基础认证
临时令牌认证（1小时有效期）
数据库存储验证记录（ws_auth_tokens表）
4. 安全体系

4.1 加密方案

4.1.1 混合加密策略

传统加密：AES-256-GCM
量子安全加密：Kyber1024
辅助算法：MD5预处理、自定义编码
4.1.2 密钥管理

密钥轮换机制
健康检查机制
多层级安全设计
内存安全擦除（sodium_memzero）
4.2 API安全

4.2.1 签名机制

HMAC-SHA256算法
基于请求参数+时间戳+密钥
参数按字母顺序排序（ksort）
4.2.2 防护措施

时间戳验证（5分钟窗口）
重放攻击防护
hash_equals防时序攻击
5. 数据库设计

5.1 核心表结构

5.1.1 用户相关表

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  password_strength TINYINT NOT NULL,
  tfa_secret VARCHAR(32),
  bio_auth_data TEXT,
  last_password_change DATETIME,
  status ENUM('active','locked','disabled') DEFAULT 'active'
);

CREATE TABLE user_devices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  device_fingerprint VARCHAR(64) NOT NULL,
  trusted BOOLEAN DEFAULT FALSE,
  last_used DATETIME,
  FOREIGN KEY (user_id) REFERENCES users(id)
);
5.1.2 签约相关表

CREATE TABLE contract_templates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  content LONGTEXT NOT NULL,
  created_by INT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE contracts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  template_id INT NOT NULL,
  parties JSON NOT NULL,
  status ENUM('draft','pending','signed','expired') DEFAULT 'draft',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  signed_at DATETIME,
  FOREIGN KEY (template_id) REFERENCES contract_templates(id)
);
5.2 安全相关表

CREATE TABLE ws_auth_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token VARCHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE login_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  ip_address VARCHAR(45) NOT NULL,
  user_agent VARCHAR(255) NOT NULL,
  country_code CHAR(2),
  success BOOLEAN NOT NULL,
  attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);
6. 部署指南

6.1 环境要求

组件	版本要求
PHP	≥7.4
MySQL	≥8.0
Node.js	≥14.x
Redis	≥5.0
6.2 必需PHP扩展

mysqli
redis
memcached
mbstring
sodium
6.3 目录权限

logs/ - 755
tmp/ - 777
sessions/ - 700
7. 测试方案

7.1 测试范围

1. 基础环境测试（PHP版本和扩展）

2. 服务连接测试（MySQL/Redis/Memcached）

3. 核心功能测试（加密功能）

4. 文件系统测试（目录权限）

7.2 自动化测试

# 运行环境验证测试
php test/ServerEnvValidator.php

# 前端测试
npm test

# API测试
phpunit tests/ApiTests/
8. 性能优化建议

1. WebSocket优化：

实现连接池管理
增加心跳检测机制
优化广播策略
2. 数据库优化：

为高频查询添加适当索引
考虑读写分离
优化大表查询
3. 缓存策略：

增加二级缓存
优化缓存失效策略
实现热点数据预加载
9. 安全加固建议

1. 加密增强：

定期轮换加密密钥
实现密钥分级管理
增加加密算法健康检查
2. 监控增强：

实现异常行为检测
增加安全事件告警
完善审计日志
3. 防御增强：

实现WAF防护
增加速率限制
完善输入验证
10. 附录

10.1 术语表

TFA：双因素认证
RBAC：基于角色的访问控制
PQC：后量子密码学
10.2 相关文档

[法大大API文档]()
[DeepSeek集成指南]()
[量子加密实现白皮书]()
文档版本：1.0.0*
最后更新：2025-4-19*
文档维护者：DeepSeek-Companion技术团队*KLJyouth*