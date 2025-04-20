<?php
namespace Services;

class AlertService {
    private $redis;
    private $thresholds = [
        'connection_wait' => 200,    // ms
        'pool_usage' => 80,         // %
        'query_time' => 1000,       // ms
        'error_rate' => 0.01        // 1%
    ];

    public function __construct() {
        $this->redis = new \Redis();
        $this->logger = LogHelper::getInstance();
    }

    public function checkMetrics(array $metrics): void {
        // 检查连接池使用率
        $poolUsage = ($metrics['active'] / ($metrics['active'] + $metrics['idle'])) * 100;
        if ($poolUsage > $this->thresholds['pool_usage']) {
            $this->triggerAlert('pool_usage', sprintf(
                "连接池使用率过高: %.2f%%", 
                $poolUsage
            ));
        }

        // 检查查询响应时间
        if ($metrics['avg_response'] > $this->thresholds['query_time']) {
            $this->triggerAlert('query_time', sprintf(
                "查询响应时间过长: %.2fms", 
                $metrics['avg_response']
            ));
        }
    }

    private function triggerAlert(string $type, string $message): void {
        $alert = [
            'type' => $type,
            'message' => $message,
            'timestamp' => time()
        ];
        
        // 记录告警
        $this->redis->lPush('system:alerts', json_encode($alert));
        $this->logger->alert($message, $alert);

        // 发送通知
        $this->sendNotification($alert);
    }

    private function sendNotification(array $alert): void {
        // 实现通知发送逻辑(邮件、短信等)
    }
}
