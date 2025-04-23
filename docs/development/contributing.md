# 贡献指南

欢迎参与Stanfai PHP项目！以下是贡献代码的规范流程：

## 🛠️ 开发环境

1. **环境要求**：
   - PHP 7.4+
   - Composer 2.0+
   - MySQL 5.7+/MariaDB 10.3+

2. **初始化设置**：
   ```bash
   git clone https://github.com/stanfai/stanfai-php.git
   cd stanfai-php
   composer install
   cp .env.example .env
   ```

## 📝 代码规范

1. **编码风格**：
   - 遵循PSR-12编码标准
   - 使用类型提示和返回类型声明
   ```php
   // Good
   public function encrypt(string $data): string;
   
   // Bad
   function encrypt($data);
   ```

2. **提交消息**：
   - 使用约定式提交(Conventional Commits)
   ```
   feat(encrypt): add quantum encryption support
   fix(auth): resolve session fixation vulnerability
   ```

## 🔍 测试要求

1. **单元测试**：
   ```bash
   phpunit tests/Unit
   ```
2. **安全测试**：
   ```bash
   php artisan test --group=security
   ```

## ➕ 提交PR

1. Fork主仓库
2. 创建特性分支
3. 确保所有测试通过
4. 提交清晰的PR描述

[返回README](../README.md)