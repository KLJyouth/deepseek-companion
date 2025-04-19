<?php
namespace Admin\Controllers;

use Libs\DatabaseHelper;
use Admin\Services\GeoThreatAnalyzer;

class SecurityDashboardController {
    private $db;
    private $geoAnalyzer;

    public function __construct() {
        $this->db = DatabaseHelper::getInstance();
        $this->geoAnalyzer = new GeoThreatAnalyzer();
    }

    public function renderGlobalThreatMap() {
        $threats = $this->db->query("SELECT * FROM threat_log WHERE timestamp > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        $processedData = array_map([$this->geoAnalyzer, 'mapAttackOrigin'], $threats);

        header('Content-Type: application/json');
        echo json_encode([
            'nodes' => $this->generate3DNodes($processedData),
            'paths' => $this->calculateAttackPaths($processedData)
        ]);
    }

    private function generate3DNodes(array $data): array {
        return array_map(function($item) {
            return [
                'x' => $item['geo']['longitude'] * 100,
                'y' => $item['geo']['latitude'] * 100,
                'z' => log($item['risk_score']) * 50,
                'color' => $this->getThreatColor($item['risk_score']),
                'tooltip' => "{$item['geo']['city']} - {$item['pattern_type']}"
            ];
        }, $data);
    }

    private function calculateAttackPaths(array $data): array {
        $paths = [];
        foreach ($data as $entry) {
            $paths[] = [
                'from' => [$entry['geo']['longitude'] * 100, $entry['geo']['latitude'] * 100, 0],
                'to' => [0, 0, 500],  // 攻击目标指向数据中心
                'color' => $this->getPathColor($entry['risk_score'])
            ];
        }
        return $paths;
    }

    private function getThreatColor(float $score): string {
        $hue = max(0, 120 - ($score * 1.2));
        return "hsl($hue, 100%, 50%)";
    }

    private function getPathColor(float $score): string {
        $alpha = min(0.8, $score / 100);
        return "rgba(255, 0, 0, $alpha)";
    }
}