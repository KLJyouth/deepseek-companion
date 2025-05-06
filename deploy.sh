#!/bin/bash

echo "开始部署 Stanfai 项目..."

# 检查必要工具
command -v php >/dev/null 2>&1 || { echo "需要安装 PHP"; exit 1; }
command -v composer >/dev/null 2>&1 || { echo "需要安装 Composer"; exit 1; }
command -v npm >/dev/null 2>&1 || { echo "需要安装 Node.js"; exit 1; }

# 设置目录权限
echo "设置目录权限..."
chmod -R 755 .
chmod -R 777 storage
chmod -R 777 cache
chmod -R 777 logs
chmod -R 777 public/uploads

# 安装依赖
echo "安装项目依赖..."
composer install --no-dev --optimize-autoloader
npm install && npm run build

# 运行安装程序
echo "运行安装程序..."
php install.php --no-interactive

# 优化配置
echo "优化系统配置..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "部署完成!"
echo "请确保Web服务器配置正确指向 public 目录"
echo "访问网站验证安装是否成功"
