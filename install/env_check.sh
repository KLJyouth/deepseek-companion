#!/bin/bash

# 环境检测脚本
# 用于检查系统是否符合安装要求

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# 1. 检查操作系统
echo -e "\n${YELLOW}=== 操作系统检查 ===${NC}"
os_name=$(uname)
if [ "$os_name" != "Linux" ] && [ "$os_name" != "Darwin" ]; then
    echo -e "${RED}✗ 错误: 非类Unix系统${NC}"
    exit 1
else
    echo -e "${GREEN}✓ 通过: 类Unix系统 (${os_name})${NC}"
fi

# 2. 检查PHP版本
echo -e "\n${YELLOW}=== PHP版本检查 (需要 >7.2) ===${NC}"
php_version=$(php -v | head -n 1 | cut -d " " -f 2 | cut -d "." -f 1,2)
if [ -z "$php_version" ]; then
    echo -e "${RED}✗ 错误: PHP未安装${NC}"
    exit 1
else
    php_major=$(echo $php_version | cut -d "." -f 1)
    php_minor=$(echo $php_version | cut -d "." -f 2)
    
    if [ "$php_major" -ge 7 ] && [ "$php_minor" -ge 3 ]; then
        echo -e "${GREEN}✓ 通过: PHP版本 ${php_version}${NC}"
    else
        echo -e "${RED}✗ 错误: PHP版本过低 (${php_version})${NC}"
        exit 1
    fi
fi

# 3. 检查MySQL版本
echo -e "\n${YELLOW}=== MySQL版本检查 (需要 5.7) ===${NC}"
mysql_version=$(mysql --version 2>/dev/null | awk '{print $3}' | cut -d "." -f 1,2)
if [ -z "$mysql_version" ]; then
    echo -e "${RED}✗ 错误: MySQL未安装或未在PATH中${NC}"
    exit 1
else
    if [ "$mysql_version" == "5.7" ]; then
        echo -e "${GREEN}✓ 通过: MySQL版本 ${mysql_version}${NC}"
    else
        echo -e "${RED}✗ 错误: 不兼容的MySQL版本 (${mysql_version})${NC}"
        exit 1
    fi
fi

# 4. 检查Web服务器
echo -e "\n${YELLOW}=== Web服务器检查 ===${NC}"
if pgrep -x "nginx" >/dev/null; then
    echo -e "${GREEN}✓ 检测到: Nginx${NC}"
elif pgrep -x "apache2" >/dev/null || pgrep -x "httpd" >/dev/null; then
    echo -e "${GREEN}✓ 检测到: Apache${NC}"
elif pgrep -x "w3wp" >/dev/null; then
    echo -e "${GREEN}✓ 检测到: IIS${NC}"
else
    echo -e "${YELLOW}⚠ 警告: 未检测到Web服务器${NC}"
fi

# 5. 检查宝塔面板
echo -e "\n${YELLOW}=== 宝塔面板检查 ===${NC}"
if [ -f "/www/server/panel/BT-Panel" ]; then
    echo -e "${GREEN}✓ 检测到: 宝塔面板已安装${NC}"
else
    echo -e "${YELLOW}⚠ 信息: 未检测到宝塔面板${NC}"
fi

echo -e "\n${GREEN}环境检查完成${NC}"
exit 0