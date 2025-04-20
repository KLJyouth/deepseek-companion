#!/bin/bash
# 安全功能部署脚本
# 由install.php在安装完成后调用

LOG_FILE="/var/log/security_deploy.log"
ENV_FILE="$(dirname "$0")/../.env"
BACKUP_SCRIPT="$(dirname "$0")/backup_env.sh"
MONITOR_SCRIPT="$(dirname "$0")/monitor_install_bak.sh"

# 初始化日志
log() {
    echo "[$(date '+%F %T')] $1" >> "$LOG_FILE"
}

# 1. 设置文件权限
log "设置.env文件权限"
chmod 640 "$ENV_FILE" || log "警告: 设置.env权限失败"

# 2. 部署备份任务
log "部署.env备份任务"
if [ -f "$BACKUP_SCRIPT" ]; then
    chmod +x "$BACKUP_SCRIPT"
    (crontab -l 2>/dev/null; echo "0 3 * * * $BACKUP_SCRIPT") | crontab -
    log "已添加.env备份任务"
else
    log "错误: 备份脚本不存在"
fi

# 3. 部署监控服务
log "部署install.php.bak监控"
if [ -f "$MONITOR_SCRIPT" ]; then
    chmod +x "$MONITOR_SCRIPT"
    cat > /etc/systemd/system/monitor_install.service <<EOF
[Unit]
Description=Monitor install.php.bak access
After=network.target

[Service]
ExecStart=$MONITOR_SCRIPT
Restart=always
User=root

[Install]
WantedBy=multi-user.target
EOF

    systemctl daemon-reload
    systemctl enable monitor_install
    systemctl start monitor_install
    log "已部署监控服务"
else
    log "错误: 监控脚本不存在"
fi

log "安全部署完成"