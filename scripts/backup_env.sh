#!/bin/bash
# .env文件备份脚本
# 每天凌晨3点执行：0 3 * * * /path/to/scripts/backup_env.sh

BACKUP_DIR="/var/backups/env_files"
ENV_FILE="$(dirname "$0")/../.env"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
GPG_RECIPIENT="admin@example.com"  # 替换为你的GPG加密邮箱

# 创建备份目录
mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"

# 生成加密备份
BACKUP_FILE="${BACKUP_DIR}/env_backup_${TIMESTAMP}.gpg"
gpg --encrypt --recipient "$GPG_RECIPIENT" --output "$BACKUP_FILE" "$ENV_FILE"

# 保留最近7天备份
find "$BACKUP_DIR" -name "env_backup_*.gpg" -mtime +7 -exec rm {} \;

# 记录日志
logger -t ENV_BACKUP "完成.env文件备份: $BACKUP_FILE"