<?php

namespace App\Services\LegalKnowledge\NlpProcessor;

use Illuminate\Support\Facades\Log;

/**
 * 本地NLP处理器实现
 * 
 * 提供本地环境下的法律文本NLP处理功能
 * 
 * @version 1.0.0
 * @author 广西港妙科技有限公司
 * @copyright 版权所有 © 广西港妙科技有限公司
 */
class LocalNlpProcessor implements NlpProcessorInterface
{
    /**
     * 处理器类型标识
     * 
     * @var string
     */
    protected $processorType = 'local';
    
    /**
     * 实体类型映射
     * 
     * @var array
     */
    protected $entityTypes = [
        'concept' => '概念',
        'term' => '术语',
        'clause' => '条款',
        'regulation' => '法规'
    ];
    
    /**
     * 关系类型映射
     * 
     * @var array
     */
    protected $relationTypes = [
        'defines' => '定义',
        'includes' => '包含',
        'references' => '引用',
        'contradicts' => '矛盾',
        'extends' => '扩展'
    ];
    
    /**
     * 构造函数
     */
    public function __construct()
    {
        // 初始化本地NLP处理资源
        $this->initResources();
    }
    
    /**
     * 初始化本地NLP处理资源
     * 
     * @return void
     */
    protected function initResources(): void
    {
        // 加载本地模型和词典资源
        // 实际项目中可根据需要实现具体的资源加载逻辑
    }
    
    /**
     * 从文本中识别实体
     * 
     * {@inheritdoc}
     */
    public function recognizeEntities(string $text, array $options = []): array
    {
        try {
            // 本地实体识别逻辑实现
            // 这里是示例实现，实际项目中需要根据具体的NLP技术栈进行开发
            $entities = [];
            
            // 设置默认选项
            $options = array_merge([
                'min_confidence' => 0.6,
                'entity_types' => array_keys($this->entityTypes),
                'max_entities' => 100
            ], $options);
            
            // 实体识别处理逻辑
            // 这里可以集成第三方库或自定义算法
            
            // 模拟识别结果
            if (!empty($text)) {
                // 基于规则的简单实体提取示例
                $entities = $this->extractEntitiesByRules($text, $options);
            }
            
            return $entities;
        } catch (\Exception $e) {
            Log::error('本地实体识别失败: ' . $e->getMessage(), [
                'text_length' => strlen($text),
                'options' => $options
            ]);
            
            return [];
        }
    }
    
    /**
     * 基于规则的实体提取
     * 
     * @param string $text 待处理文本
     * @param array $options 处理选项
     * @return array 提取的实体
     */
    protected function extractEntitiesByRules(string $text, array $options): array
    {
        $entities = [];
        $entityId = 0;
        
        // 简单的基于规则的实体提取示例
        // 在实际项目中，这里应该使用更复杂的NLP算法
        
        // 提取可能的法规引用
        if (in_array('regulation', $options['entity_types'])) {
            preg_match_all('/《([^》]+)》/', $text, $matches);
            foreach ($matches[1] as $match) {
                $entities[] = [
                    'id' => 'entity_' . (++$entityId),
                    'text' => $match,
                    'type' => 'regulation',
                    'start_pos' => strpos($text, $match) - 1, // 减1是因为《符号
                    'end_pos' => strpos($text, $match) + strlen($match),
                    'confidence' => 0.85
                ];
            }
        }
        
        // 提取可能的条款
        if (in_array('clause', $options['entity_types'])) {
            preg_match_all('/第([\d一二三四五六七八九十百千]+)条/', $text, $matches);
            foreach ($matches[0] as $match) {
                $entities[] = [
                    'id' => 'entity_' . (++$entityId),
                    'text' => $match,
                    'type' => 'clause',
                    'start_pos' => strpos($text, $match),
                    'end_pos' => strpos($text, $match) + strlen($match),
                    'confidence' => 0.9
                ];
            }
        }
        
        // 限制返回的实体数量
        if (count($entities) > $options['max_entities']) {
            $entities = array_slice($entities, 0, $options['max_entities']);
        }
        
        return $entities;
    }
    
