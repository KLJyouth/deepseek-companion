<?php

namespace App\Services\LegalKnowledge;

use Illuminate\Support\Facades\Log;
use App\Models\LegalKnowledgeEntity;
use App\Models\LegalKnowledgeRelation;
use Illuminate\Support\Collection;

/**
 * 法律知识图谱查询服务
 * 
 * 提供基于图算法的法律条款关联分析和推理功能，支持合同风险评估
 * 
 * @version 1.0.0
 * @author 广西港妙科技有限公司
 * @copyright 版权所有 © 广西港妙科技有限公司
 */
class KnowledgeGraphQueryService
{
    /**
     * 关系抽取服务实例
     * 
     * @var RelationExtractionService
     */
    protected $relationService;
    
    /**
     * 构造函数
     * 
     * @param RelationExtractionService $relationService 关系抽取服务实例
     */
    public function __construct(RelationExtractionService $relationService)
    {
        $this->relationService = $relationService;
    }
    
    /**
     * 查找与指定实体相关的所有实体
     * 
     * @param int $entityId 实体ID
     * @param array $options 查询选项
     * @return array 相关实体列表
     */
    public function findRelatedEntities(int $entityId, array $options = []): array
    {
        try {
            // 设置默认选项
            $options = array_merge([
                'max_depth' => 2,           // 最大查询深度
                'relation_types' => [],     // 关系类型过滤
                'entity_types' => [],       // 实体类型过滤
                'include_properties' => true, // 是否包含属性
                'weight_threshold' => 0.0   // 权重阈值
            ], $options);
            
            // 获取起始实体
            $startEntity = LegalKnowledgeEntity::findOrFail($entityId);
            
            // 初始化结果集
            $result = [
                'entities' => [$startEntity->id => $this->formatEntity($startEntity, $options)],
                'relations' => [],
                'metadata' => [
                    'query_depth' => $options['max_depth'],
                    'start_entity' => $startEntity->id,
                    'total_entities' => 1,
                    'total_relations' => 0
                ]
            ];
            
            // 初始化已访问实体集合
            $visited = [$startEntity->id => true];
            
            // 初始化队列
            $queue = [['entity' => $startEntity, 'depth' => 0]];
            
            // 广度优先搜索
            while (!empty($queue)) {
                $current = array_shift($queue);
                $currentEntity = $current['entity'];
                $currentDepth = $current['depth'];
                
                // 达到最大深度则停止继续搜索
                if ($currentDepth >= $options['max_depth']) {
                    continue;
                }
                
                // 获取相关关系
                $relations = $this->getEntityRelations($currentEntity->id, $options);
                
                foreach ($relations as $relation) {
                    // 添加关系到结果集
                    $relationKey = $relation->id;
                    if (!isset($result['relations'][$relationKey])) {
                        $result['relations'][$relationKey] = $this->formatRelation($relation, $options);
                        $result['metadata']['total_relations']++;
                    }
                    
                    // 获取关联实体
                    $relatedEntityId = $relation->source_entity_id == $currentEntity->id 
                        ? $relation->target_entity_id 
                        : $relation->source_entity_id;
                    
                    // 如果实体未访问过，则添加到队列和结果集
                    if (!isset($visited[$relatedEntityId])) {
                        $relatedEntity = LegalKnowledgeEntity::findOrFail($relatedEntityId);
                        
                        // 检查实体类型过滤
                        if (!empty($options['entity_types']) && !in_array($relatedEntity->type, $options['entity_types'])) {
                            continue;
                        }
                        
                        $visited[$relatedEntityId] = true;
                        $result['entities'][$relatedEntityId] = $this->formatEntity($relatedEntity, $options);
                        $result['metadata']['total_entities']++;
                        
                        $queue[] = ['entity' => $relatedEntity, 'depth' => $currentDepth + 1];
                    }
                }
            }
            
            return $result;
        } catch (\Exception $e) {
            Log::error('查找相关实体失败: ' . $e->getMessage(), [
                'entity_id' => $entityId,
                'options' => $options
            ]);
            
            return [
                'error' => '查找相关实体失败: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 获取实体的所有关系
     * 
     * @param int $entityId 实体ID
     * @param array $options 查询选项
     * @return Collection 关系集合
     */
    protected function getEntityRelations(int $entityId, array $options): Collection
    {
        $query = LegalKnowledgeRelation::where(function($q) use ($entityId) {
            $q->where('source_entity_id', $entityId)
              ->orWhere('target_entity_id', $entityId);
        })->active();
        
        // 应用关系类型过滤
        if (!empty($options['relation_types'])) {
            $query->whereIn('relation_type', $options['relation_types']);
        }
        
        // 应用权重阈值过滤
        if ($options['weight_threshold'] > 0) {
            $query->where('weight', '>=', $options['weight_threshold']);
        }
        
        return $query->get();
    }
    
    /**
     * 格式化实体数据
     * 
     * @param LegalKnowledgeEntity $entity 实体对象
     * @param array $options 格式化选项
     * @return array 格式化后的实体数据
     */
    protected function formatEntity(LegalKnowledgeEntity $entity, array $options): array
    {
        $result = [
            'id' => $entity->id,
            'name' => $entity->name,
            'type' => $entity->type,
            'description' => $entity->description
        ];
        
        if ($options['include_properties'] && !empty($entity->properties)) {
            $result['properties'] = $entity->properties;
        }
        
        return $result;
    }
    
    /**
     * 格式化关系数据
     * 
     * @param LegalKnowledgeRelation $relation 关系对象
     * @param array $options 格式化选项
     * @return array 格式化后的关系数据
     */
    protected function formatRelation(LegalKnowledgeRelation $relation, array $options): array
    {
        $result = [
            'id' => $relation->id,
            'source' => $relation->source_entity_id,
            'target' => $relation->target_entity_id,
            'type' => $relation->relation_type,
            'weight' => $relation->weight,
            'description' => $relation->description
        ];
        
        if ($options['include_properties'] && !empty($relation->properties)) {
            $result['properties'] = $relation->properties;
        }
        
        return $result;
    }
    
    /**
     * 查找两个实体之间的最短路径
     * 
     * @param int $sourceId 源实体ID
     * @param int $targetId 目标实体ID
     * @param array $options 查询选项
     * @return array 路径数据
     */
    public function findShortestPath(int $sourceId, int $targetId, array $options = []): array
    {
        try {
            // 设置默认选项
            $options = array_merge([
                'max_depth' => 5,           // 最大查询深度
                'relation_types' => [],     // 关系类型过滤
                'include_properties' => false, // 是否包含属性
                'weight_threshold' => 0.0   // 权重阈值
            ], $options);
            
            // 初始化已访问节点和路径
            $visited = [];
            $queue = [['id' => $sourceId, 'path' => [], 'depth' => 0]];
            
            while (!empty($queue)) {
                $current = array_shift($queue);
                $currentId = $current['id'];
                $currentPath = $current['path'];
                $currentDepth = $current['depth'];
                
                // 如果达到目标节点，返回路径
                if ($currentId == $targetId) {
                    return $this->formatPath($currentPath, $options);
                }
                
                // 如果达到最大深度，跳过
                if ($currentDepth >= $options['max_depth']) {
                    continue;
                }
                
                // 标记为已访问
                $visited[$currentId] = true;
                
                // 获取相关关系
                $relations = $this->getEntityRelations($currentId, $options);
                
                foreach ($relations as $relation) {
                    $nextId = $relation->source_entity_id == $currentId 
                        ? $relation->target_entity_id 
                        : $relation->source_entity_id;
                    
                    // 如果已访问过，跳过
                    if (isset($visited[$nextId])) {
                        continue;
                    }
                    
                    // 添加到路径
                    $newPath = $currentPath;
                    $newPath[] = [
                        'relation' => $relation->id,
                        'source' => $relation->source_entity_id,
                        'target' => $relation->target_entity_id,
                        'type' => $relation->relation_type
                    ];
                    
                    // 添加到队列
                    $queue[] = ['id' => $nextId, 'path' => $newPath, 'depth' => $currentDepth + 1];
                }
            }
            
            // 未找到路径
            return [
                'source' => $sourceId,
                'target' => $targetId,
                'path_exists' => false,
                'path' => [],
                'entities' => [],
                'relations' => []
            ];
        } catch (\Exception $e) {
            Log::error('查找最短路径失败: ' . $e->getMessage(), [
                'source_id' => $sourceId,
                'target_id' => $targetId,
                'options' => $options
            ]);
            
            return [
                'error' => '查找最短路径失败: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 格式化路径数据
     * 
     * @param array $path 路径数据
     * @param array $options 格式化选项
     * @return array 格式化后的路径数据
     */
    protected function formatPath(array $path, array $options): array
    {
        if (empty($path)) {
            return [
                'path_exists' => false,
                'path' => [],
                'entities' => [],
                'relations' => []
            ];
        }
        
        $entities = [];
        $relations = [];
        $entityIds = [];
        
        // 收集路径中的所有实体ID
        foreach ($path as $step) {
            $entityIds[$step['source']] = true;
            $entityIds[$step['target']] = true;
            $relations[$step['relation']] = true;
        }
        
        // 获取实体数据
        $entityModels = LegalKnowledgeEntity::whereIn('id', array_keys($entityIds))->get();
        foreach ($entityModels as $entity) {
            $entities[$entity->id] = $this->formatEntity($entity, $options);
        }
        
        // 获取关系数据
        $relationModels = LegalKnowledgeRelation::whereIn('id', array_keys($relations))->get();
        $formattedRelations = [];
        foreach ($relationModels as $relation) {
            $formattedRelations[$relation->id] = $this->formatRelation($relation, $options);
        }
        
        return [
            'path_exists' => true,
            'path' => $path,
            'entities' => $entities,
            'relations' => $formattedRelations,
            'path_length' => count($path)
        ];
    }
    
    /**
     * 分析合同风险
     * 
     * @param string $contractText 合同文本
     * @param array $options 分析选项
     * @return array 风险分析结果
     */
    public function analyzeContractRisk(string $contractText, array $options = []): array
    {
        try {
            // 设置默认选项
            $options = array_merge([
                'risk_threshold' => 0.7,     // 风险阈值
                'extract_entities' => true,  // 是否提取实体
                'analyze_relations' => true, // 是否分析关系
                'include_context' => true    // 是否包含上下文
            ], $options);
            
            // 提取实体
            $entities = [];
            if ($options['extract_entities']) {
                $extractionResult = $this->relationService->extractEntities($contractText);
                $entities = $extractionResult['entities'] ?? [];
            }
            
            // 初始化风险项
            $riskItems = [];
            
            // 对每个实体进行风险分析
            foreach ($entities as $entity) {
                // 查找知识库中的匹配实体
                $matchedEntities = LegalKnowledgeEntity::where('name', 'like', '%' . $entity['text'] . '%')
                    ->orWhere('description', 'like', '%' . $entity['text'] . '%')
                    ->get();
                
                foreach ($matchedEntities as $matchedEntity) {
                    // 查找冲突关系
                    $conflictRelations = LegalKnowledgeRelation::where(function($query) use ($matchedEntity) {
                        $query->where('source_entity_id', $matchedEntity->id)
                              ->orWhere('target_entity_id', $matchedEntity->id);
                    })
                    ->where('relation_type', LegalKnowledgeRelation::TYPE_CONFLICTS_WITH)
                    ->where('weight', '>=', $options['risk_threshold'])
                    ->get();
                    
                    foreach ($conflictRelations as $relation) {
                        $otherEntityId = $relation->source_entity_id == $matchedEntity->id
                            ? $relation->target_entity_id
                            : $relation->source_entity_id;
                        
                        $otherEntity = LegalKnowledgeEntity::find($otherEntityId);
                        
                        if ($otherEntity) {
                            // 提取上下文
                            $context = null;
                            if ($options['include_context']) {
                                $context = $this->extractContext($contractText, $entity['text']);
                            }
                            
                            // 添加风险项
                            $riskItems[] = [
                                'entity' => $this->formatEntity($matchedEntity, $options),
                                'conflict_entity' => $this->formatEntity($otherEntity, $options),
                                'relation' => $this->formatRelation($relation, $options),
                                'risk_level' => $relation->weight,
                                'context' => $context,
                                'position' => $entity['position'] ?? null
                            ];
                        }
                    }
                }
            }
            
            // 计算总体风险评分
            $overallRiskScore = 0;
            if (!empty($riskItems)) {
                $totalRisk = array_sum(array_column($riskItems, 'risk_level'));
                $overallRiskScore = $totalRisk / count($riskItems);
            }
            
            return [
                'overall_risk_score' => $overallRiskScore,
                'risk_items' => $riskItems,
                'entity_count' => count($entities),
                'risk_item_count' => count($riskItems),
                'analysis_time' => date('Y-m-d H:i:s')
            ];
        } catch (\Exception $e) {
            Log::error('合同风险分析失败: ' . $e->getMessage(), [
                'contract_length' => strlen($contractText),
                'options' => $options
            ]);
            
            return [
                'error' => '合同风险分析失败: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 从文本中提取上下文
     * 
     * @param string $text 完整文本
     * @param string $keyword 关键词
     * @param int $contextLength 上下文长度
     * @return string|null 上下文文本
     */
    protected function extractContext(string $text, string $keyword, int $contextLength = 100): ?string
    {
        $position = mb_stripos($text, $keyword);
        if ($position === false) {
            return null;
        }
        
        $start = max(0, $position - $contextLength / 2);
        $length = min(mb_strlen($text) - $start, $contextLength + mb_strlen($keyword));
        
        $context = mb_substr($text, $start, $length);
        
        // 添加省略号
        if ($start > 0) {
            $context = '...' . $context;
        }
        
        if ($start + $length < mb_strlen($text)) {
            $context .= '...';
        }
        
        return $context;
    }
    
    /**
     * 计算实体中心性
     * 
     * @param int $entityId 实体ID
     * @param string $centralityType 中心性类型 (degree, betweenness, closeness)
     * @param array $options 计算选项
     * @return array 中心性计算结果
     */
    public function calculateEntityCentrality(int $entityId, string $centralityType = 'degree', array $options = []): array
    {
        try {
            // 设置默认选项
            $options = array_merge([
                'max_depth' => 3,           // 最大查询深度
                'relation_types' => [],     // 关系类型过滤
                'weight_threshold' => 0.0   // 权重阈值
            ], $options);
            
            // 获取相关实体和关系
            $graphData = $this->findRelatedEntities($entityId, $options);
            
            if (isset($graphData['error'])) {
                return $graphData;
            }
            
            $centrality = 0;
            $explanation = '';
            
            switch ($centralityType) {
                case 'degree':
                    // 度中心性：与实体直接相连的关系数量
                    $directRelations = array_filter($graphData['relations'], function($relation) use ($entityId) {
                        return $relation['source'] == $entityId || $relation['target'] == $entityId;
                    });
                    
                    $centrality = count($directRelations);
                    $explanation = '度中心性表示实体直接连接的关系数量，该实体有' . $centrality . '个直接关系';
                    break;
                    
                case 'betweenness':
                    // 介数中心性：简化版，计算实体在多少最短路径中出现
                    $pathCount = 0;
                    $totalPaths = 0;
                    
                    $entityIds = array_keys($graphData['entities']);
                    foreach ($entityIds as $source) {
                        if ($source == $entityId) continue;
                        
                        foreach ($entityIds as $target) {
                            if ($target == $entityId || $target == $source) continue;
                            
                            $totalPaths++;
                            $path = $this->findShortestPath($source, $target, $options);
                            
                            if ($path['path_exists']) {
                                foreach ($path['path'] as $step) {
                                    if ($step['source'] == $entityId || $step['target'] == $entityId) {
                                        $pathCount++;
                                        break;
                                    }
                                }
                            }
                        }
                    }
                    
                    $centrality = $totalPaths > 0 ? $pathCount / $totalPaths : 0;
                    $explanation = '介数中心性表示实体在网络中充当桥梁的程度，该实体的介数中心性为' . round($centrality, 4);
                    break;
                    
                case 'closeness':
                    // 接近中心性：到其他所有节点的平均最短路径长度的倒数
                    $totalDistance = 0;
                    $reachableCount = 0;
                    
                    $entityIds = array_keys($graphData['entities']);
                    foreach ($entityIds as $target) {
                        if ($target == $entityId) continue;
                        
                        $path = $this->findShortestPath($entityId, $target, $options);
                        
                        if ($path['path_exists']) {
                            $totalDistance += $path['path_length'];
                            $reachableCount++;
                        }
                    }
                    
                    $centrality = $reachableCount > 0 ? $reachableCount / $totalDistance : 0;
                    $explanation = '接近中心性表示实体到其他实体的平均距离，该实体的接近中心性为' . round($centrality, 4);
                    break;
                    
                default:
                    return [
                        'error' => '不支持的中心性类型: ' . $centralityType
                    ];
            }
            
            return [
                'entity_id' => $entityId,
                'centrality_type' => $centralityType,
                'centrality_value' => $centrality,
                'explanation' => $explanation,
                'graph_size' => [
                    'entities' => count($graphData['entities']),
                    'relations' => count($graphData['relations'])
                ]
            ];
        } catch (\Exception $e) {
            Log::error('计算实体中心性失败: ' . $e->getMessage(), [
                'entity_id' => $entityId,
                'centrality_type' => $centralityType,
                'options' => $options
            ]);
            
            return [
                'error' => '计算实体中心性失败: ' . $e->getMessage()
            ];
        }
    }
}