<?php
namespace App\Controllers;

use App\Libs\DatabaseHelper;
use App\Services\AlertService;
use App\Services\AdvancedAnalyticsService;
use App\Services\MetricsAnalysisService;

/**
 * 分析控制器 - 负责处理系统性能分析和指标展示
 * 
 * @copyright 版权所有 © 广西港妙科技有限公司
 * @version 1.0.0
 */
class AnalyticsController {
    private $database;
    private $alertService;
    private $analyticsService;
    private $metricsService;
    
    /**
     * 构造函数 - 初始化所需服务
     */
    public function __construct() {
        $this->database = DatabaseHelper::getInstance();
        $this->alertService = new AlertService();
        $this->analyticsService = new AdvancedAnalyticsService();
        $this->metricsService = new MetricsAnalysisService();
    }
    
    /**
     * 显示分析仪表板页面
     */
    public function dashboard() {
        include __DIR__ . '/../views/analytics/dashboard.php';
    }
    
    /**
     * 获取所有指标数据
     * 
     * @return array 包含性能、告警和预测数据的数组
     */
    public function getMetricsData(): array {
        return [
            'performance' => $this->getPerformanceMetrics(),
            'alerts' => $this->getAlertMetrics(),
            'predictions' => $this->getPredictionMetrics()
        ];
    }
    
    /**
     * 获取性能指标数据
     * 
     * @return array 性能指标数据
     */
    private function getPerformanceMetrics(): array {
        $rawMetrics = $this->database->getRows(
            "SELECT date(created_at) as date, 
                    avg(cpu_usage) as cpu, 
                    avg(memory_usage) as memory 
             FROM performance_metrics 
             WHERE created_at >= ? 
             GROUP BY date(created_at) 
             ORDER BY date ASC",
            [['value' => date('Y-m-d', strtotime('-30 days')), 'type' => 's']]
        );
        
        $formattedMetrics = [
            'cpu' => [],
            'memory' => []
        ];
        
        foreach ($rawMetrics as $metric) {
            $formattedMetrics['cpu'][] = [
                $metric['date'], 
                floatval($metric['cpu'])
            ];
            $formattedMetrics['memory'][] = [
                $metric['date'], 
                floatval($metric['memory'])
            ];
        }
        
        return $formattedMetrics;
    }
    
    /**
     * 获取告警指标数据
     * 
     * @return array 告警指标数据
     */
    private function getAlertMetrics(): array {
        $alertData = $this->alertService->getAlertStats(30); // 获取30天的告警统计
        
        return [
            'critical' => $alertData['critical'] ?? [],
            'warning' => $alertData['warning'] ?? [],
            'info' => $alertData['info'] ?? []
        ];
    }
    
    /**
     * 获取预测指标数据
     * 
     * @return array 预测指标数据
     */
    private function getPredictionMetrics(): array {
        $metrics = $this->metricsService->analyzePerformanceTrends([
            'period' => 30,
            'interval' => 'day'
        ]);
        
        return [
            'trends' => $metrics['trends'] ?? [],
            'predictions' => $metrics['predictions'] ?? [],
            'anomalies' => $metrics['anomalies'] ?? []
        ];
    }
    
    /**
     * 导出分析报告
     * 
     * @return string 报告文件路径
     */
    public function exportAnalyticsReport(): string {
        $data = $this->getMetricsData();
        $timestamp = date('YmdHis');
        $filename = "analytics_report_{$timestamp}.pdf";
        $filepath = __DIR__ . "/../reports/{$filename}";
        
        // 确保目录存在
        if (!is_dir(dirname($filepath))) {
            mkdir(dirname($filepath), 0755, true);
        }
        
        // 生成报告逻辑...
        
        return $filepath;
    }
}