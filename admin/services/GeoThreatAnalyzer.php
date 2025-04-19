<?php
namespace Admin\Services;

use Libs\GeoIPMapper;
use Libs\DatabaseHelper;
use Libs\SecurityAuditHelper;

class GeoThreatAnalyzer {
    private $db;
    private $modelPath = __DIR__.'/../../../storage/models/lstm_threat_detection.h5';

    public function __construct() {
        $this->db = DatabaseHelper::getInstance();
    }

    public function mapAttackOrigin(array $threatData): array {
        $geoData = GeoIPMapper::mapIP($threatData['source_ip']);
        
        $enhancedData = array_merge($threatData, [
            'geo' => $geoData,
            'risk_score' => $this->calculateRiskScore($threatData),
            'pattern_type' => $this->analyzeAttackPattern($threatData)
        ]);

        $this->db->logAudit('threat_geo', 0, $enhancedData);
        return $enhancedData;
    }

    public function processLiveThreat(array $streamData): void {
        $this->mapAttackOrigin($streamData);
        
        if ($this->detectAnomaliesWithLSTM($streamData)) {
            SecurityAuditHelper::audit('lstm_anomaly', 
                "检测到异常行为模式: " . json_encode($streamData)
            );
        }
    }

    private function calculateRiskScore(array $data): float {
        $weights = ['request_freq' => 0.35, 'payload_size' => 0.25, 
                   'endpoint_sensitivity' => 0.4];
        return (
            $data['request_freq'] * $weights['request_freq'] +
            log($data['payload_size'] + 1) * $weights['payload_size'] +
            ($data['endpoint_sensitivity'] / 10) * $weights['endpoint_sensitivity']
        ) * 100;
    }

    private function analyzeAttackPattern(array $data): string {
        $patterns = [
            'SQLi' => ['/SELECT.*FROM/i', '/UNION.*SELECT/i'],
            'XSS' => ['/<script>/i', '/alert\(/i'],
            'BruteForce' => ['/login.*3次失败/i']
        ];

        foreach ($patterns as $type => $regexList) {
            foreach ($regexList as $regex) {
                if (preg_match($regex, $data['request_data'])) {
                    return $type;
                }
            }
        }
        
        return $this->detectStatisticalAnomaly($data) ? '新型攻击' : '正常流量';
    }

    private function detectStatisticalAnomaly(array $data): bool {
        $baseline = $this->db->getThreatBaseline('hourly');
        return $data['request_freq'] > ($baseline['avg'] + 3 * $baseline['std_dev']);
    }

    private function detectAnomaliesWithLSTM(array $data): bool {
        if (!file_exists($this->modelPath)) {
            return false;
        }

        try {
            // 调用Python LSTM模型进行实时检测
            $output = shell_exec("python3 {$this->modelPath} " . 
                escapeshellarg(json_encode($data)));
            return trim($output) === 'anomaly';
        } catch (\Exception $e) {
            SecurityAuditHelper::audit('model_error', $e->getMessage());
            return false;
        }
    }
}