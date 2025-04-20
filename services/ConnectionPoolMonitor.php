<?php
namespace Services;

class ConnectionPoolMonitor {
    private $redis;
    private $alertService;
    private $metricsPrefix = 'db:pool:metrics:';
    
    public function recordMetrics(array $metrics): void {
        // 增加更多性能指标
        $enhancedMetrics = array_merge($metrics, [
            'memory_usage' => memory_get_usage(true),
            'cpu_load' => sys_getloadavg()[0],
            'query_qps' => $this->calculateQPS(),
            'slow_queries' => $this->getSlowQueryCount(),
            'deadlock_count' => $this->getDeadlockCount(),
            'cache_hit_rate' => $this->getCacheHitRate(),
            'transaction_time' => $this->getAvgTransactionTime()
        ]);

        // 记录到Redis时间序列
        $this->storeTimeSeriesData($enhancedMetrics);
        
        // 检查告警阈值
        $this->alertService->checkMetrics($enhancedMetrics);
    }

    private function calculateQPS(): float {
        $window = 60; // 1分钟窗口
        $queries = $this->redis->lLen('queries:history');
        return $queries / $window;
    }

    private function getSlowQueryCount(): int {
        return (int)$this->redis->get('stats:slow_queries') ?? 0;
    }

    private function storeTimeSeriesData(array $metrics): void {
        $timestamp = time();
        $key = "metrics:{$timestamp}";
        
        $this->redis->multi();
        $this->redis->hMSet($key, $metrics);
        $this->redis->expire($key, 86400); // 保留24小时
        $this->redis->exec();
    }

    public function getMetricsSummary(int $minutes = 5): array {
        $end = time();
        $start = $end - ($minutes * 60);
        
        $summary = [
            'avg_response_time' => [],
            'max_response_time' => [],
            'connection_usage' => [],
            'error_rates' => []
        ];

        for ($ts = $start; $ts <= $end; $ts += 60) {
            $data = $this->redis->hGetAll("metrics:{$ts}");
            if ($data) {
                $summary['avg_response_time'][] = [
                    'timestamp' => $ts,
                    'value' => $data['avg_response']
                ];
                // ...other metrics
            }
        }

        return $summary;
    }

    public function getPoolStatus(): array {
        $now = time();
        $metrics = [];
        
        // 获取最近1小时的指标
        for ($i = 0; $i < 60; $i++) {
            $ts = $now - ($i * 60);
            $data = $this->redis->hGetAll($this->metricsPrefix . $ts);
            if ($data) {
                $metrics[] = array_merge(['timestamp' => $ts], $data);
            }
        }
        
        return $metrics;
    }
}
