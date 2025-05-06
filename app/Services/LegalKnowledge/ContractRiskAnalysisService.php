<?php

namespace App\Services\LegalKnowledge;

use Illuminate\Support\Facades\Log;
use App\Services\LegalKnowledge\NlpProcessor\NlpProcessorInterface;
use App\Models\LegalKnowledgeEntity;
use App\Models\LegalKnowledgeRelation;

/**
 * 合同风险分析服务
 * 
 * 提供基于知识图谱的合同风险分析功能
 * 
 * @version 1.0.0
 * @author 广西港妙科技有限公司
 * @copyright 版权所有 © 广西港妙科技有限公司
 */
class ContractRiskAnalysisService
{
    /**
     * NLP处理器实例
     * 
     * @var NlpProcessorInterface
     */
    protected $nlpProcessor;
    
    /**
     * 关系抽取服务实例
     * 
     * @var RelationExtractionService
     */
    protected $relationService;
    
    /**
     * 知识图谱查询服务实例
     * 
     * @var KnowledgeGraphQueryService
     */
    protected $queryService;
    
    /**
     * 构造函数
     * 
     * @param NlpProcessorInterface $nlpProcessor NLP处理器实例
     * @param RelationExtractionService $relationService 关系抽取服务实例
     * @param KnowledgeGraphQueryService $queryService 知识图谱查询服务实例
     */
    public function __construct(
        NlpProcessorInterface $nlpProcessor,
        RelationExtractionService $relationService,
        KnowledgeGraphQueryService $queryService
    ) {
        $this->nlpProcessor = $nlpProcessor;
        $this->relationService = $relationService;
        $this->queryService = $queryService;
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
                'risk_threshold' => 0.7,       // 风险阈值
                'include_graph' => true,      // 是否包含知识图谱
                'tenant_id' => null,          // 租户ID
                'detailed_analysis' => true,   // 是否进行详细分析
                'risk_categories' => [         // 风险类别
                    'legal_compliance',       // 法律合规性
                    'financial_risk',         // 财务风险
                    'operational_risk',       // 运营风险
                    'contractual_obligations' // 合同义务
                ]
            ], $options);
            
            // 构建合同知识图谱
            $graphData = $this->relationService->buildKnowledgeGraph(
                $contractText,
                $options['tenant_id'],
                ['extract_entities' => true, 'extract_relations' => true]
            );
            
            // 提取合同关键条款
            $keyTerms = $this->extractKeyTerms($contractText);
            
            // 分析风险点
            $riskPoints = $this->identifyRiskPoints($graphData, $keyTerms, $options);
            
            // 计算总体风险评分
            $overallRiskScore = $this->calculateOverallRiskScore($riskPoints);
            
            // 生成风险报告
            $riskReport = $this->generateRiskReport($riskPoints, $overallRiskScore, $options);
            
            // 构建返回结果
            $result = [
                'overall_risk_score' => $overallRiskScore,
                'risk_level' => $this->mapScoreToRiskLevel($overallRiskScore),
                'risk_points' => $riskPoints,
                'risk_report' => $riskReport,
                'analysis_time' => date('Y-m-d H:i:s')
            ];
            
            // 如果需要包含知识图谱
            if ($options['include_graph']) {
                $result['graph_data'] = $graphData;
            }
            
            return $result;
        } catch (\Exception $e) {
            Log::error('合同风险分析失败: ' . $e->getMessage(), [
                'contract_length' => strlen($contractText),
                'options' => $options
            ]);
            
            return [
                'error' => '合同风险分析失败: ' . $e->getMessage(),
                'analysis_time' => date('Y-m-d H:i:s')
            ];
        }
    }
    
    /**
     * 提取合同关键条款
     * 
     * @param string $contractText 合同文本
     * @return array 关键条款列表
     */
    protected function extractKeyTerms(string $contractText): array
    {
        // 使用NLP处理器提取关键条款
        $keyTerms = $this->nlpProcessor->extractKeyPhrases($contractText, [
            'max_phrases' => 20,
            'min_relevance' => 0.6
        ]);
        
        // 对关键条款进行分类
        $categorizedTerms = [];
        foreach ($keyTerms as $term) {
            // 分析条款类型
            $termType = $this->analyzeTermType($term);
            
            if (!isset($categorizedTerms[$termType])) {
                $categorizedTerms[$termType] = [];
            }
            
            $categorizedTerms[$termType][] = $term;
        }
        
        return $categorizedTerms;
    }
    
    /**
     * 分析条款类型
     * 
     * @param array $term 条款信息
     * @return string 条款类型
     */
    protected function analyzeTermType(array $term): string
    {
        $text = $term['text'];
        
        // 根据关键词判断条款类型
        if (preg_match('/(违约|赔偿|责任|惩罚|罚款)/u', $text)) {
            return 'liability';
        } elseif (preg_match('/(付款|支付|费用|价格|报酬)/u', $text)) {
            return 'payment';
        } elseif (preg_match('/(期限|截止|日期|时间|延期)/u', $text)) {
            return 'timeline';
        } elseif (preg_match('/(保密|机密|隐私|不得泄露)/u', $text)) {
            return 'confidentiality';
        } elseif (preg_match('/(终止|解除|撤销|取消)/u', $text)) {
            return 'termination';
        } elseif (preg_match('/(争议|纠纷|仲裁|诉讼|管辖)/u', $text)) {
            return 'dispute';
        } elseif (preg_match('/(知识产权|专利|商标|著作权|版权)/u', $text)) {
            return 'intellectual_property';
        } elseif (preg_match('/(保证|承诺|陈述|声明)/u', $text)) {
            return 'warranty';
        }
        
        return 'other';
    }
    
    /**
     * 识别风险点
     * 
     * @param array $graphData 知识图谱数据
     * @param array $keyTerms 关键条款
     * @param array $options 分析选项
     * @return array 风险点列表
     */
    protected function identifyRiskPoints(array $graphData, array $keyTerms, array $options): array
    {
        $riskPoints = [];
        
        // 分析实体风险
        $entityRisks = $this->analyzeEntityRisks($graphData['nodes'], $options);
        
        // 分析关系风险
        $relationRisks = $this->analyzeRelationRisks($graphData['edges'], $options);
        
        // 分析条款风险
        $termRisks = $this->analyzeTermRisks($keyTerms, $options);
        
        // 合并风险点
        $riskPoints = array_merge($entityRisks, $relationRisks, $termRisks);
        
        // 按风险分值排序
        usort($riskPoints, function($a, $b) {
            return $b['risk_score'] <=> $a['risk_score'];
        });
        
        return $riskPoints;
    }
    
    /**
     * 分析实体风险
     * 
     * @param array $entities 实体列表
     * @param array $options 分析选项
     * @return array 实体风险列表
     */
    protected function analyzeEntityRisks(array $entities, array $options): array
    {
        $entityRisks = [];
        
        foreach ($entities as $entity) {
            // 跳过低风险实体类型
            if (in_array($entity['type'], ['time', 'location', 'organization'])) {
                continue;
            }
            
            // 检查实体是否存在于知识库中
            $knownEntity = $this->queryService->findEntityByName($entity['label']);
            
            // 如果实体在知识库中存在风险标记
            if ($knownEntity && isset($knownEntity['properties']['risk_level'])) {
                $riskScore = $this->mapRiskLevelToScore($knownEntity['properties']['risk_level']);
                
                // 如果风险分值超过阈值
                if ($riskScore >= $options['risk_threshold']) {
                    $entityRisks[] = [
                        'type' => 'entity',
                        'entity_type' => $entity['type'],
                        'entity_name' => $entity['label'],
                        'risk_score' => $riskScore,
                        'risk_category' => $knownEntity['properties']['risk_category'] ?? 'unknown',
                        'description' => $knownEntity['properties']['risk_description'] ?? '实体存在潜在风险',
                        'recommendation' => $knownEntity['properties']['risk_recommendation'] ?? '建议进一步审查该实体'
                    ];
                }
            }
            
            // 对于特定类型的实体进行额外风险分析
            if ($entity['type'] == 'clause' && isset($entity['properties']['text'])) {
                $clauseRisk = $this->analyzeClauseRisk($entity['properties']['text']);
                
                if ($clauseRisk['risk_score'] >= $options['risk_threshold']) {
                    $entityRisks[] = array_merge(
                        ['type' => 'clause', 'entity_name' => $entity['label']],
                        $clauseRisk
                    );
                }
            }
        }
        
        return $entityRisks;
    }
    
    /**
     * 分析关系风险
     * 
     * @param array $relations 关系列表
     * @param array $options 分析选项
     * @return array 关系风险列表
     */
    protected function analyzeRelationRisks(array $relations, array $options): array
    {
        $relationRisks = [];
        
        foreach ($relations as $relation) {
            // 检查高风险关系类型
            if (in_array($relation['label'], ['conflicts_with', 'contradicts', 'excludes'])) {
                $relationRisks[] = [
                    'type' => 'relation',
                    'relation_type' => $relation['label'],
                    'source' => $relation['source'],
                    'target' => $relation['target'],
                    'risk_score' => 0.85,
                    'risk_category' => 'contractual_obligations',
                    'description' => '合同条款存在冲突或矛盾',
                    'recommendation' => '建议重新审查相关条款，消除冲突'
                ];
            }
            
            // 检查义务关系
            if ($relation['label'] == 'obligates' && isset($relation['properties']['strength'])) {
                // 如果义务强度较高
                if ($relation['properties']['strength'] >= 0.7) {
                    $relationRisks[] = [
                        'type' => 'relation',
                        'relation_type' => $relation['label'],
                        'source' => $relation['source'],
                        'target' => $relation['target'],
                        'risk_score' => 0.75,
                        'risk_category' => 'contractual_obligations',
                        'description' => '合同包含高强度义务条款',
                        'recommendation' => '建议评估履行该义务的能力和成本'
                    ];
                }
            }
        }
        
        return $relationRisks;
    }
    
    /**
     * 分析条款风险
     * 
     * @param array $keyTerms 关键条款
     * @param array $options 分析选项
     * @return array 条款风险列表
     */
    protected function analyzeTermRisks(array $keyTerms, array $options): array
    {
        $termRisks = [];
        
        // 高风险条款类型
        $highRiskTypes = ['liability', 'termination', 'dispute', 'intellectual_property'];
        
        foreach ($highRiskTypes as $type) {
            if (isset($keyTerms[$type])) {
                foreach ($keyTerms[$type] as $term) {
                    $clauseRisk = $this->analyzeClauseRisk($term['text']);
                    
                    if ($clauseRisk['risk_score'] >= $options['risk_threshold']) {
                        $termRisks[] = array_merge(
                            ['type' => 'term', 'term_type' => $type, 'term_text' => $term['text']],
                            $clauseRisk
                        );
                    }
                }
            }
        }
        
        return $termRisks;
    }
    
    /**
     * 分析条款风险
     * 
     * @param string $clauseText 条款文本
     * @return array 风险信息
     */
    protected function analyzeClauseRisk(string $clauseText): array
    {
        // 风险关键词及其权重
        $riskKeywords = [
            '违约' => 0.8,
            '赔偿' => 0.7,
            '责任' => 0.6,
            '罚款' => 0.75,
            '终止' => 0.65,
            '解除' => 0.65,
            '争议' => 0.6,
            '仲裁' => 0.55,
            '诉讼' => 0.7,
            '知识产权' => 0.65,
            '专利' => 0.6,
            '商标' => 0.6,
            '著作权' => 0.6,
            '保密' => 0.55,
            '不可抗力' => 0.5
        ];
        
        // 计算风险分值
        $riskScore = 0;
        $matchedKeywords = [];
        
        foreach ($riskKeywords as $keyword => $weight) {
            if (mb_strpos($clauseText, $keyword) !== false) {
                $riskScore = max($riskScore, $weight);
                $matchedKeywords[] = $keyword;
            }
        }
        
        // 确定风险类别
        $riskCategory = 'other';
        if (preg_match('/(违约|赔偿|责任|罚款)/u', $clauseText)) {
            $riskCategory = 'liability';
        } elseif (preg_match('/(终止|解除)/u', $clauseText)) {
            $riskCategory = 'termination';
        } elseif (preg_match('/(争议|仲裁|诉讼)/u', $clauseText)) {
            $riskCategory = 'dispute';
        } elseif (preg_match('/(知识产权|专利|商标|著作权)/u', $clauseText)) {
            $riskCategory = 'intellectual_property';
        }
        
        // 生成风险描述和建议
        $description = '条款包含潜在风险关键词: ' . implode(', ', $matchedKeywords);
        $recommendation = '建议法务专业人员审查该条款';
        
        return [
            'risk_score' => $riskScore,
            'risk_category' => $riskCategory,
            'description' => $description,
            'recommendation' => $recommendation
        ];
    }
    
    /**
     * 计算总体风险评分
     * 
     * @param array $riskPoints 风险点列表
     * @return float 总体风险评分
     */
    protected function calculateOverallRiskScore(array $riskPoints): float
    {
        if (empty($riskPoints)) {
            return 0.0;
        }
        
        // 计算风险点的加权平均分
        $totalScore = 0;
        $totalWeight = 0;
        
        foreach ($riskPoints as $risk) {
            // 根据风险类别设置权重
            $weight = 1.0;
            switch ($risk['risk_category']) {
                case 'liability':
                    $weight = 1.5;
                    break;
                case 'termination':
                    $weight = 1.3;
                    break;
                case 'dispute':
                    $weight = 1.2;
                    break;
                case 'intellectual_property':
                    $weight = 1.4;
                    break;
            }
            
            $totalScore += $risk['risk_score'] * $weight;
            $totalWeight += $weight;
        }
        
        // 计算加权平均分
        $weightedAverage = $totalScore / $totalWeight;
        
        // 考虑风险点数量的影响
        $countFactor = min(1.0, count($riskPoints) / 10.0);
        
        // 最终风险评分
        $finalScore = $weightedAverage * (0.7 + 0.3 * $countFactor);
        
        // 确保分值在0-1之间
        return min(1.0, max(0.0, $finalScore));
    }
    
    /**
     * 生成风险报告
     * 
     * @param array $riskPoints 风险点列表
     * @param float $overallRiskScore 总体风险评分
     * @param array $options 分析选项
     * @return array 风险报告
     */
    protected function generateRiskReport(array $riskPoints, float $overallRiskScore, array $options): array
    {
        // 按风险类别分组
        $risksByCategory = [];
        foreach ($riskPoints as $risk) {
            $category = $risk['risk_category'];
            if (!isset($risksByCategory[$category])) {
                $risksByCategory[$category] = [];
            }
            $risksByCategory[$category][] = $risk;
        }
        
        // 生成风险摘要
        $summary = [
            'risk_level' => $this->mapScoreToRiskLevel($overallRiskScore),
            'total_risk_points' => count($riskPoints),
            'risk_categories' => array_keys($risksByCategory),
            'high_risk_count' => count(array_filter($riskPoints, function($risk) {
                return $risk['risk_score'] >= 0.8;
            })),
            'medium_risk_count' => count(array_filter($riskPoints, function($risk) {
                return $risk['risk_score'] >= 0.5 && $risk['risk_score'] < 0.8;
            })),
            'low_risk_count' => count(array_filter($riskPoints, function($risk) {
                return $risk['risk_score'] < 0.5;
            }))
        ];
        
        // 生成风险类别分析
        $categoryAnalysis = [];
        foreach ($risksByCategory as $category => $risks) {
            // 计算该类别的平均风险分值
            $avgScore = array_sum(array_column($risks, 'risk_score')) / count($risks);
            
            $categoryAnalysis[$category] = [
                'risk_count' => count($risks),
                'average_risk_score' => $avgScore,
                'risk_level' => $this->mapScoreToRiskLevel($avgScore),
                'top_risks' => array_slice($risks, 0, 3) // 取前三个高风险点
            ];
        }
        
        // 生成风险缓解建议
        $mitigationSuggestions = $this->generateMitigationSuggestions($risksByCategory);
        
        return [
            'summary' => $summary,
            'category_analysis' => $categoryAnalysis,
            'mitigation_suggestions' => $mitigationSuggestions
        ];
    }
    
    /**
     * 生成风险缓解建议
     * 
     * @param array $risksByCategory 按类别分组的风险点
     * @return array 缓解建议
     */
    protected function generateMitigationSuggestions(array $risksByCategory): array
    {
        $suggestions = [];
        
        // 针对不同风险类别生成建议
        $categoryTemplates = [
            'liability' => [
                'title' => '责任风险缓解建议',
                'suggestions' => [
                    '明确界定各方责任范围和限制',
                    '设置合理的赔偿上限',
                    '考虑增加免责条款',
                    '评估保险覆盖范围'
                ]
            ],
            'termination' => [
                'title' => '终止风险缓解建议',
                'suggestions' => [
                    '明确终止条件和流程',
                    '设置合理的通知期限',
                    '规定终止后的权利义务',
                    '考虑过渡期安排'
                ]
            ],
            'dispute' => [
                'title' => '争议风险缓解建议',
                'suggestions' => [
                    '优先选择调解或仲裁解决争议',
                    '明确管辖法院和适用法律',
                    '设置争议解决的时间限制',
                    '考虑争议解决成本分担'
                ]
            ],
            'intellectual_property' => [
                'title' => '知识产权风险缓解建议',
                'suggestions' => [
                    '明确界定知识产权的归属',
                    '规定侵权责任和赔偿方式',
                    '设置保密义务和期限',
                    '考虑许可使用的范围和限制'
                ]
            ],
            'contractual_obligations' => [
                'title' => '合同义务风险缓解建议',
                'suggestions' => [
                    '明确各方义务的履行标准和时间',
                    '设置违约责任和补救措施',
                    '考虑不可抗力条款',
                    '评估履行义务的能力和成本'
                ]
            ]
        ];
        
        // 根据存在的风险类别生成建议
        foreach ($risksByCategory as $category => $risks) {
            if (isset($categoryTemplates[$category])) {
                $suggestions[] = $categoryTemplates[$category];
            }
        }
        
        // 如果没有匹配的类别，添加通用建议
        if (empty($suggestions)) {
            $suggestions[] = [
                'title' => '通用风险缓解建议',
                'suggestions' => [
                    '请法律专业人员全面审查合同',
                    '明确各方权利义务',
                    '设置合理的违约责任',
                    '考虑争议解决机制'
                ]
            ];
        }
        
        return $suggestions;
    }
    
    /**
     * 将风险分值映射为风险等级
     * 
     * @param float $score 风险分值
     * @return string 风险等级
     */
    protected function mapScoreToRiskLevel(float $score): string
    {
        if ($score >= 0.8) {
            return '高风险';
        } elseif ($score >= 0.5) {
            return '中风险';
        } else {
            return '低风险';
        }
    }
    
    /**
     * 将风险等级映射为风险分值
     * 
     * @param string $level 风险等级
     * @return float 风险分值
     */
    protected function mapRiskLevelToScore(string $level): float
    {
        switch ($level) {
            case '高风险':
                return 0.9;
            case '中风险':
                return 0.65;
            case '低风险':
                return 0.3;
            default:
                return 0.0;
        }
    }
}