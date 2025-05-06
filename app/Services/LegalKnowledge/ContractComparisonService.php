<?php

namespace App\Services\LegalKnowledge;

use Illuminate\Support\Facades\Log;
use App\Services\LegalKnowledge\NlpProcessor\NlpProcessorInterface;

/**
 * 合同智能比对服务
 * 
 * 提供合同文本智能比对、差异分析和可视化展示功能
 * 
 * @version 1.0.0
 * @author 广西港妙科技有限公司
 * @copyright 版权所有 © 广西港妙科技有限公司
 */
class ContractComparisonService
{
    /**
     * NLP处理器实例
     * 
     * @var NlpProcessorInterface
     */
    protected $nlpProcessor;
    
    /**
     * 构造函数
     * 
     * @param NlpProcessorInterface $nlpProcessor NLP处理器实例
     */
    public function __construct(NlpProcessorInterface $nlpProcessor)
    {
        $this->nlpProcessor = $nlpProcessor;
    }
    
    /**
     * 比较两份合同文本
     * 
     * @param string $contract1 第一份合同文本
     * @param string $contract2 第二份合同文本
     * @param array $options 比对选项
     * @return array 比对结果
     */
    public function compareContracts(string $contract1, string $contract2, array $options = []): array
    {
        try {
            // 设置默认选项
            $options = array_merge([
                'similarity_method' => 'cosine',  // 相似度计算方法
                'segment_by' => 'paragraph',     // 分段方式：paragraph, clause, sentence
                'highlight_diff' => true,        // 是否高亮差异
                'min_similarity_threshold' => 0.7 // 最小相似度阈值
            ], $options);
            
            // 分段处理合同文本
            $segments1 = $this->segmentText($contract1, $options['segment_by']);
            $segments2 = $this->segmentText($contract2, $options['segment_by']);
            
            // 计算整体相似度
            $overallSimilarity = $this->nlpProcessor->calculateSimilarity(
                $contract1, 
                $contract2, 
                ['method' => $options['similarity_method']]
            );
            
            // 分段比对
            $segmentComparisons = $this->compareSegments($segments1, $segments2, $options);
            
            // 识别关键差异
            $keyDifferences = $this->identifyKeyDifferences($segmentComparisons, $options);
            
            // 构建比对报告
            return [
                'overall_similarity' => $overallSimilarity,
                'segment_comparisons' => $segmentComparisons,
                'key_differences' => $keyDifferences,
                'comparison_time' => date('Y-m-d H:i:s'),
                'comparison_options' => $options
            ];
        } catch (\Exception $e) {
            Log::error('合同比对失败: ' . $e->getMessage(), [
                'contract1_length' => strlen($contract1),
                'contract2_length' => strlen($contract2),
                'options' => $options
            ]);
            
            return [
                'error' => '合同比对处理失败: ' . $e->getMessage(),
                'comparison_time' => date('Y-m-d H:i:s')
            ];
        }
    }
    
    /**
     * 根据指定方式分段文本
     * 
     * @param string $text 待分段文本
     * @param string $segmentBy 分段方式
     * @return array 分段结果
     */
    protected function segmentText(string $text, string $segmentBy): array
    {
        $segments = [];
        
        switch ($segmentBy) {
            case 'paragraph':
                // 按段落分割
                $segments = array_filter(preg_split('/\n{2,}/', $text));
                break;
                
            case 'clause':
                // 按条款分割（假设条款以"第X条"或"第X章