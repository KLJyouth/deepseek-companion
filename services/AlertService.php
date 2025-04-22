<?php
namespace Services;

use Libs\LogHelper;
use Libs\DatabaseHelper;
use Redis;
use RedisException;

/**
 * 系统安全告警服务
 *
 * @final 标记为不可继承类
 * @copyright 广西港妙科技有限公司 保留所有权利
 */


/**
 * 告警服务 - 负责系统告警的检测、记录和通知
 * 
 * @copyright 版权所有 © 广西港妙科技有限公司
 * @version 1.0.0
 */
class AlertService {
    /**
     * @var Redis Redis客户端实例
     */
    private \Redis $redis;
    
    /**
     * @var LogHelper 日志助手实例
     */
    private LogHelper $logger;
    
    /**
     * @var DatabaseHelper 数据库助手实例
     */
    private DatabaseHelper $db;
    
    /**
     * @var array 告警阈值配置
     */
    private array $thresholds;

    public function __construct(
        private string $host = '127.0.0.1',
        private int $port = 6379,
        private float $timeout = 2.5,
        private int $retryInterval = 100
    ) {
        $this->redis = new \Redis();
        $this->logger = LogHelper::getInstance();
        $this->db = DatabaseHelper::getInstance();

        try {
            if (!$this->redis->connect($host, $port, $timeout, null, $retryInterval)) {
                throw new \RedisException('Redis连接失败');
            }
        } catch (\RedisException $e) {
            $this->logger->error('Redis连接异常: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 检查系统指标并触发告警
     * 
     * @param array $metrics 系统指标数据
     */
    public function checkMetrics(array $metrics): void {
        // 检查连接池使用率
        $poolUsage = ($metrics['active'] / ($metrics['active'] + $metrics['idle'])) * 100;
        if ($poolUsage > $this->thresholds['pool_usage']) {
            $this->triggerAlert('pool_usage', sprintf(
                "连接池使用率过高: %.2f%%", 
                $poolUsage
            ), 'warning');
        }

        // 检查查询响应时间
        if ($metrics['avg_response'] > $this->thresholds['query_time']) {
            $this->triggerAlert('query_time', sprintf(
                "查询响应时间过长: %.2fms", 
                $metrics['avg_response']
            ), 'critical');
        }
    }

    /**
     * 获取告警统计数据
     * 
     * @param int $days 统计天数
     * @return array 告警统计数据
     */
    public function getAlertStats(int $days = 30): array {
        $startDate = date('Y-m-d', strtotime("-{$days} days"));
        
        // 从数据库获取告警统计数据
        $alertStats = $this->db->getRows(
            "SELECT 
                date(created_at) as date,
                severity,
                count(*) as count
             FROM system_alerts
             WHERE created_at >= ?
             GROUP BY date(created_at), severity
             ORDER BY date ASC",
            [['value' => $startDate, 'type' => 's']]
        );
        
        // 格式化数据以适应图表需求
        $result = [
            'critical' => [],
            'warning' => [],
            'info' => []
        ];
        
        foreach ($alertStats as $stat) {
            $severity = strtolower($stat['severity']);
            if (isset($result[$severity])) {
                $result[$severity][] = [
                    $stat['date'],
                    intval($stat['count'])
                ];
            }
        }
        
        return $result;
    }

    /**
     * 触发告警
     * 
     * @param string $type 告警类型
     * @param string $message 告警消息
     * @param string $severity 告警严重程度 (critical, warning, info)
     */
    private function triggerAlert(string $type, string $message, string $severity = 'warning'): void {
        $alert = [
            'type' => $type,
            'message' => $message,
            'severity' => $severity,
            'timestamp' => time()
        ];
        
        // 记录告警到Redis
        $this->redis->lPush('system:alerts', json_encode($alert));
        
        // 记录告警到日志
        $this->logger->error($message, $alert); // 使用error方法替代不存在的alert方法
        
        // 记录告警到数据库
        $this->db->insert('system_alerts', [
            'type' => $type,
            'message' => $message,
            'severity' => $severity,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        // 发送通知
        $this->sendNotification($alert);
    }

    /**
     * 发送告警通知
     * 
     * @param array $alert 告警数据
     */
    private function sendNotification(array $alert): void {
        // 实现通知发送逻辑(邮件、短信等)
    }
}
