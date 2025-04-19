<?php
namespace Services;

use Libs\DatabaseHelper;
use Libs\SecurityAuditHelper;

class ContractRiskPredictor {
    private $db;
    private $modelPath = __DIR__.'/../../storage/models/contract_risk.h5';
    
    public function __construct() {
        $this->db = DatabaseHelper::getInstance();
    }
    
    public function predictRisk(array $contractData): array {
        $features = $this->extractFeatures($contractData);
        $riskScore = $this->runModelPrediction($features);
        
        $result = [
            'risk_score' => $riskScore,
            'vulnerability_types' => $this->classifyVulnerabilities($riskScore, $features),
            'recommendations' => $this->generateRecommendations($riskScore)
        ];
        
        SecurityAuditHelper::audit('contract_risk', json_encode($result));
        return $result;
    }
    
    private function extractFeatures(array $contractData): array {
        return [
            'opcode_complexity' => $this->calculateOpcodeComplexity($contractData['bytecode']),
            'external_calls' => count($contractData['external_calls']),
            'state_variables' => count($contractData['state_variables']),
            'gas_usage' => $contractData['gas_estimate']
        ];
    }
    
    private function runModelPrediction(array $features): float {
        if (!file_exists($this->modelPath)) {
            return 0.0;
        }
        
        try {
            $output = shell_exec("python3 {$this->modelPath} " . 
                escapeshellarg(json_encode($features)));
            return (float)trim($output);
        } catch (\Exception $e) {
            SecurityAuditHelper::audit('model_error', $e->getMessage());
            return 0.0;
        }
    }
    
    private function classifyVulnerabilities(float $score, array $features): array {
        $vulnerabilities = [];
        
        if ($score > 0.7) {
            $vulnerabilities[] = '重入攻击风险';
        }
        
        if ($features['external_calls'] > 5 && $score > 0.5) {
            $vulnerabilities[] = '外部调用风险';
        }
        
        if ($features['gas_usage'] > 3000000) {
            $vulnerabilities[] = 'Gas耗尽风险';
        }
        
        return $vulnerabilities;
    }
    
    private function generateRecommendations(float $score): array {
        $recommendations = [];
        
        if ($score > 0.7) {
            $recommendations[] = '建议使用重入防护模式';
            $recommendations[] = '建议进行全面的安全审计';
        } elseif ($score > 0.5) {
            $recommendations[] = '建议限制外部调用数量';
            $recommendations[] = '建议增加状态变量访问控制';
        }
        
        return $recommendations;
    }
    
    private function calculateOpcodeComplexity(string $bytecode): float {
        $uniqueOpcodes = count(array_unique(str_split($bytecode, 2)));
        return min($uniqueOpcodes / 100, 1.0);
    }
}