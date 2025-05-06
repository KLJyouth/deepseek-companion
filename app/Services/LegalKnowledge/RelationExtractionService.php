<?php

namespace App\Services\LegalKnowledge;

use App\Models\LegalKnowledgeEntity;
use App\Models\LegalKnowledgeRelation;
use Illuminate\Support\Facades\Log;

/**
 * 法律关系抽取服务
 * 
 * 提供法律文本中实体间关系识别、提取和存储功能
 * 
 * @version 1.0.0
 * @author 广西港妙科技有限公司
 * @copyright 版权所有 © 广西港妙科技有限公司
 */
class RelationExtractionService
{
    /**
     * 支持的关系类型
     */
    const RELATION_TYPES = [
        'is_a' => '是一种',
        'part_of' => '部分',
        'refers_to' => '引用',
        'conflicts_with' => '冲突',
        'supersedes' => '替代'
    ];
    
    /**
     * NLP处理器实例
     * 
     * @var object
     */
    protected $nlpProcessor;
    
    /**
     * 实体识别服务
     * 
     * @var EntityRecognitionService
     */
    protected $entityService;
    
    /**
     * 构造函数
     * 
     * @param EntityRecognitionService|null $entityService 实体识别服务
     */
    public function __construct(EntityRecognitionService $entityService = null)
    {
        // 初始化NLP处理器
        $this->initNlpProcessor();
        
        // 设置实体识别服务
        $this->entityService = $entityService ?? new EntityRecognitionService();
    }
    
    /**
     * 初始化NLP处理器
     * 
     * @return void
     */
    protected function initNlpProcessor()
    {
        // 根据配置初始化不同的NLP处理器
        $processorType = config('legal_knowledge.nlp_processor', 'local');
        
        if ($processorType === 'local') {
            // 本地处理器初始化
            $this->nlpProcessor = new LocalNlpProcessor();
        } else {
            // 远程API处理器初始化
            $this->nlpProcessor = new RemoteNlpProcessor(
                config('legal_knowledge.api_endpoint'),
                config('legal_knowledge.api_key')
            );
        }
    }
    
    /**
     * 从法律文本中提取实体关系
     * 
     * @param string $text 法律文本内容
     * @param array $entities 已识别的实体列表（可选）
     * @param array $options 提取选项
     * @return array 提取到的关系列表
     */
    public function extractRelations(string $text, array $entities = [], array $options = []): array
    {
        try {
            // 如果未提供实体，先识别实体
            if (empty($entities)) {
                $entities = $this->entityService->recognizeEntities($text, $options);
            }
            
            // 文本预处理
            $processedText = $this->preprocessText($text);
            
            // 使用NLP处理器提取关系
            $relations = $this->nlpProcessor->extractRelations($processedText, $entities, $options);
            
            // 后处理关系结果
            return $this->postprocessRelations($relations);
        } catch (\Exception $e) {
            Log::error('法律关系提取失败: ' . $e->getMessage(), [
                'text_length' => strlen($text),
                'entity_count' => count($entities),
                'options' => $options
            ]);
            
            return [];
        }
    }
    
    /**
     * 文本预处理
     * 
     * @param string $text 原始文本
     * @return string 处理后的文本
     */
    protected function preprocessText(string $text): string
    {
        // 移除多余空白字符
        $text = preg_replace('/\s+/', ' ', $text);
        
        // 分段处理
        $text = preg_replace('/([。！？；])\s*/', "$1\n", $text);
        
        // 移除特殊字符
        $text = preg_replace('/[\x00-\x1F\x7F]/', '', $text);
        
        return trim($text);
    }
    
    /**
     * 关系结果后处理
     * 
     * @param array $relations 原始关系列表
     * @return array 处理后的关系列表
     */
    protected function postprocessRelations(array $relations): array
    {
        $result = [];
        
        foreach ($relations as $relation) {
            // 验证关系类型
            if (!isset(self::RELATION_TYPES[$relation['relation_type']])) {
                continue;
            }
            
            // 验证源实体和目标实体
            if (empty($relation['source_entity']) || empty($relation['target_entity'])) {
                continue;
            }
            
            // 关系去重和合并
            $key = $relation['source_entity'] . '_' . $relation['relation_type'] . '_' . $relation['target_entity'];
            
            if (!isset($result[$key])) {
                $result[$key] = $relation;
            } else {
                // 合并属性
                if (isset($relation['properties']) && is_array($relation['properties'])) {
                    $result[$key]['properties'] = array_merge(
                        $result[$key]['properties'] ?? [],
                        $relation['properties']
                    );
                }
                
                // 更新置信度和权重
                if (isset($relation['confidence']) && 
                    (!isset($result[$key]['confidence']) || $relation['confidence'] > $result[$key]['confidence'])) {
                    $result[$key]['confidence'] = $relation['confidence'];
                    
                    // 根据置信度更新权重
                    if (isset($relation['confidence'])) {
                        $result[$key]['weight'] = max(0.1, min(1.0, $relation['confidence']));
                    }
                }
            }
        }
        
        return array_values($result);
    }
    
