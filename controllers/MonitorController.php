<?php
namespace Controllers;

use Services\TestMetricsService;
use Services\TestReportService;

class MonitorController
{
    private $metricsService;
    private $reportService;
    private $monitor;
    private $rules;
    
    public function __construct()
    {
        $this->metricsService = new TestMetricsService();
        $this->reportService = new TestReportService($this->metricsService);
        $this->monitor = new \Services\ConnectionPoolMonitor();
        $this->rules = require __DIR__ . '/../config/alert_rules.php';
    }
    
    public function dashboard()
    {
        $metrics = $this->metricsService->collectMetrics();
        $thresholds = require __DIR__ . '/../config/thresholds.php';
        
        return $this->render('monitor/dashboard', [
            'metrics' => $metrics,
            'thresholds' => $thresholds
        ]);
    }
    
    public function exportReport()
    {
        $format = $_GET['format'] ?? 'pdf';
        $report = $this->reportService->generateReport($format);
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="report.' . $format . '"');
        echo $report;
    }
    
    public function updateThresholds()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $thresholds = $_POST['thresholds'] ?? [];
            file_put_contents(
                __DIR__ . '/../config/thresholds.php',
                '<?php return ' . var_export($thresholds, true) . ';'
            );
            return ['success' => true];
        }
    }
    
    public function getMetrics() {
        header('Content-Type: application/json');
        echo json_encode($this->monitor->getMetricsSummary());
    }
    
    public function exportMetrics() {
        $metrics = $this->monitor->getMetricsSummary(1440); // 获取24小时数据
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="metrics_' . date('Ymd_His') . '.csv"');
        
        $fp = fopen('php://output', 'w');
        
        // 写入CSV头
        fputcsv($fp, ['Timestamp', 'Active Connections', 'Response Time', 'Error Rate']);
        
        // 写入数据
        foreach ($metrics as $metric) {
            fputcsv($fp, [
                date('Y-m-d H:i:s', $metric['timestamp']),
                $metric['active_connections'],
                $metric['avg_response_time'],
                $metric['error_rate']
            ]);
        }
        
        fclose($fp);
    }
    
    public function updateAlertRules() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        
        $rules = json_decode(file_get_contents('php://input'), true);
        
        // 验证并保存新规则
        if ($this->validateRules($rules)) {
            file_put_contents(
                __DIR__ . '/../config/alert_rules.php',
                '<?php return ' . var_export($rules, true) . ';'
            );
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
        }
    }
    
    private function validateRules($rules) {
        // 实现规则验证逻辑
        return true;
    }

    public function metrics($start, $end) {
        // 获取时间标签
        $timeLabels = $this->getTimeLabels($start, $end);

        return [
            'cpu' => [
                'labels' => $timeLabels,
                'datasets' => [[
                    'label' => 'CPU使用率(%)',
                    'data' => $this->fetchDataFromSource('cpu', $start, $end),
                    'borderColor' => 'rgb(75, 192, 192)',
                    'tension' => 0.1
                ]]
            ],
            'memory' => [
                'labels' => $timeLabels,
                'datasets' => [[
                    'label' => '内存使用率(%)',
                    'data' => $this->fetchDataFromSource('memory', $start, $end),
                    'borderColor' => 'rgb(255, 99, 132)',
                    'tension' => 0.1
                ]]
            ],
            'concurrent' => [
                'labels' => $timeLabels,
                'datasets' => [[
                    'label' => '并发请求数',
                    'data' => $this->fetchDataFromSource('concurrent', $start, $end),
                    'borderColor' => 'rgb(54, 162, 235)',
                    'tension' => 0.1
                ]]
            ],
            'throughput' => [
                'labels' => $timeLabels,
                'datasets' => [[
                    'label' => '请求吞吐量',
                    'data' => $this->fetchDataFromSource('throughput', $start, $end),
                    'borderColor' => 'rgb(153, 102, 255)',
                    'tension' => 0.1
                ]]
            ]
        ];
    }

    private function getTimeLabels($start, $end) {
        $labels = [];
        $interval = ($end - $start) / 12; // 分成12段
        for ($i = 0; $i < 12; $i++) {
            $labels[] = date('H:i', $start + $i * $interval);
        }
        return $labels;
    }

    private function fetchDataFromSource($metric, $start, $end) {
        // 假设从数据库或API获取数据
        // 这里以数据库为例
        $db = $this->getDatabaseConnection();
        $query = $db->prepare("SELECT value FROM metrics WHERE metric = :metric AND timestamp BETWEEN :start AND :end ORDER BY timestamp ASC");
        $query->execute([
            ':metric' => $metric,
            ':start' => $start,
            ':end' => $end
        ]);
        return $query->fetchAll(PDO::FETCH_COLUMN);
    }

    private function getDatabaseConnection() {
        // 配置数据库连接
        $dsn = 'mysql:host=localhost;dbname=monitoring;charset=utf8mb4';
        $username = 'root';
        $password = '';
        return new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    }
}