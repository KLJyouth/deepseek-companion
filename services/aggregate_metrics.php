<?php
require_once __DIR__.'/../libs/Bootstrap.php';
require_once __DIR__.'/../libs/DatabaseHelper.php';

class MetricAggregator {
    private $db;
    
    public function __construct() {
        $this->db = new DatabaseHelper();
    }
    
    public function run() {
        // 每小时执行一次聚合
        $this->aggregateHourly();
        
        // 每天执行一次日聚合
        if (date('H') === '00') {
            $this->aggregateDaily();
        }
    }
    
    private function aggregateHourly() {
        $lastHour = time() - 3600;
        $metrics = ['cpu', 'memory', 'concurrent', 'throughput'];
        
        foreach ($metrics as $metric) {
            $this->db->query(
                "INSERT INTO metric_hourly (metric, timestamp, avg_value, min_value, max_value)
                 SELECT 
                    metric,
                    UNIX_TIMESTAMP(FROM_UNIXTIME(timestamp, '%Y-%m-%d %H:00:00')),
                    AVG(value),
                    MIN(value),
                    MAX(value)
                 FROM system_metrics
                 WHERE metric = ? AND timestamp >= ?
                 GROUP BY FROM_UNIXTIME(timestamp, '%Y-%m-%d %H')",
                [['value' => $metric, 'type' => 's'], ['value' => $lastHour, 'type' => 'i']]
            );
        }
    }
    
    private function aggregateDaily() {
        $lastDay = time() - 86400;
        $metrics = ['cpu', 'memory', 'concurrent', 'throughput'];
        
        foreach ($metrics as $metric) {
            $this->db->query(
                "INSERT INTO metric_daily (metric, timestamp, avg_value, min_value, max_value)
                 SELECT 
                    metric,
                    UNIX_TIMESTAMP(FROM_UNIXTIME(timestamp, '%Y-%m-%d 00:00:00')),
                    AVG(value),
                    MIN(value),
                    MAX(value)
                 FROM system_metrics
                 WHERE metric = ? AND timestamp >= ?
                 GROUP BY FROM_UNIXTIME(timestamp, '%Y-%m-%d')",
                [['value' => $metric, 'type' => 's'], ['value' => $lastDay, 'type' => 'i']]
            );
        }
    }
}

// 执行聚合
$aggregator = new MetricAggregator();
$aggregator->run();