    /**
     * 保存提取到的关系
     * 
     * @param array $relations 关系列表
     * @param int|null $tenantId 租户ID
     * @param array $options 保存选项
     * @return array 保存结果
     */
    public function saveRelations(array $relations, ?int $tenantId = null, array $options = []): array
    {
        $savedRelations = [];
        $errors = [];
        
        foreach ($relations as $relation) {
            try {
                // 查找源实体和目标实体
                $sourceEntity = $this->findOrCreateEntity($relation['source_entity'], $relation['source_type'] ?? null, $tenantId);
                $targetEntity = $this->findOrCreateEntity($relation['target_entity'], $relation['target_type'] ?? null, $tenantId);
                
                if (!$sourceEntity || !$targetEntity) {
                    throw new \Exception('源实体或目标实体不存在');
                }
                
                // 准备关系数据
                $relationData = [
                    'tenant_id' => $tenantId,
                    'source_entity_id' => $sourceEntity->id,
                    'target_entity_id' => $targetEntity->id,
                    'relation_type' => $relation['relation_type'],
                    'description' => $relation['description'] ?? null,
                    'properties' => isset($relation['properties']) ? json_encode($relation['properties'], JSON_UNESCAPED_UNICODE) : null,
                    'weight' => $relation['weight'] ?? 1.0,
                    'source' => $relation['source'] ?? $options['source'] ?? null,
                    'status' => $relation['status'] ?? 1
                ];
                
                // 查找或创建关系
                $savedRelation = LegalKnowledgeRelation::updateOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'source_entity_id' => $sourceEntity->id,
                        'target_entity_id' => $targetEntity->id,
                        'relation_type' => $relation['relation_type']
                    ],
                    $relationData
                );
                
                $savedRelations[] = $savedRelation;
            } catch (\Exception $e) {
                Log::error('保存法律关系失败: ' . $e->getMessage(), [
                    'relation' => $relation
                ]);
                
                $errors[] = [
                    'source' => $relation['source_entity'] ?? null,
                    'target' => $relation['target_entity'] ?? null,
                    'relation' => $relation['relation_type'] ?? null,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return [
            'saved' => $savedRelations,
            'errors' => $errors
        ];
    }
    
    /**
     * 查找或创建实体
     * 
     * @param string $entityName 实体名称
     * @param string|null $entityType 实体类型
     * @param int|null $tenantId 租户ID
     * @return LegalKnowledgeEntity|null 实体对象
     */
    protected function findOrCreateEntity(string $entityName, ?string $entityType = null, ?int $tenantId = null): ?LegalKnowledgeEntity
    {
        // 查找实体
        $query = LegalKnowledgeEntity::where('name', $entityName);
        
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }
        
        if ($entityType !== null) {
            $query->where('type', $entityType);
        }
        
        $entity = $query->first();
        
        // 如果实体不存在且提供了类型，则创建
        if (!$entity && $entityType !== null) {
            $entity = LegalKnowledgeEntity::create([
                'tenant_id' => $tenantId,
                'name' => $entityName,
                'type' => $entityType,
                'status' => 1
            ]);
        }
        
        return $entity;
    }
    
    /**
     * 批量处理法律文本并提取关系
     * 
     * @param array $texts 法律文本列表
     * @param int|null $tenantId 租户ID
     * @param array $options 处理选项
     * @return array 处理结果
     */
    public function batchProcess(array $texts, ?int $tenantId = null, array $options = []): array
    {
        $results = [];
        $allRelations = [];
        
        foreach ($texts as $index => $text) {
            // 识别实体
            $entities = $this->entityService->recognizeEntities($text, $options);
            
            // 提取关系
            $relations = $this->extractRelations($text, $entities, $options);
            
            $results[$index] = [
                'text_id' => $index,
                'entity_count' => count($entities),
                'relation_count' => count($relations),
                'relations' => $relations
            ];
            
            $allRelations = array_merge($allRelations, $relations);
        }
        
        // 保存所有关系
        if (!empty($allRelations) && ($options['save'] ?? true)) {
            $saveResult = $this->saveRelations($allRelations, $tenantId, $options);
            $results['save_result'] = $saveResult;
        }
        
        return $results;
    }
    
    /**
     * 构建知识图谱
     * 
     * @param string $text 法律文本内容
     * @param int|null $tenantId 租户ID
     * @param array $options 处理选项
     * @return array 知识图谱数据
     */
    public function buildKnowledgeGraph(string $text, ?int $tenantId = null, array $options = []): array
    {
        // 识别实体
        $entities = $this->entityService->recognizeEntities($text, $options);
        $entityResult = $this->entityService->saveEntities($entities, $tenantId, $options);
        
        // 提取关系
        $relations = $this->extractRelations($text, $entities, $options);
        $relationResult = $this->saveRelations($relations, $tenantId, $options);
        
        // 构建图谱数据
        $graphData = [
            'nodes' => [],
            'edges' => []
        ];
        
        // 添加节点
        foreach ($entityResult['saved'] as $entity) {
            $graphData['nodes'][] = [
                'id' => $entity->id,
                'label' => $entity->name,
                'type' => $entity->type,
                'properties' => json_decode($entity->properties, true) ?? []
            ];
        }
        
        // 添加边
        foreach ($relationResult['saved'] as $relation) {
            $graphData['edges'][] = [
                'id' => $relation->id,
                'source' => $relation->source_entity_id,
                'target' => $relation->target_entity_id,
                'label' => self::RELATION_TYPES[$relation->relation_type] ?? $relation->relation_type,
                'type' => $relation->relation_type,
                'weight' => $relation->weight,
                'properties' => json_decode($relation->properties, true) ?? []
            ];
        }
        
        return [
            'graph' => $graphData,
            'stats' => [
                'entity_count' => count($graphData['nodes']),
                'relation_count' => count($graphData['edges']),
                'entity_errors' => count($entityResult['errors']),
                'relation_errors' => count($relationResult['errors'])
            ],
            'errors' => [
                'entities' => $entityResult['errors'],
                'relations' => $relationResult['errors']
            ]
        ];
    }
}