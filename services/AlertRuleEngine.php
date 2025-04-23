<?php
namespace App\Services;

class AlertRuleEngine {
    private $rules = [];
    private $actions = [];
    private $chainedRules = [];
    
    public function addRule(string $name, callable $condition, array $actions): self {
        $this->rules[$name] = [
            'condition' => $condition,
            'actions' => $actions
        ];
        return $this;
    }
    
    public function chainRules(string $sourceRule, string $targetRule, array $conditions): self {
        $this->chainedRules[] = [
            'source' => $sourceRule,
            'target' => $targetRule,
            'conditions' => $conditions,
            'delay' => $conditions['delay'] ?? 0
        ];
        return $this;
    }
    
    public function evaluate(array $metrics): array {
        $triggeredRules = [];
        
        foreach ($this->rules as $name => $rule) {
            if (($rule['condition'])($metrics)) {
                $triggeredRules[] = $name;
                $this->executeActions($rule['actions'], $metrics);
                
                // 检查并触发链式规则
                $this->evaluateChainedRules($name, $metrics);
            }
        }
        
        return $triggeredRules;
    }
    
    private function evaluateChainedRules(string $sourceRule, array $metrics): void {
        foreach ($this->chainedRules as $chain) {
            if ($chain['source'] === $sourceRule) {
                if ($this->checkChainConditions($chain['conditions'], $metrics)) {
                    // 延迟执行目标规则
                    if ($chain['delay'] > 0) {
                        $this->scheduleRuleExecution($chain['target'], $metrics, $chain['delay']);
                    } else {
                        $this->executeRule($chain['target'], $metrics);
                    }
                }
            }
        }
    }
}