    /**
     * 从文本中提取实体间的关系
     * 
     * {@inheritdoc}
     */
    public function extractRelations(string $text, array $entities, array $options = []): array
    {
        try {
            // 本地关系提取逻辑实现
            $relations = [];
            $relationId = 0;
            
            // 设置默认选项
            $options = array_merge([
                'min_confidence' => 0.6,
                'relation_types' => array_keys($this->relationTypes),
                'max_relations' => 100
            ], $options);
            
            // 基于规则的简单关系提取
            // 在实际项目中，这里应该使用更复杂的NLP算法
            
            // 分析实体间的共现关系
            $entityCount = count($entities);
            for ($i = 0; $i < $entityCount; $i++) {
                for ($j = $i + 1; $j < $entityCount; $j++) {
                    $sourceEntity = $entities[$i];
                    $targetEntity = $entities[$j];
                    
                    // 检查两个实体是否在文本中相近
                    $distance = abs($sourceEntity['end_pos'] - $targetEntity['start_pos']);
                    if ($distance < 50) { // 如果两个实体在文本中的距离小于50个字符
                        // 确定关系类型（这里使用简单规则，实际应用中需要更复杂的逻辑）
                        $relationType = $this->determineRelationType($text, $sourceEntity, $targetEntity);
                        
                        if ($relationType && in_array($relationType, $options['relation_types'])) {
                            $relations[] = [
                                'id' => 'relation_' . (++$relationId),
                                'source_id' => $sourceEntity['id'],
                                'target_id' => $targetEntity['id'],
                                'type' => $relationType,
                                'confidence' => 0.7,
                                'description' => $this->generateRelationDescription($relationType, $sourceEntity, $targetEntity)
                            ];
                        }
                    }
                }
            }
            
            // 限制返回的关系数量
            if (count($relations) > $options['max_relations']) {
                $relations = array_slice($relations, 0, $options['max_relations']);
            }
            
            return $relations;
        } catch (\Exception $e) {
            Log::error('本地关系提取失败: ' . $e->getMessage(), [
                'text_length' => strlen($text),
                'entity_count' => count($entities),
                'options' => $options
            ]);
            
            return [];
        }
    }
    
    /**
     * 确定两个实体之间的关系类型
     * 
     * @param string $text 原文本
     * @param array $sourceEntity 源实体
     * @param array $targetEntity 目标实体
     * @return string|null 关系类型或null
     */
    protected function determineRelationType(string $text, array $sourceEntity, array $targetEntity): ?string
    {
        // 提取两个实体之间的文本片段
        $startPos = min($sourceEntity['end_pos'], $targetEntity['end_pos']);
        $endPos = max($sourceEntity['start_pos'], $targetEntity['start_pos']);
        
        if ($startPos < $endPos) {
            $betweenText = substr($text, $startPos, $endPos - $startPos);
            
            // 基于关键词的简单关系判断
            if (preg_match('/(定义|是指|指|称为|即)/', $betweenText)) {
                return 'defines';
            } elseif (preg_match('/(包括|包含|涵盖|囊括)/', $betweenText)) {
                return 'includes';
            } elseif (preg_match('/(参照|参考|引用|根据|依据)/', $betweenText)) {
                return 'references';
            } elseif (preg_match('/(矛盾|冲突|不一致|相反)/', $betweenText)) {
                return 'contradicts';
            } elseif (preg_match('/(扩展|延伸|补充|增加)/', $betweenText)) {
                return 'extends';
            }
        }
        
        // 默认返回null表示无法确定关系
        return null;
    }
    
    /**
     * 生成关系描述
     * 
     * @param string $relationType 关系类型
     * @param array $sourceEntity 源实体
     * @param array $targetEntity 目标实体
     * @return string 关系描述
     */
    protected function generateRelationDescription(string $relationType, array $sourceEntity, array $targetEntity): string
    {
        $relationName = $this->relationTypes[$relationType] ?? $relationType;
        return "{$sourceEntity['text']}与{$targetEntity['text']}之间存在{$relationName}关系";
    }
    
    /**
     * 对文本进行语义分析
     * 
     * {@inheritdoc}
     */
    public function semanticAnalysis(string $text, array $options = []): array
    {
        try {
            // 设置默认选项
            $options = array_merge([
                'analyze_sentiment' => true,
                'analyze_keywords' => true,
                'analyze_categories' => true
            ], $options);
            
            $result = [
                'text_length' => strlen($text),
                'language' => 'zh-CN',
                'processing_time' => microtime(true)
            ];
            
            // 情感分析
            if ($options['analyze_sentiment']) {
                $result['sentiment'] = $this->analyzeSentiment($text);
            }
            
            // 关键词提取
            if ($options['analyze_keywords']) {
                $result['keywords'] = $this->extractKeywords($text);
            }
            
            // 文本分类
            if ($options['analyze_categories']) {
                $result['categories'] = $this->categorizeText($text);
            }
            
            $result['processing_time'] = microtime(true) - $result['processing_time'];
            
            return $result;
        } catch (\Exception $e) {
            Log::error('本地语义分析失败: ' . $e->getMessage(), [
                'text_length' => strlen($text),
                'options' => $options
            ]);
            
            return [
                'error' => $e->getMessage(),
                'text_length' => strlen($text)
            ];
        }
    }
    
