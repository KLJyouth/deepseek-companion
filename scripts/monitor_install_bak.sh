#!/bin/bash
# install.php.bak访问监控脚本
# 建议作为systemd服务运行

LOG_FILE="/var/log/install_bak_access.log"
MONITOR_FILE="$(dirname "$0")/../install.php.bak"
ALERT_THRESHOLD=3  # 触发报警的访问次数
BLOCK_DURATION=3600  # 封锁时长(秒)

# 初始化日志文件
touch "$LOG_FILE"
chmod 600 "$LOG_FILE"

# 监控文件访问
inotifywait -qm -e access --format '%T %w %f %e' --timefmt '%F %T' "$MONITOR_FILE" | while read -r line
do
    # 记录访问日志
    TIMESTAMP=$(date '+%F %T')
    IP=$(who | awk '{print $5}' | cut -d'(' -f2 | cut -d')' -f1)
    echo "$TIMESTAMP - 访问来自 $IP - $line" >> "$LOG_FILE"

    # 检查异常访问
    COUNT=$(grep -c "$IP" "$LOG_FILE" | awk '{print $1}')
    if [ "$COUNT" -ge "$ALERT_THRESHOLD" ]; then
        # 记录报警
        echo "$TIMESTAMP - 异常访问来自 $IP (${COUNT}次)" >> "$LOG_FILE"
        
        # 封锁IP (需要root权限)
        if [ "$(id -u)" -eq 0 ]; then
            iptables -A INPUT -s "$IP" -j DROP
            echo "$TIMESTAMP - 已封锁IP: $IP" >> "$LOG_FILE"
            
            # 设置临时封锁
            sleep "$BLOCK_DURATION"
            iptables -D INPUT -s "$IP" -j DROP
        fi
    fi
done