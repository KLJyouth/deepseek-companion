#!/bin/bash
# 安装向导权限设置脚本

# 设置安装目录路径
INSTALL_DIR=$(dirname "$0")

echo "正在设置安装向导权限..."

# 1. 设置env_check.sh可执行权限
chmod +x "$INSTALL_DIR/env_check.sh"
if [ $? -eq 0 ]; then
    echo "[✓] env_check.sh 已设置为可执行"
else
    echo "[✗] 无法设置env_check.sh的可执行权限"
    exit 1
fi

# 2. 设置目录权限
find "$INSTALL_DIR" -type d -exec chmod 755 {} \;
if [ $? -eq 0 ]; then
    echo "[✓] 所有目录已设置为755权限"
else
    echo "[✗] 无法设置目录权限"
    exit 1
fi

# 3. 设置文件权限
find "$INSTALL_DIR" -type f -exec chmod 644 {} \;
if [ $? -eq 0 ]; then
    echo "[✓] 所有文件已设置为644权限"
else
    echo "[✗] 无法设置文件权限"
    exit 1
fi

# 4. 设置特殊文件权限
chmod 666 "$INSTALL_DIR/config.template.php"
if [ $? -eq 0 ]; then
    echo "[✓] config.template.php 已设置为可写"
else
    echo "[✗] 无法设置config.template.php的权限"
    exit 1
fi

# 5. 设置存储目录权限
STORAGE_DIRS=("api" "css" "js" "storage")
for dir in "${STORAGE_DIRS[@]}"; do
    if [ -d "$INSTALL_DIR/$dir" ]; then
        chmod -R 755 "$INSTALL_DIR/$dir"
        echo "[✓] $dir/ 目录权限已设置"
    fi
done

echo "权限设置完成！"
echo "您现在可以访问安装向导了。"
exit 0