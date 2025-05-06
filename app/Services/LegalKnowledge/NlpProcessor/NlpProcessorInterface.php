<?php

namespace App\Services\LegalKnowledge\NlpProcessor;

/**
 * NLP处理器接口
 * 
 * 定义法律文本NLP处理的标准接口
 * 
 * @version 1.0.0
 * @author 广西港妙科技有限公司
 * @copyright 版权所有 © 广西港妙科技有限公司
 */
interface NlpProcessorInterface
{
    /**
     * 从文本中识别实体
     * 
     * @param string $text 待处理的文本
     * @param array $options 处理选项
     * @return array 识别到的实体列表
     */
    public function recognizeEntities(string $text, array $options = []): array;
    
    /**
     * 从文本中提取实体间的关系
     * 
     * @param string $text 待处理的文本
     * @param array $entities 已识别的实体列表
     * @param array $options 处理选项
     * @return array 提取到的关系列表
     */
    public function extractRelations(string $text, array $entities, array $options = []): array;
    
    /**
     * 对文本进行语义分析
     * 
     * @param string $text 待分析的文本
     * @param array $options 分析选项
     * @return array 分析结果
     */
    public function semanticAnalysis(string $text, array $options = []): array;
    
    /**
     * 计算两段文本的相似度
     * 
     * @param string $text1 第一段文本
     * @param string $text2 第二段文本
     * @param array $options 计算选项
     * @return float 相似度分数(0-1)
     */
    public function calculateSimilarity(string $text1, string $text2, array $options = []): float;
    
    /**
     * 获取处理器类型
     * 
     * @return string 处理器类型标识
     */
    public function getProcessorType(): string;
}