<?php
namespace Services;

class ModelPerformanceMonitor {
    private $redis;
    private $logger;
    private const METRICS_KEY = 'model:metrics:';
    
    public function __construct() {
        $this->redis = new \Redis();
        $this->logger = LogHelper::getInstance();
    }

    public function recordPrediction($modelId, $input, $output, $duration) {
        $metrics = [
            'timestamp' => microtime(true),
            'duration_ms' => $duration * 1000,
            'memory_usage' => memory_get_peak_usage(true),
            'input_size' => strlen(serialize($input)),
            'accuracy' => $this->calculateAccuracy($output)
        ];

        $this->redis->hMSet(self::METRICS_KEY . $modelId, $metrics);
        $this->redis->expire(self::METRICS_KEY . $modelId, 86400); // 24小时过期
    }

    public function getModelMetrics($modelId, $timeRange = 3600): array {
        return [
            'avg_duration' => $this->calculateAverageDuration($modelId, $timeRange),
            'accuracy_trend' => $this->getAccuracyTrend($modelId, $timeRange),
            'resource_usage' => $this->getResourceUsage($modelId),
            'anomalies' => $this->detectAnomalies($modelId)
        ];
    }
}
