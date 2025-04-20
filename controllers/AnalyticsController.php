<?php
namespace Controllers;

class AnalyticsController {
    private $database;
    private $alertService;
    
    public function dashboard() {
        include __DIR__ . '/../views/analytics/dashboard.php';
    }
    
    public function getMetricsData(): array {
        return [
            'performance' => $this->getPerformanceMetrics(),
            'alerts' => $this->getAlertMetrics(),
            'predictions' => $this->getPredictionMetrics()
        ];
    }
}
