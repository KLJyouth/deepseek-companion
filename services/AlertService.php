<?php
namespace Services;

use Libs\LogHelper;
use RedisException;

class AlertService {
    private \Redis $redis;
    private array $thresholds = [
        'connection_wait' => 200,
        'pool_usage' => 80,
        'query_time' => 1000,
        'error_rate' => 0.01
    ];

    public function __construct(
        private string $host = '127.0.0.1',
        private int $port = 6379,
        private float $timeout = 2.5,
        private int $retryInterval = 100
    ) {
        $this->redis = new \Redis();
        $this->logger = LogHelper::getInstance();

        try {
            if (!$this->redis->connect($host, $port, $timeout, null, $retryInterval)) {
                throw new RedisException('Redis连接失败');
            }
        } catch (RedisException $e) {
            $this->logger->error('Redis连接异常: ' . $e->getMessage());
            throw $e;
        }
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