    /**
     * 分析文本情感
     * 
     * @param string $text 待分析文本
     * @return array 情感分析结果
     */
    protected function analyzeSentiment(string $text): array
    {
        // 简单的基于关键词的情感分析
        // 在实际项目中，这里应该使用更复杂的NLP算法
        
        $positiveWords = ['同意', '批准', '认可', '有效', '合法', '有利', '支持'];
        $negativeWords = ['拒绝', '否决', '无效', '违法', '反对', '不利', '禁止'];
        
        $positiveScore = 0;
        $negativeScore = 0;
        
        foreach ($positiveWords as $word) {
            $positiveScore += substr_count($text, $word);
        }
        
        foreach ($negativeWords as $word) {
            $negativeScore += substr_count($text, $word);
        }
        
        $totalScore = $positiveScore + $negativeScore;
        if ($totalScore > 0) {
            $positiveRatio = $positiveScore / $totalScore;
            $negativeRatio = $negativeScore / $totalScore;
        } else {
            $positiveRatio = 0.5;
            $negativeRatio = 0.5;
        }
        
        return [
            'positive' => $positiveRatio,
            'negative' => $negativeRatio,
            'neutral' => 1 - ($positiveRatio + $negativeRatio),
            'dominant' => ($positiveRatio > $negativeRatio) ? 'positive' : (($positiveRatio < $negativeRatio) ? 'negative' : 'neutral')
        ];
    }
    
    /**
     * 提取文本关键词
     * 
     * @param string $text 待分析文本
     * @return array 关键词列表
     */
    protected function extractKeywords(string $text): array
    {
        // 简单的TF统计实现
        // 在实际项目中，这里应该使用更复杂的NLP算法，如TF-IDF或TextRank
        
        // 移除标点符号和特殊字符
        $cleanText = preg_replace('/[\p{P}\s]+/u', ' ', $text);
        
        // 分词（这里使用简单的按空格分词，实际中应使用专业分词工具）
        $words = explode(' ', $cleanText);
        $words = array_filter($words, function($word) {
            return mb_strlen($word, 'UTF-8') >= 2; // 只保留长度大于等于2的词
        });
        
        // 统计词频
        $wordFreq = array_count_values($words);
        
        // 按词频排序
        arsort($wordFreq);
        
        // 取前10个词作为关键词
        $keywords = [];
        $i = 0;
        foreach ($wordFreq as $word => $freq) {
            if ($i >= 10) break;
            
            $keywords[] = [
                'word' => $word,
                'frequency' => $freq,
                'score' => $freq / count($words)
            ];
            
            $i++;
        }
        
        return $keywords;
    }
    
    /**
     * 对文本进行分类
     * 
     * @param string $text 待分析文本
     * @return array 分类结果
     */
    protected function categorizeText(string $text): array
    {
        // 简单的基于关键词的分类实现
        // 在实际项目中，这里应该使用更复杂的NLP算法
        
        $categories = [
            'contract' => ['合同', '协议', '甲方', '乙方', '丙方', '签约', '履行', '违约'],
            'litigation' => ['诉讼', '原告', '被告', '法院', '判决', '裁定', '上诉', '申诉'],
            'regulation' => ['法规', '条例', '办法', '规定', '实施细则', '通知', '决定'],
            'intellectual_property' => ['知识产权', '专利', '商标', '著作权', '版权', '许可', '侵权']
        ];
        
        $scores = [];
        foreach ($categories as $category => $keywords) {
            $score = 0;
            foreach ($keywords as $keyword) {
                $count = substr_count($text, $keyword);
                $score += $count;
            }
            $scores[$category] = $score;
        }
        
        // 归一化分数
        $totalScore = array_sum($scores);
        $normalizedScores = [];
        
        if ($totalScore > 0) {
            foreach ($scores as $category => $score) {
                $normalizedScores[$category] = $score / $totalScore;
            }
        } else {
            // 如果没有匹配任何关键词，则平均分配概率
            $equalProb = 1 / count($categories);
            foreach ($categories as $category => $keywords) {
                $normalizedScores[$category] = $equalProb;
            }
        }
        
        // 按概率排序
        arsort($normalizedScores);
        
        // 构建返回结果
        $result = [];
        foreach ($normalizedScores as $category => $probability) {
            $result[] = [
                'category' => $category,
                'probability' => $probability
            ];
        }
        
        return $result;
    }
    
