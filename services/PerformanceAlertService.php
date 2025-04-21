<?php
namespace Services;

use Redis;
use RedisException;
use LibsLogHelper;

class PerformanceAlertService {
    private Redis $redis;
    private array $alertConfig;
    
    public function __construct(
        private string $configPath = __DIR__ . '/../config/alert_thresholds.php',
        private string $host = '127.0.0.1',
        private int $port = 6379,
        private float $timeout = 2.5
    ) {
        $this->redis = new Redis();
        $this->alertConfig = require $configPath;
        
        try {
            if (!$this->redis->connect($host, $port, $timeout)) {
                throw new RedisException('Redis连接失败');
            }
        } catch (RedisException $e) {
            LogHelper::getInstance()->error('Redis连接异常: ' . $e->getMessage());
            throw $e;
        }
    }
    
    public function checkPerformanceMetrics(array $metrics): void {
        $alerts = [];
        
        // CPU使用率检查
        if ($metrics['cpu_usage'] > $this->alertConfig['cpu_critical']) {
            $alerts[] = $this->createAlert('critical', 'CPU使用率过高', $metrics['cpu_usage']);
        }
        
        // 内存使用检查
        if ($metrics['memory_usage'] > $this->alertConfig['memory_critical']) {
            $alerts[] = $this->createAlert('critical', '内存使用率过高', $metrics['memory_usage']);
        }
        
        // 响应时间检查
        if ($metrics['response_time'] > $this->alertConfig['response_critical']) {
            $alerts[] = $this->createAlert('critical', '响应时间异常', $metrics['response_time']);
        }
        
        if (!empty($alerts)) {
            $this->dispatchAlerts($alerts);
        }
    }
    
    private function dispatchAlerts(array $alerts): void {
        foreach ($alerts as $alert) {
            // 发送告警
            $this->sendNotification($alert);
            
            // 记录告警历史
            $this->logAlert($alert);
            
            // 触发自动恢复机制
            if ($alert['level'] === 'critical') {
                $this->triggerAutoRecovery($alert);
            }
        }
    }
    
    private function sendNotification(array $alert): void {
        // 实现多通道告警发送
        $channels = ['email', 'sms', 'webhook'];
        foreach ($channels as $channel) {
            NotificationService::send($channel, $alert);
        }
    }
}
