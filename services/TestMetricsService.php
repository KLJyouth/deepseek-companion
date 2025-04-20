<?php
namespace Services;

class TestMetricsService {
    private $db;
    private $thresholds = [
        'response_time' => 2000, // 毫秒
        'memory_usage' => 128,   // MB
        'error_rate' => 0.01,    // 1%
        'cpu_usage' => 80        // 80%
    ];

    public function __construct() {
        $this->db = \Libs\DatabaseHelper::getInstance();
    }

    public function collectMetrics(): array {
        return [
            'performance' => $this->getPerformanceMetrics(),
            'security' => $this->getSecurityMetrics(),
            'compliance' => $this->getComplianceMetrics(),
            'reliability' => $this->getReliabilityMetrics()
        ];
    }

    private function getPerformanceMetrics(): array {
        $metrics = [
            'response_time' => $this->measureResponseTime(),
            'memory_usage' => memory_get_peak_usage(true) / 1024 / 1024,
            'cpu_usage' => sys_getloadavg()[0] * 100,
            'db_queries' => $this->countDatabaseQueries()
        ];

        return $this->checkThresholds('performance', $metrics);
    }

    private function getSecurityMetrics(): array {
        return [
            'failed_logins' => $this->getFailedLoginCount(),
            'suspicious_ips' => $this->getSuspiciousIPCount(),
            'encryption_checks' => $this->performEncryptionChecks()
        ];
    }

    private function checkThresholds(string $category, array $metrics): array {
        $alerts = [];
        foreach ($metrics as $key => $value) {
            if (isset($this->thresholds[$key]) && $value > $this->thresholds[$key]) {
                $alerts[] = "Warning: $key exceeds threshold ({$value} > {$this->thresholds[$key]})";
            }
        }
        
        return [
            'data' => $metrics,
            'alerts' => $alerts,
            'status' => empty($alerts) ? 'normal' : 'warning'
        ];
    }
}
