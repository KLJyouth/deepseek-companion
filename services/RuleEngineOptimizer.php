<?php
namespace Services;

class RuleEngineOptimizer {
    private $redis;
    private $ruleCache = [];
    private $compiledRules = [];
    
    public function __construct() {
        $this->redis = new \Redis();
    }

    public function optimizeRules(array $rules): array {
        $optimized = [];
        
        foreach ($rules as $rule) {
            // 预编译规则条件
            $compiledCondition = $this->compileCondition($rule['condition']);
            
            // 优化规则执行顺序
            $priority = $this->calculatePriority($rule);
            
            $optimized[] = [
                'id' => $rule['id'],
                'compiled_condition' => $compiledCondition,
                'priority' => $priority,
                'actions' => $rule['actions']
            ];
        }
        
        // 按优先级排序
        usort($optimized, fn($a, $b) => $b['priority'] - $a['priority']);
        
        return $optimized;
    }

    private function compileCondition(string $condition): callable {
        $cacheKey = "rule:compiled:" . md5($condition);
        
        if (isset($this->compiledRules[$cacheKey])) {
            return $this->compiledRules[$cacheKey];
        }

        $compiled = eval('return function($data) { return ' . $condition . '; };');
        $this->compiledRules[$cacheKey] = $compiled;
        
        return $compiled;
    }

    public function batchEvaluate(array $optimizedRules, array $data): array {
        $results = [];
        $context = [];

        foreach ($optimizedRules as $rule) {
            if ($this->evaluateWithCache($rule, $data, $context)) {
                $results[] = $rule['id'];
                
                // 更新执行上下文
                $context[$rule['id']] = true;
                
                // 检查是否需要提前退出
                if ($this->shouldBreakEvaluation($rule, $context)) {
                    break;
                }
            }
        }

        return $results;
    }

    private function evaluateWithCache(array $rule, array $data, array $context): bool {
        $cacheKey = "rule:result:" . $rule['id'] . ":" . md5(json_encode($data));
        
        if ($cached = $this->redis->get($cacheKey)) {
            return (bool)$cached;
        }

        $result = ($rule['compiled_condition'])($data);
        $this->redis->setex($cacheKey, 60, (int)$result);
        
        return $result;
    }
}
