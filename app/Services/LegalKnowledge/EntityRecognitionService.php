<?php

namespace App\Services\LegalKnowledge;

use App\Models\LegalKnowledgeEntity;
use Illuminate\Support\Facades\Log;

/**
 * 法律实体识别服务
 * 
 * 提供法律文本中实体识别、提取和存储功能
 * 
 * @version 1.0.0
 * @author 广西港妙科技有限公司
 * @copyright 版权所有 © 广西港妙科技有限公司
 */
class EntityRecognitionService
{
    /**
     * 支持的实体类型
     */
    const ENTITY_TYPES = [
        'concept' => '概念',
        'term' => '术语',
        'clause' => '条款',
        'regulation' => '法规'
    ];
    
    /**
     * NLP处理器实例
     * 
     * @var object
     */
    protected $nlpProcessor;
    
    /**
     * 构造函数
     */
    public function __construct()
    {
        // 初始化NLP处理器
        $this->initNlpProcessor();
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
     * 从法律文本中识别实体
     * 
     * @param string $text 法律文本内容
     * @param array $options 识别选项
     * @return array 识别到的实体列表
     */
    public function recognizeEntities(string $text, array $options = []): array
    {
        try {
            // 文本预处理
            $processedText = $this->preprocessText($text);
            
            // 使用NLP处理器识别实体
            $entities = $this->nlpProcessor->extractEntities($processedText, $options);
            
            // 后处理实体结果
            return $this->postprocessEntities($entities);
        } catch (\Exception $e) {
            Log::error('法律实体识别失败: ' . $e->getMessage(), [
                'text_length' => strlen($text),
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
     * 实体结果后处理
     * 
     * @param array $entities 原始实体列表
     * @return array 处理后的实体列表
     */
    protected function postprocessEntities(array $entities): array
    {
        $result = [];
        
        foreach ($entities as $entity) {
            // 验证实体类型
            if (!isset(self::ENTITY_TYPES[$entity['type']])) {
                continue;
            }
            
            // 实体去重和合并
            $key = $entity['name'] . '_' . $entity['type'];
            
            if (!isset($result[$key])) {
                $result[$key] = $entity;
            } else {
                // 合并属性
                if (isset($entity['properties']) && is_array($entity['properties'])) {
                    $result[$key]['properties'] = array_merge(
                        $result[$key]['properties'] ?? [],
                        $entity['properties']
                    );
                }
                
                // 更新置信度
                if (isset($entity['confidence']) && 
                    (!isset($result[$key]['confidence']) || $entity['confidence'] > $result[$key]['confidence'])) {
                    $result[$key]['confidence'] = $entity['confidence'];
                }
            }
        }
        
        return array_values($result);
    }
    
    /**
     * 保存识别到的实体
     * 
     * @param array $entities 实体列表
     * @param int|null $tenantId 租户ID
     * @param array $options 保存选项
     * @return array 保存结果
     */
    public function saveEntities(array $entities, ?int $tenantId = null, array $options = []): array
    {
        $savedEntities = [];
        $errors = [];
        
        foreach ($entities as $entity) {
            try {
                // 准备实体数据
                $entityData = [
                    'tenant_id' => $tenantId,
                    'name' => $entity['name'],
                    'type' => $entity['type'],
                    'description' => $entity['description'] ?? null,
                    'properties' => isset($entity['properties']) ? json_encode($entity['properties'], JSON_UNESCAPED_UNICODE) : null,
                    'source' => $entity['source'] ?? $options['source'] ?? null,
                    'category_id' => $entity['category_id'] ?? $options['category_id'] ?? null,
                    'status' => $entity['status'] ?? 1
                ];
                
                // 查找或创建实体
                $savedEntity = LegalKnowledgeEntity::updateOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'name' => $entity['name'],
                        'type' => $entity['type']
                    ],
                    $entityData
                );
                
                $savedEntities[] = $savedEntity;
            } catch (\Exception $e) {
                Log::error('保存法律实体失败: ' . $e->getMessage(), [
                    'entity' => $entity
                ]);
                
                $errors[] = [
                    'entity' => $entity['name'],
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return [
            'saved' => $savedEntities,
            'errors' => $errors
        ];
    }
    
    /**
     * 批量处理法律文本并提取实体
     * 
     * @param array $texts 法律文本列表
     * @param int|null $tenantId 租户ID
     * @param array $options 处理选项
     * @return array 处理结果
     */
    public function batchProcess(array $texts, ?int $tenantId = null, array $options = []): array
    {
        $results = [];
        $allEntities = [];
        
        foreach ($texts as $index => $text) {
            // 识别实体
            $entities = $this->recognizeEntities($text, $options);
            
            $results[$index] = [
                'text_id' => $index,
                'entity_count' => count($entities),
                'entities' => $entities
            ];
            
            $allEntities = array_merge($allEntities, $entities);
        }
        
        // 保存所有实体
        if (!empty($allEntities) && ($options['save'] ?? true)) {
            $saveResult = $this->saveEntities($allEntities, $tenantId, $options);
            $results['save_result'] = $saveResult;
        }
        
        return $results;
    }
}