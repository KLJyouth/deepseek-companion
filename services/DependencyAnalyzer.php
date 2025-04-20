<?php
namespace Services;

class DependencyAnalyzer {
    private $cache;
    private $dependencyGraph;
    private $visited;
    
    public function __construct() {
        $this->cache = new \Redis();
        $this->dependencyGraph = [];
        $this->visited = [];
    }
    
    public function analyzeDependencies(array $components): array {
        // 使用缓存优化重复分析
        $cacheKey = 'dep_analysis:' . md5(serialize($components));
        if ($cached = $this->cache->get($cacheKey)) {
            return unserialize($cached);
        }
        
        $this->buildDependencyGraph($components);
        
        $analysis = [
            'direct_dependencies' => $this->findDirectDependencies(),
            'circular_dependencies' => $this->findCircularDependencies(),
            'dependency_levels' => $this->calculateDependencyLevels(),
            'optimization_suggestions' => $this->generateOptimizationSuggestions()
        ];
        
        // 缓存分析结果
        $this->cache->setex($cacheKey, 3600, serialize($analysis));
        
        return $analysis;
    }
    
    private function buildDependencyGraph(array $components): void {
        foreach ($components as $component => $deps) {
            if (!isset($this->dependencyGraph[$component])) {
                $this->dependencyGraph[$component] = [
                    'direct' => [],
                    'indirect' => [],
                    'weight' => 0
                ];
            }
            
            // 使用深度优先搜索构建完整依赖图
            $this->dfsAnalyzeDependencies($component, $deps);
        }
    }
    
    private function dfsAnalyzeDependencies(string $component, array $deps, array $path = []): void {
        if (in_array($component, $path)) {
            // 检测到循环依赖
            $this->recordCircularDependency(array_merge($path, [$component]));
            return;
        }
        
        $path[] = $component;
        
        foreach ($deps as $dep) {
            $this->dependencyGraph[$component]['direct'][] = $dep;
            
            // 递归分析间接依赖
            if (isset($this->dependencyGraph[$dep])) {
                foreach ($this->dependencyGraph[$dep]['direct'] as $indirectDep) {
                    if (!in_array($indirectDep, $this->dependencyGraph[$component]['indirect'])) {
                        $this->dependencyGraph[$component]['indirect'][] = $indirectDep;
                    }
                }
                
                if (!in_array($dep, $path)) {
                    $this->dfsAnalyzeDependencies($dep, $this->dependencyGraph[$dep]['direct'], $path);
                }
            }
        }
        
        // 计算依赖权重
        $this->dependencyGraph[$component]['weight'] = 
            count($this->dependencyGraph[$component]['direct']) + 
            count($this->dependencyGraph[$component]['indirect']) * 0.5;
    }
}