    /**
     * 计算两段文本的相似度
     * 
     * {@inheritdoc}
     */
    public function calculateSimilarity(string $text1, string $text2, array $options = []): float
    {
        try {
            // 设置默认选项
            $options = array_merge([
                'method' => 'cosine', // 相似度计算方法：cosine, jaccard, levenshtein
                'normalize' => true,  // 是否对文本进行归一化处理
                'case_sensitive' => false // 是否区分大小写
            ], $options);
            
            // 文本预处理
            if ($options['normalize']) {
                $text1 = $this->normalizeText($text1, $options['case_sensitive']);
                $text2 = $this->normalizeText($text2, $options['case_sensitive']);
            }
            
            // 根据选择的方法计算相似度
            switch ($options['method']) {
                case 'jaccard':
                    return $this->jaccardSimilarity($text1, $text2);
                    
                case 'levenshtein':
                    return $this->levenshteinSimilarity($text1, $text2);
                    
                case 'cosine':
                default:
                    return $this->cosineSimilarity($text1, $text2);
            }
        } catch (\Exception $e) {
            Log::error('文本相似度计算失败: ' . $e->getMessage(), [
                'text1_length' => strlen($text1),
                'text2_length' => strlen($text2),
                'options' => $options
            ]);
            
            return 0.0;
        }
    }
    
    /**
     * 文本归一化处理
     * 
     * @param string $text 原始文本
     * @param bool $caseSensitive 是否区分大小写
     * @return string 处理后的文本
     */
    protected function normalizeText(string $text, bool $caseSensitive = false): string
    {
        // 移除标点符号和特殊字符
        $text = preg_replace('/[\p{P}]+/u', '', $text);
        
        // 移除多余空白
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        
        // 转换大小写
        if (!$caseSensitive) {
            $text = mb_strtolower($text, 'UTF-8');
        }
        
        return $text;
    }
    
    /**
     * 计算Jaccard相似度
     * 
     * @param string $text1 第一段文本
     * @param string $text2 第二段文本
     * @return float 相似度分数(0-1)
     */
    protected function jaccardSimilarity(string $text1, string $text2): float
    {
        // 将文本分割成字符数组
        $chars1 = preg_split('//u', $text1, -1, PREG_SPLIT_NO_EMPTY);
        $chars2 = preg_split('//u', $text2, -1, PREG_SPLIT_NO_EMPTY);
        
        // 计算交集和并集
        $intersection = array_intersect($chars1, $chars2);
        $union = array_unique(array_merge($chars1, $chars2));
        
        // 计算Jaccard系数
        if (count($union) > 0) {
            return count($intersection) / count($union);
        }
        
        return 0.0;
    }
    
    /**
     * 基于Levenshtein距离计算相似度
     * 
     * @param string $text1 第一段文本
     * @param string $text2 第二段文本
     * @return float 相似度分数(0-1)
     */
    protected function levenshteinSimilarity(string $text1, string $text2): float
    {
        // 对于长文本，我们只取前N个字符进行比较
        $maxLen = 255; // levenshtein函数的限制
        
        if (mb_strlen($text1, 'UTF-8') > $maxLen || mb_strlen($text2, 'UTF-8') > $maxLen) {
            $text1 = mb_substr($text1, 0, $maxLen, 'UTF-8');
            $text2 = mb_substr($text2, 0, $maxLen, 'UTF-8');
        }
        
        // 计算Levenshtein距离
        $distance = levenshtein($text1, $text2);
        
        // 计算最大可能距离
        $maxDistance = max(mb_strlen($text1, 'UTF-8'), mb_strlen($text2, 'UTF-8'));
        
        // 转换为相似度分数
        if ($maxDistance > 0) {
            return 1 - ($distance / $maxDistance);
        }
        
        return 1.0; // 两个空字符串视为完全相同
    }
    
    /**
     * 计算余弦相似度
     * 
     * @param string $text1 第一段文本
     * @param string $text2 第二段文本
     * @return float 相似度分数(0-1)
     */
    protected function cosineSimilarity(string $text1, string $text2): float
    {
        // 将文本分割成字符
        $chars1 = preg_split('//u', $text1, -1, PREG_SPLIT_NO_EMPTY);
        $chars2 = preg_split('//u', $text2, -1, PREG_SPLIT_NO_EMPTY);
        
        // 构建词频向量
        $vector1 = array_count_values($chars1);
        $vector2 = array_count_values($chars2);
        
        // 计算点积
        $dotProduct = 0;
        foreach ($vector1 as $char => $count) {
            if (isset($vector2[$char])) {
                $dotProduct += $count * $vector2[$char];
            }
        }
        
        // 计算向量模长
        $magnitude1 = sqrt(array_sum(array_map(function($x) { return $x * $x; }, $vector1)));
        $magnitude2 = sqrt(array_sum(array_map(function($x) { return $x * $x; }, $vector2)));
        
        // 计算余弦相似度
        if ($magnitude1 > 0 && $magnitude2 > 0) {
            return $dotProduct / ($magnitude1 * $magnitude2);
        }
        
        return 0.0;
    }
    
    /**
     * 获取处理器类型
     * 
     * {@inheritdoc}
     */
    public function getProcessorType(): string
    {
        return $this->processorType;
    }
}