<?php

use Libs\CryptoHelper;
use Libs\DatabaseHelper;

require_once __DIR__.'/../services/SystemMonitorService.php';

class MonitorController {
    private $monitor;
    
    public function __construct() {
        $this->monitor = new SystemMonitorService();
        $this->checkAdminAccess();
    }
    
    private function checkAdminAccess() {
        if (!isset($_SESSION['admin_id'])) {
            header('HTTP/1.1 403 Forbidden');
            exit;
        }
    }
    
    public function dashboard() {
        $data = [
            'current' => $this->monitor->getCurrentLoad(),
            'trend' => $this->monitor->getLoadTrend()
        ];
        
        header('Content-Type: application/json');
        echo json_encode($data);
    }
    
    public function metrics() {
        $start = $_GET['start'] ?? time() - 3600; // 默认1小时
        $end = $_GET['end'] ?? time();
        
        $data = [
            'cpu' => $this->getMetricData('cpu', $start, $end),
            'memory' => $this->getMetricData('memory', $start, $end),
            'concurrent' => $this->getMetricData('concurrent', $start, $end)
        ];
        
        header('Content-Type: application/json');
        echo json_encode($data);
    }
    
    private function getMetricData(string $metric, int $start, int $end): array {
        $db = new DatabaseHelper();
        $range = $end - $start;
        
        // 根据时间范围自动选择聚合级别
        if ($range > 86400 * 7) { // 大于7天使用小时聚合
            $query = "SELECT 
                UNIX_TIMESTAMP(FROM_UNIXTIME(timestamp, '%Y-%m-%d %H:00:00')) as timestamp,
                AVG(value) as value
                FROM system_metrics 
                WHERE metric = ? AND timestamp BETWEEN ? AND ?
                GROUP BY FROM_UNIXTIME(timestamp, '%Y-%m-%d %H')
                ORDER BY timestamp";
        } elseif ($range > 86400) { // 1-7天使用10分钟聚合
            $query = "SELECT 
                UNIX_TIMESTAMP(FROM_UNIXTIME(timestamp, '%Y-%m-%d %H:%i:00')) as timestamp,
                AVG(value) as value
                FROM system_metrics 
                WHERE metric = ? AND timestamp BETWEEN ? AND ?
                GROUP BY FROM_UNIXTIME(timestamp, '%Y-%m-%d %H'), FLOOR(MINUTE(FROM_UNIXTIME(timestamp))/10)
                ORDER BY timestamp";
        } else { // 小于1天使用原始数据
            $query = "SELECT timestamp, value FROM system_metrics 
                     WHERE metric = ? AND timestamp BETWEEN ? AND ?
                     ORDER BY timestamp";
        }
        
        $result = $db->query($query, [
            ['value' => $metric, 'type' => 's'],
            ['value' => $start, 'type' => 'i'],
            ['value' => $end, 'type' => 'i']
        ]);
        
        $labels = [];
        $data = [];
        
        foreach ($result as $row) {
            $labels[] = date('H:i', $row['timestamp']);
            $data[] = $metric === 'concurrent' ? $row['value'] : $row['value'] * 100;
        }
        
        return [
            'labels' => $labels,
            'data' => $data
        ];
    }
}