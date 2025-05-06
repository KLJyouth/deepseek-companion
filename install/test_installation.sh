#!/bin/bash
# 安装向导测试脚本

echo "开始测试安装向导组件..."

# 1. 检查必需文件
REQUIRED_FILES=(
    "env_check.sh"
    "index.html"
    "css/install.css"
    "js/install.js"
    "api/env_check.php"
    "api/install.php"
    "config.template.php"
    "verify.php"
)

missing_files=0
for file in "${REQUIRED_FILES[@]}"; do
    if [ ! -f "$file" ]; then
        echo "[✗] 缺少文件: $file"
        missing_files=$((missing_files + 1))
    else
        echo "[✓] 文件存在: $file"
    fi
done

if [ $missing_files -gt 0 ]; then
    echo "错误: 缺少 $missing_files 个必需文件"
    exit 1
fi

# 2. 检查文件权限
echo -e "\n检查文件权限..."
if [ -x "env_check.sh" ]; then
    echo "[✓] env_check.sh 可执行"
else
    echo "[✗] env_check.sh 不可执行"
    exit 1
fi

# 3. 测试环境检测API
echo -e "\n测试环境检测API..."
api_response=$(curl -sS "http://localhost/install/api/env_check.php")
if echo "$api_response" | grep -q "database"; then
    echo "[✓] 环境检测API响应正常"
else
    echo "[✗] 环境检测API无响应或返回错误"
    echo "API响应: $api_response"
    exit 1
fi

# 4. 测试配置文件模板可写
echo -e "\n测试配置文件模板..."
if [ -w "config.template.php" ]; then
    echo "[✓] config.template.php 可写"
else
    echo "[✗] config.template.php 不可写"
    exit 1
fi

echo -e "\n所有测试通过！安装向导已准备好使用。"
exit 0