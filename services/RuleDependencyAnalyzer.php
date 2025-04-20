<?php
namespace Services;

class RuleDependencyAnalyzer {
    private $rules = [];
    private $dependencyGraph = [];
    
    public function buildDependencyGraph(array $rules): array {
        $this->rules = $rules;
        $this->dependencyGraph = [];
        
        foreach ($rules as $ruleId => $rule) {
            $this->analyzeDependencies($ruleId, $rule);
        }
        
        return [
            'graph' => $this->dependencyGraph,
            'cycles' => $this->detectCycles(),
            'execution_order' => $this->calculateExecutionOrder()
        ];
    }
    
    private function analyzeDependencies(string $ruleId, array $rule): void {
        $this->dependencyGraph[$ruleId] = [];
        
        // 分析规则条件中的依赖
        if (isset($rule['condition'])) {
            preg_match_all('/\$rules\[([^\]]+)\]/', $rule['condition'], $matches);
            foreach ($matches[1] as $dep) {
                $this->dependencyGraph[$ruleId][] = trim($dep, '"\'');
            }
        }
        
        // 分析动作中的依赖
        if (isset($rule['actions'])) {
            foreach ($rule['actions'] as $action) {
                if (isset($action['requires'])) {
                    foreach ($action['requires'] as $dep) {
                        $this->dependencyGraph[$ruleId][] = $dep;
                    }
                }
            }
        }
    }
    
    private function detectCycles(): array {
        $visited = [];
        $cycles = [];
        
        foreach ($this->dependencyGraph as $ruleId => $deps) {
            $path = [];
            $this->dfs($ruleId, $visited, $path, $cycles);
        }
        
        return $cycles;
    }
    
    private function calculateExecutionOrder(): array {
        $visited = [];
        $order = [];
        
        foreach ($this->dependencyGraph as $ruleId => $deps) {
            $this->topologicalSort($ruleId, $visited, $order);
        }
        
        return array_reverse($order);
    }
    
    public function validateRules(): array {
        $issues = [];
        
        // 检查循环依赖
        $cycles = $this->detectCycles();
        if (!empty($cycles)) {
            $issues[] = [
                'type' => 'cycle_dependency',
                'rules' => $cycles,
                'severity' => 'error'
            ];
        }
        
        // 检查未定义的规则引用
        foreach ($this->dependencyGraph as $ruleId => $deps) {
            foreach ($deps as $dep) {
                if (!isset($this->rules[$dep])) {
                    $issues[] = [
                        'type' => 'undefined_rule',
                        'rule' => $ruleId,
                        'dependency' => $dep,
                        'severity' => 'error'
                    ];
                }
            }
        }
        
        return $issues;
    }
}
