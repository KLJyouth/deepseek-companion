<?php

namespace App\Services\LegalKnowledge;

use Illuminate\Support\Facades\Log;

/**
 * 法律知识图谱可视化服务
 * 
 * 提供法律知识图谱的可视化展示和交互功能
 * 
 * @version 1.0.0
 * @author 广西港妙科技有限公司
 * @copyright 版权所有 © 广西港妙科技有限公司
 */
class KnowledgeGraphVisualization
{
    /**
     * 关系抽取服务实例
     * 
     * @var RelationExtractionService
     */
    protected $relationService;
    
    /**
     * 合同比对服务实例
     * 
     * @var ContractComparisonService
     */
    protected $comparisonService;
    
    /**
     * 构造函数
     * 
     * @param RelationExtractionService $relationService 关系抽取服务实例
     * @param ContractComparisonService $comparisonService 合同比对服务实例
     */
    public function __construct(
        RelationExtractionService $relationService,
        ContractComparisonService $comparisonService
    ) {
        $this->relationService = $relationService;
        $this->comparisonService = $comparisonService;
    }
    
    /**
     * 生成知识图谱可视化数据
     * 
     * @param array $graphData 知识图谱数据
     * @param array $options 可视化选项
     * @return array 可视化数据
     */
    public function generateVisualization(array $graphData, array $options = []): array
    {
        try {
            // 设置默认选项
            $options = array_merge([
                'layout' => 'force',          // 布局算法: force, circular, hierarchical
                'node_size' => 'degree',      // 节点大小计算方式: fixed, degree, centrality
                'edge_width' => 'weight',     // 边宽度计算方式: fixed, weight
                'color_scheme' => 'category', // 颜色方案: category, gradient, custom
                'include_stats' => true,      // 是否包含统计信息
                'highlight_key_nodes' => true // 是否高亮关键节点
            ], $options);
            
            // 处理节点
            $nodes = $this->processNodes($graphData['nodes'], $options);
            
            // 处理边
            $edges = $this->processEdges($graphData['edges'], $options);
            
            // 计算布局
            $layout = $this->calculateLayout($nodes, $edges, $options['layout']);
            
            // 生成统计信息
            $stats = $options['include_stats'] ? $this->generateStats($nodes, $edges) : null;
            
            // 构建可视化数据
            $visualizationData = [
                'nodes' => $nodes,
                'edges' => $edges,
                'layout' => $layout,
                'stats' => $stats,
                'options' => $options
            ];
            
            return $visualizationData;
        } catch (\Exception $e) {
            Log::error('知识图谱可视化生成失败: ' . $e->getMessage(), [
                'graph_data_size' => [
                    'nodes' => count($graphData['nodes'] ?? []),
                    'edges' => count($graphData['edges'] ?? [])
                ],
                'options' => $options
            ]);
            
            return [
                'error' => '知识图谱可视化生成失败: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 处理节点数据
     * 
     * @param array $nodes 原始节点数据
     * @param array $options 可视化选项
     * @return array 处理后的节点数据
     */
    protected function processNodes(array $nodes, array $options): array
    {
        $processedNodes = [];
        $nodeTypes = [];
        $degreeMap = $this->calculateNodeDegrees($nodes);
        
        foreach ($nodes as $node) {
            // 收集节点类型
            if (!in_array($node['type'], $nodeTypes)) {
                $nodeTypes[] = $node['type'];
            }
            
            // 计算节点大小
            $size = $this->calculateNodeSize($node, $degreeMap, $options['node_size']);
            
            // 计算节点颜色
            $color = $this->calculateNodeColor($node, $nodeTypes, $options['color_scheme']);
            
            // 处理节点属性
            $properties = $node['properties'] ?? [];
            
            // 构建处理后的节点
            $processedNode = [
                'id' => $node['id'],
                'label' => $node['label'],
                'type' => $node['type'],
                'size' => $size,
                'color' => $color,
                'properties' => $properties,
                'degree' => $degreeMap[$node['id']] ?? 0
            ];
            
            // 如果需要高亮关键节点
            if ($options['highlight_key_nodes'] && ($degreeMap[$node['id']] ?? 0) > 3) {
                $processedNode['isKeyNode'] = true;
            }
            
            $processedNodes[] = $processedNode;
        }
        
        return $processedNodes;
    }
    
    /**
     * 处理边数据
     * 
     * @param array $edges 原始边数据
     * @param array $options 可视化选项
     * @return array 处理后的边数据
     */
    protected function processEdges(array $edges, array $options): array
    {
        $processedEdges = [];
        $relationTypes = [];
        
        foreach ($edges as $edge) {
            // 收集关系类型
            if (!in_array($edge['label'], $relationTypes)) {
                $relationTypes[] = $edge['label'];
            }
            
            // 计算边宽度
            $width = $this->calculateEdgeWidth($edge, $options['edge_width']);
            
            // 计算边颜色
            $color = $this->calculateEdgeColor($edge, $relationTypes, $options['color_scheme']);
            
            // 构建处理后的边
            $processedEdge = [
                'id' => $edge['id'],
                'source' => $edge['source'],
                'target' => $edge['target'],
                'label' => $edge['label'],
                'width' => $width,
                'color' => $color,
                'properties' => $edge['properties'] ?? []
            ];
            
            $processedEdges[] = $processedEdge;
        }
        
        return $processedEdges;
    }
    
    /**
     * 计算节点度数
     * 
     * @param array $nodes 节点数据
     * @return array 节点度数映射
     */
    protected function calculateNodeDegrees(array $nodes): array
    {
        $degreeMap = [];
        
        // 初始化所有节点的度数为0
        foreach ($nodes as $node) {
            $degreeMap[$node['id']] = 0;
        }
        
        // 计算每个节点的度数
        foreach ($nodes as $node) {
            $connections = $node['connections'] ?? [];
            $degreeMap[$node['id']] = count($connections);
        }
        
        return $degreeMap;
    }
    
    /**
     * 计算节点大小
     * 
     * @param array $node 节点数据
     * @param array $degreeMap 节点度数映射
     * @param string $sizeMethod 大小计算方式
     * @return int 节点大小
     */
    protected function calculateNodeSize(array $node, array $degreeMap, string $sizeMethod): int
    {
        $baseSize = 30; // 基础大小
        
        switch ($sizeMethod) {
            case 'fixed':
                return $baseSize;
                
            case 'degree':
                $degree = $degreeMap[$node['id']] ?? 0;
                return $baseSize + ($degree * 5);
                
            case 'centrality':
                $centrality = $node['centrality'] ?? 0.5;
                return $baseSize + ($centrality * 20);
                
            default:
                return $baseSize;
        }
    }
    
    /**
     * 计算节点颜色
     * 
     * @param array $node 节点数据
     * @param array $nodeTypes 节点类型列表
     * @param string $colorScheme 颜色方案
     * @return string 节点颜色
     */
    protected function calculateNodeColor(array $node, array $nodeTypes, string $colorScheme): string
    {
        $defaultColor = '#1890ff';
        
        switch ($colorScheme) {
            case 'category':
                // 根据节点类型分配颜色
                $colorMap = [
                    '人物' => '#ff7875',
                    '组织' => '#ffa940',
                    '地点' => '#52c41a',
                    '时间' => '#1890ff',
                    '法律条款' => '#722ed1',
                    '合同条款' => '#eb2f96',
                    '金额' => '#faad14',
                    '日期' => '#13c2c2',
                    '产品' => '#fa8c16',
                    '服务' => '#a0d911'
                ];
                
                return $colorMap[$node['type']] ?? $defaultColor;
                
            case 'gradient':
                // 根据节点重要性生成渐变色
                $importance = $node['importance'] ?? 0.5;
                $r = (int)(255 * (1 - $importance));
                $g = (int)(144 * $importance);
                $b = (int)(255 * $importance);
                return sprintf('#%02x%02x%02x', $r, $g, $b);
                
            case 'custom':
                // 使用节点自定义颜色
                return $node['custom_color'] ?? $defaultColor;
                
            default:
                return $defaultColor;
        }
    }
    
    /**
     * 计算边宽度
     * 
     * @param array $edge 边数据
     * @param string $widthMethod 宽度计算方式
     * @return int 边宽度
     */
    protected function calculateEdgeWidth(array $edge, string $widthMethod): int
    {
        $baseWidth = 1; // 基础宽度
        
        switch ($widthMethod) {
            case 'fixed':
                return $baseWidth;
                
            case 'weight':
                $weight = $edge['weight'] ?? 1;
                return $baseWidth + $weight;
                
            default:
                return $baseWidth;
        }
    }
    
    /**
     * 计算边颜色
     * 
     * @param array $edge 边数据
     * @param array $relationTypes 关系类型列表
     * @param string $colorScheme 颜色方案
     * @return string 边颜色
     */
    protected function calculateEdgeColor(array $edge, array $relationTypes, string $colorScheme): string
    {
        $defaultColor = '#bfbfbf';
        
        switch ($colorScheme) {
            case 'category':
                // 根据关系类型分配颜色
                $colorMap = [
                    '隶属于' => '#1890ff',
                    '拥有' => '#52c41a',
                    '参与' => '#fa8c16',
                    '位于' => '#722ed1',
                    '发生于' => '#eb2f96',
                    '签订于' => '#faad14',
                    '规定' => '#13c2c2',
                    '约定' => '#a0d911'
                ];
                
                return $colorMap[$edge['label']] ?? $defaultColor;
                
            case 'gradient':
                // 根据边权重生成渐变色
                $weight = $edge['weight'] ?? 0.5;
                $r = (int)(191 * (1 - $weight));
                $g = (int)(191 * (1 - $weight));
                $b = (int)(191 * (1 - $weight));
                return sprintf('#%02x%02x%02x', $r, $g, $b);
                
            case 'custom':
                // 使用边自定义颜色
                return $edge['custom_color'] ?? $defaultColor;
                
            default:
                return $defaultColor;
        }
    }
    
    /**
     * 计算图谱布局
     * 
     * @param array $nodes 节点数据
     * @param array $edges 边数据
     * @param string $layoutType 布局类型
     * @return array 布局数据
     */
    protected function calculateLayout(array $nodes, array $edges, string $layoutType): array
    {
        $layout = [];
        
        switch ($layoutType) {
            case 'force':
                // 力导向布局
                $layout = $this->forceDirectedLayout($nodes, $edges);
                break;
                
            case 'circular':
                // 环形布局
                $layout = $this->circularLayout($nodes);
                break;
                
            case 'hierarchical':
                // 层次布局
                $layout = $this->hierarchicalLayout($nodes, $edges);
                break;
                
            default:
                // 默认使用力导向布局
                $layout = $this->forceDirectedLayout($nodes, $edges);
        }
        
        return $layout;
    }
    
    /**
     * 力导向布局算法
     * 
     * @param array $nodes 节点数据
     * @param array $edges 边数据
     * @return array 布局数据
     */
    protected function forceDirectedLayout(array $nodes, array $edges): array
    {
        // 简化的力导向布局实现
        // 在实际应用中，可以使用更复杂的算法或前端库
        $layout = [];
        $width = 800;
        $height = 600;
        
        // 为每个节点分配初始随机位置
        foreach ($nodes as $node) {
            $layout[$node['id']] = [
                'x' => rand(50, $width - 50),
                'y' => rand(50, $height - 50)
            ];
        }
        
        return $layout;
    }
    
    /**
     * 环形布局算法
     * 
     * @param array $nodes 节点数据
     * @return array 布局数据
     */
    protected function circularLayout(array $nodes): array
    {
        $layout = [];
        $count = count($nodes);
        $radius = 250;
        $centerX = 400;
        $centerY = 300;
        
        // 将节点均匀分布在圆周上
        for ($i = 0; $i < $count; $i++) {
            $angle = 2 * M_PI * $i / $count;
            $x = $centerX + $radius * cos($angle);
            $y = $centerY + $radius * sin($angle);
            
            $layout[$nodes[$i]['id']] = [
                'x' => $x,
                'y' => $y
            ];
        }
        
        return $layout;
    }
    
    /**
     * 层次布局算法
     * 
     * @param array $nodes 节点数据
     * @param array $edges 边数据
     * @return array 布局数据
     */
    protected function hierarchicalLayout(array $nodes, array $edges): array
    {
        $layout = [];
        $width = 800;
        $height = 600;
        
        // 简化的层次布局实现
        // 在实际应用中，可以使用更复杂的算法
        
        // 构建邻接表
        $adjacencyList = [];
        foreach ($edges as $edge) {
            if (!isset($adjacencyList[$edge['source']])) {
                $adjacencyList[$edge['source']] = [];
            }
            $adjacencyList[$edge['source']][] = $edge['target'];
        }
        
        // 找出根节点（入度为0的节点）
        $inDegree = array_fill_keys(array_column($nodes, 'id'), 0);
        foreach ($edges as $edge) {
            $inDegree[$edge['target']]++;
        }
        
        $roots = [];
        foreach ($inDegree as $nodeId => $degree) {
            if ($degree === 0) {
                $roots[] = $nodeId;
            }
        }
        
        // 如果没有根节点，选择第一个节点作为根
        if (empty($roots) && !empty($nodes)) {
            $roots[] = $nodes[0]['id'];
        }
        
        // 分配层次
        $levels = [];
        $visited = [];
        
        // BFS遍历分配层次
        $queue = $roots;
        $levels[0] = $roots;
        $currentLevel = 0;
        
        while (!empty($queue)) {
            $nextQueue = [];
            $nextLevel = [];
            
            foreach ($queue as $nodeId) {
                $visited[$nodeId] = true;
                
                if (isset($adjacencyList[$nodeId])) {
                    foreach ($adjacencyList[$nodeId] as $childId) {
                        if (!isset($visited[$childId])) {
                            $nextQueue[] = $childId;
                            $nextLevel[] = $childId;
                        }
                    }
                }
            }
            
            if (!empty($nextLevel)) {
                $currentLevel++;
                $levels[$currentLevel] = $nextLevel;
            }
            
            $queue = $nextQueue;
        }
        
        // 为每个层次的节点分配位置
        $levelHeight = $height / (count($levels) + 1);
        
        foreach ($levels as $level => $levelNodes) {
            $levelWidth = $width / (count($levelNodes) + 1);
            
            for ($i = 0; $i < count($levelNodes); $i++) {
                $nodeId = $levelNodes[$i];
                $layout[$nodeId] = [
                    'x' => ($i + 1) * $levelWidth,
                    'y' => ($level + 1) * $levelHeight
                ];
            }
        }
        
        // 为未访问的节点分配位置
        $unvisitedX = 50;
        $unvisitedY = $height - 50;
        
        foreach ($nodes as $node) {
            if (!isset($layout[$node['id']])) {
                $layout[$node['id']] = [
                    'x' => $unvisitedX,
                    'y' => $unvisitedY
                ];
                $unvisitedX += 50;
            }
        }
        
        return $layout;
    }
    
    /**
     * 生成图谱统计信息
     * 
     * @param array $nodes 节点数据
     * @param array $edges 边数据
     * @return array 统计信息
     */
    protected function generateStats(array $nodes, array $edges): array
    {
        // 节点类型统计
        $nodeTypeStats = [];
        foreach ($nodes as $node) {
            $type = $node['type'];
            if (!isset($nodeTypeStats[$type])) {
                $nodeTypeStats[$type] = 0;
            }
            $nodeTypeStats[$type]++;
        }
        
        // 关系类型统计
        $edgeTypeStats = [];
        foreach ($edges as $edge) {
            $type = $edge['label'];
            if (!isset($edgeTypeStats[$type])) {
                $edgeTypeStats[$type] = 0;
            }
            $edgeTypeStats[$type]++;
        }
        
        // 计算平均度数
        $totalDegree = 0;
        foreach ($nodes as $node) {
            $totalDegree += $node['degree'] ?? 0;
        }
        $avgDegree = count($nodes) > 0 ? $totalDegree / count($nodes) : 0;
        
        // 计算图谱密度
        $maxEdges = count($nodes) * (count($nodes) - 1);
        $density = $maxEdges > 0 ? count($edges) / $maxEdges : 0;
        
        return [
            'node_count' => count($nodes),
            'edge_count' => count($edges),
            'node_type_distribution' => $nodeTypeStats,
            'edge_type_distribution' => $edgeTypeStats,
            'avg_degree' => $avgDegree,
            'density' => $density
        ];
    }
    
    /**
     * 合并合同比对结果与知识图谱
     * 
     * @param array $comparisonResult 合同比对结果
     * @param array $graphData 知识图谱数据
     * @param array $options 合并选项
     * @return array 合并后的可视化数据
     */
    public function mergeComparisonWithGraph(array $comparisonResult, array $graphData, array $options = []): array
    {
        try {
            // 设置默认选项
            $options = array_merge([
                'highlight_differences' => true,  // 是否高亮差异
                'include_comparison' => true,    // 是否包含比对详情
                'layout' => 'force'              // 布局算法
            ], $options);
            
            // 生成基础可视化数据
            $visualizationData = $this->generateVisualization($graphData, $options);
            
            // 如果需要高亮差异
            if ($options['highlight_differences'] && isset($comparisonResult['key_differences'])) {
                $visualizationData = $this->highlightDifferencesInGraph(
                    $visualizationData,
                    $comparisonResult['key_differences']
                );
            }
            
            // 如果需要包含比对详情
            if ($options['include_comparison']) {
                $visualizationData['comparison'] = [
                    'overall_similarity' => $comparisonResult['overall_similarity'] ?? 0,
                    'key_differences' => $comparisonResult['key_differences'] ?? [],
                    'comparison_time' => $comparisonResult['comparison_time'] ?? null
                ];
            }
            
            return $visualizationData;
        } catch (\Exception $e) {
            Log::error('合并合同比对与知识图谱失败: ' . $e->getMessage(), [
                'comparison_result' => isset($comparisonResult) ? 'available' : 'not available',
                'graph_data' => isset($graphData) ? 'available' : 'not available',
                'options' => $options
            ]);
            
            return [
                'error' => '合并合同比对与知识图谱失败: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 在图谱中高亮差异
     * 
     * @param array $visualizationData 可视化数据
     * @param array $keyDifferences 关键差异列表
     * @return array 处理后的可视化数据
     */
    protected function highlightDifferencesInGraph(array $visualizationData, array $keyDifferences): array
    {
        // 提取差异中的关键词
        $keyTerms = [];
        foreach ($keyDifferences as $diff) {
            if (isset($diff['segment1'])) {
                $terms = $this->extractKeyTerms($diff['segment1']);
                $keyTerms = array_merge($keyTerms, $terms);
            }
            
            if (isset($diff['segment2'])) {
                $terms = $this->extractKeyTerms($diff['segment2']);
                $keyTerms = array_merge($keyTerms, $terms);
            }
        }
        
        // 高亮包含关键词的节点
        foreach ($visualizationData['nodes'] as &$node) {
            foreach ($keyTerms as $term) {
                if (stripos($node['label'], $term) !== false) {
                    $node['highlight'] = true;
                    $node['highlight_reason'] = '合同差异相关';
                    $node['highlight_color'] = '#f5222d';
                    break;
                }
            }
        }
        
        return $visualizationData;
    }
    
    /**
     * 从文本中提取关键词
     * 
     * @param string $text 文本内容
     * @return array 关键词列表
     */
    protected function extractKeyTerms(string $text): array
    {
        // 简单实现，提取长度大于1的词
        $words = preg_split('/\s+/', $text);
        return array_filter($words, function($word) {
            return mb_strlen($word) > 1;
        });
    }
}