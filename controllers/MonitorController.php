<?php
namespace Controllers;

use Services\TestMetricsService;
use Services\TestReportService;

class MonitorController
{
    private $metricsService;
    private $reportService;
    
    public function __construct()
    {
        $this->metricsService = new TestMetricsService();
        $this->reportService = new TestReportService($this->metricsService);
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
}