<?php
namespace Services;

class MetricsAnalysisService {
    private $db;
    private $cache;
    
    public function __construct() {
        $this->db = \Libs\DatabaseHelper::getInstance();
        $this->cache = new \Redis();
    }

    public function analyzePerformanceTrends(array $params): array {
        $cacheKey = "analysis:performance:" . md5(json_encode($params));
        
        if ($cached = $this->cache->get($cacheKey)) {
            return json_decode($cached, true);
        }

        // 性能指标分析
        $metrics = $this->db->getRows(
            "SELECT date(created_at) as date,
                    avg(response_time) as avg_response,
                    avg(memory_usage) as avg_memory,
                    avg(cpu_usage) as avg_cpu
             FROM performance_metrics
             WHERE created_at >= ?
             GROUP BY date(created_at)",
            [['value' => date('Y-m-d', strtotime('-30 days')), 'type' => 's']]
        );

        // 计算趋势和异常值
        $analysis = [
            'trends' => $this->calculateTrends($metrics),
            'anomalies' => $this->detectAnomalies($metrics),
            'predictions' => $this->predictNextDayMetrics($metrics)
        ];

        // 缓存分析结果
        $this->cache->setex($cacheKey, 3600, json_encode($analysis));
        
        return $analysis;
    }

    private function calculateTrends(array $metrics): array {
        $trends = [];
        foreach (['response_time', 'memory_usage', 'cpu_usage'] as $metric) {
            $values = array_column($metrics, "avg_$metric");
            $trends[$metric] = [
                'mean' => array_sum($values) / count($values),
                'min' => min($values),
                'max' => max($values),
                'trend' => $this->calculateLinearTrend($values)
            ];
        }
        return $trends;
    }

    private function detectAnomalies(array $metrics): array {
        $anomalies = [];
        foreach ($metrics as $metric) {
            // 使用3-sigma法则检测异常值
            $stdDev = $this->calculateStandardDeviation($metrics);
            $mean = array_sum(array_column($metrics, 'avg_response')) / count($metrics);
            
            if (abs($metric['avg_response'] - $mean) > 3 * $stdDev) {
                $anomalies[] = [
                    'date' => $metric['date'],
                    'value' => $metric['avg_response'],
                    'type' => 'response_time'
                ];
            }
        }
        return $anomalies;
    }

    private function calculateStandardDeviation(array $metrics): float {
        $values = array_column($metrics, 'avg_response');
        $mean = array_sum($values) / count($values);
        $squareSum = array_sum(array_map(fn($x) => pow($x - $mean, 2), $values));
        return sqrt($squareSum / count($values));
    }
}
