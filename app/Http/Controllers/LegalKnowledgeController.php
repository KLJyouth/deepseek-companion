<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Services\LegalKnowledge\RelationExtractionService;
use App\Services\LegalKnowledge\ContractComparisonService;
use App\Services\LegalKnowledge\KnowledgeGraphVisualization;
use App\Services\LegalKnowledge\KnowledgeGraphQueryService;
use App\Models\ContractComparison;
use App\Models\LegalKnowledgeEntity;
use App\Models\LegalKnowledgeRelation;

/**
 * 法律知识控制器
 * 
 * 处理法律知识图谱和合同比对相关的请求
 * 
 * @version 1.0.0
 * @author 广西港妙科技有限公司
 * @copyright 版权所有 © 广西港妙科技有限公司
 */
class LegalKnowledgeController extends Controller
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
     * 知识图谱可视化服务实例
     * 
     * @var KnowledgeGraphVisualization
     */
    protected $visualizationService;
    
    /**
     * 知识图谱查询服务实例
     * 
     * @var KnowledgeGraphQueryService
     */
    protected $queryService;
    
    /**
     * 构造函数
     * 
     * @param RelationExtractionService $relationService 关系抽取服务实例
     * @param ContractComparisonService $comparisonService 合同比对服务实例
     * @param KnowledgeGraphVisualization $visualizationService 知识图谱可视化服务实例
     * @param KnowledgeGraphQueryService $queryService 知识图谱查询服务实例
     */
    public function __construct(
        RelationExtractionService $relationService,
        ContractComparisonService $comparisonService,
        KnowledgeGraphVisualization $visualizationService,
        KnowledgeGraphQueryService $queryService
    ) {
        $this->relationService = $relationService;
        $this->comparisonService = $comparisonService;
        $this->visualizationService = $visualizationService;
        $this->queryService = $queryService;
    }
    
    /**
     * 构建法律知识图谱
     * 
     * @param Request $request HTTP请求
     * @return \Illuminate\Http\JsonResponse JSON响应
     */
    public function buildKnowledgeGraph(Request $request)
    {
        try {
            // 验证请求参数
            $validated = $request->validate([
                'text' => 'required|string',
                'tenant_id' => 'nullable|integer',
                'options' => 'nullable|array'
            ]);
            
            // 构建知识图谱
            $graphData = $this->relationService->buildKnowledgeGraph(
                $validated['text'],
                $validated['tenant_id'] ?? null,
                $validated['options'] ?? []
            );
            
            // 生成可视化数据
            $visualizationData = $this->visualizationService->generateVisualization(
                $graphData,
                $validated['options'] ?? []
            );
            
            return response()->json([
                'success' => true,
                'data' => $visualizationData
            ]);
        } catch (\Exception $e) {
            Log::error('构建法律知识图谱失败: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => '构建法律知识图谱失败: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 比较两份合同文本
     * 
     * @param Request $request HTTP请求
     * @return \Illuminate\Http\JsonResponse JSON响应
     */
    public function compareContracts(Request $request)
    {
        try {
            // 验证请求参数
            $validated = $request->validate([
                'contract1' => 'required|string',
                'contract2' => 'required|string',
                'contract1_name' => 'required|string|max:100',
                'contract2_name' => 'required|string|max:100',
                'options' => 'nullable|array'
            ]);
            
            // 比较合同
            $comparisonResult = $this->comparisonService->compareContracts(
                $validated['contract1'],
                $validated['contract2'],
                $validated['options'] ?? []
            );
            
            // 保存比对结果到数据库
            $comparison = new ContractComparison();
            $comparison->tenant_id = $request->input('tenant_id');
            $comparison->user_id = Auth::id();
            $comparison->contract1_name = $validated['contract1_name'];
            $comparison->contract2_name = $validated['contract2_name'];
            $comparison->contract1_hash = md5($validated['contract1']);
            $comparison->contract2_hash = md5($validated['contract2']);
            $comparison->overall_similarity = $comparisonResult['overall_similarity'];
            $comparison->comparison_data = $comparisonResult;
            $comparison->key_differences = $comparisonResult['key_differences'];
            $comparison->status = 'completed';
            $comparison->comparison_options = $validated['options'] ?? [];
            $comparison->save();
            
            return response()->json([
                'success' => true,
                'data' => $comparisonResult,
                'comparison_id' => $comparison->id
            ]);
        } catch (\Exception $e) {
            Log::error('合同比对失败: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => '合同比对失败: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 合并合同比对结果与知识图谱
     * 
     * @param Request $request HTTP请求
     * @return \Illuminate\Http\JsonResponse JSON响应
     */
    public function mergeComparisonWithGraph(Request $request)
    {
        try {
            // 验证请求参数
            $validated = $request->validate([
                'comparison_id' => 'required|integer',
                'options' => 'nullable|array'
            ]);
            
            // 从数据库获取比对结果
            $comparison = ContractComparison::findOrFail($validated['comparison_id']);
            $comparisonResult = $comparison->comparison_data;
            
            // 如果没有比对数据，返回错误
            if (empty($comparisonResult)) {
                return response()->json([
                    'success' => false,
                    'message' => '未找到有效的比对数据'
                ], 400);
            }
            
            // 构建知识图谱
            // 这里我们假设已经有了合同文本，实际应用中可能需要从其他地方获取
            // 或者在比对时就保存合同文本
            $combinedText = $request->input('text', '');
            
            // 如果没有提供文本，返回错误
            if (empty($combinedText)) {
                return response()->json([
                    'success' => false,
                    'message' => '请提供用于构建知识图谱的文本'
                ], 400);
            }
            
            // 构建知识图谱
            $graphData = $this->relationService->buildKnowledgeGraph(
                $combinedText,
                $comparison->tenant_id,
                $validated['options'] ?? []
            );
            
            // 合并比对结果与知识图谱
            $mergedData = $this->visualizationService->mergeComparisonWithGraph(
                $comparisonResult,
                $graphData,
                $validated['options'] ?? []
            );
            
            // 更新比对记录
            $comparison->visualization_data = $mergedData;
            $comparison->save();
            
            return response()->json([
                'success' => true,
                'data' => $mergedData,
                'comparison_id' => $comparison->id
            ]);
        } catch (\Exception $e) {
            Log::error('合并比对结果与知识图谱失败: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => '合并比对结果与知识图谱失败: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 获取合同比对可视化数据
     * 
     * @param int $id 比对记录ID
     * @return \Illuminate\Http\JsonResponse JSON响应
     */
    public function getComparisonVisualization($id)
    {
        try {
            $comparison = ContractComparison::findOrFail($id);
            
            // 如果已经有可视化数据，直接返回
            if (!empty($comparison->visualization_data)) {
                return response()->json([
                    'success' => true,
                    'data' => $comparison->visualization_data
                ]);
            }
            
            // 如果没有可视化数据，但有比对数据，生成可视化数据
            if (!empty($comparison->comparison_data)) {
                $visualizationData = $this->visualizationService->generateComparisonVisualization(
                    $comparison->comparison_data
                );
                
                // 更新比对记录
                $comparison->visualization_data = $visualizationData;
                $comparison->save();
                
                return response()->json([
                    'success' => true,
                    'data' => $visualizationData
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => '未找到有效的比对数据'
            ], 400);
        } catch (\Exception $e) {
            Log::error('获取合同比对可视化数据失败: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => '获取合同比对可视化数据失败: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 查找与指定实体相关的所有实体
     * 
     * @param Request $request HTTP请求
     * @return \Illuminate\Http\JsonResponse JSON响应
     */
    public function findRelatedEntities(Request $request)
    {
        try {
            // 验证请求参数
            $validated = $request->validate([
                'entity_id' => 'required|integer',
                'max_depth' => 'nullable|integer|min:1|max:5',
                'relation_types' => 'nullable|array',
                'entity_types' => 'nullable|array',
                'weight_threshold' => 'nullable|numeric|min:0|max:1'
            ]);
            
            // 设置查询选项
            $options = [
                'max_depth' => $validated['max_depth'] ?? 2,
                'relation_types' => $validated['relation_types'] ?? [],
                'entity_types' => $validated['entity_types'] ?? [],
                'weight_threshold' => $validated['weight_threshold'] ?? 0.0
            ];
            
            // 查询相关实体
            $result = $this->queryService->findRelatedEntities(
                $validated['entity_id'],
                $options
            );
            
            if (isset($result['error'])) {
                return response()->json([
                    'success' => false,
                    'message' => $result['error']
                ], 400);
            }
            
            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('查找相关实体失败: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => '查找相关实体失败: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 查找两个实体之间的最短路径
     * 
     * @param Request $request HTTP请求
     * @return \Illuminate\Http\JsonResponse JSON响应
     */
    public function findShortestPath(Request $request)
    {
        try {
            // 验证请求参数
            $validated = $request->validate([
                'source_id' => 'required|integer',
                'target_id' => 'required|integer',
                'max_depth' => 'nullable|integer|min:1|max:5',
                'relation_types' => 'nullable|array',
                'weight_threshold' => 'nullable|numeric|min:0|max:1'
            ]);
            
            // 设置查询选项
            $options = [
                'max_depth' => $validated['max_depth'] ?? 5,
                'relation_types' => $validated['relation_types'] ?? [],
                'weight_threshold' => $validated['weight_threshold'] ?? 0.0
            ];
            
            // 查询最短路径
            $result = $this->queryService->findShortestPath(
                $validated['source_id'],
                $validated['target_id'],
                $options
            );
            
            if (isset($result['error'])) {
                return response()->json([
                    'success' => false,
                    'message' => $result['error']
                ], 400);
            }
            
            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('查找最短路径失败: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => '查找最短路径失败: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 分析合同风险
     * 
     * @param Request $request HTTP请求
     * @return \Illuminate\Http\JsonResponse JSON响应
     */
    public function analyzeContractRisk(Request $request)
    {
        try {
            // 验证请求参数
            $validated = $request->validate([
                'contract_text' => 'required|string',
                'risk_threshold' => 'nullable|numeric|min:0|max:1',
                'extract_entities' => 'nullable|boolean',
                'analyze_relations' => 'nullable|boolean',
                'include_context' => 'nullable|boolean'
            ]);
            
            // 设置分析选项
            $options = [
                'risk_threshold' => $validated['risk_threshold'] ?? 0.7,
                'extract_entities' => $validated['extract_entities'] ?? true,
                'analyze_relations' => $validated['analyze_relations'] ?? true,
                'include_context' => $validated['include_context'] ?? true
            ];
            
            // 分析合同风险
            $result = $this->queryService->analyzeContractRisk(
                $validated['contract_text'],
                $options
            );
            
            if (isset($result['error'])) {
                return response()->json([
                    'success' => false,
                    'message' => $result['error']
                ], 400);
            }
            
            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('分析合同风险失败: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => '分析合同风险失败: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 计算实体中心性
     * 
     * @param Request $request HTTP请求
     * @return \Illuminate\Http\JsonResponse JSON响应
     */
    public function calculateEntityCentrality(Request $request)
    {
        try {
            // 验证请求参数
            $validated = $request->validate([
                'entity_id' => 'required|integer',
                'centrality_type' => 'required|string|in:degree,betweenness,closeness',
                'max_depth' => 'nullable|integer|min:1|max:5',
                'relation_types' => 'nullable|array',
                'weight_threshold' => 'nullable|numeric|min:0|max:1'
            ]);
            
            // 设置计算选项
            $options = [
                'max_depth' => $validated['max_depth'] ?? 3,
                'relation_types' => $validated['relation_types'] ?? [],
                'weight_threshold' => $validated['weight_threshold'] ?? 0.0
            ];
            
            // 计算实体中心性
            $result = $this->queryService->calculateEntityCentrality(
                $validated['entity_id'],
                $validated['centrality_type'],
                $options
            );
            
            if (isset($result['error'])) {
                return response()->json([
                    'success' => false,
                    'message' => $result['error']
                ], 400);
            }
            
            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('计算实体中心性失败: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => '计算实体中心性失败: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 高亮显示合同差异
     * 
     * @param Request $request HTTP请求
     * @return \Illuminate\Http\JsonResponse JSON响应
     */
    public function highlightDifferences(Request $request)
    {
        try {
            // 验证请求参数
            $validated = $request->validate([
                'contract1' => 'required|string',
                'contract2' => 'required|string',
                'format' => 'nullable|string|in:html,json,text',
                'color_scheme' => 'nullable|string|in:standard,accessibility',
                'context_lines' => 'nullable|integer|min:0|max:10'
            ]);
            
            // 设置高亮选项
            $options = [
                'format' => $validated['format'] ?? 'html',
                'color_scheme' => $validated['color_scheme'] ?? 'standard',
                'context_lines' => $validated['context_lines'] ?? 3
            ];
            
            // 高亮差异
            $highlightResult = $this->comparisonService->highlightDifferences(
                $validated['contract1'],
                $validated['contract2'],
                $options
            );
            
            return response()->json([
                'success' => true,
                'data' => $highlightResult
            ]);
        } catch (\Exception $e) {
            Log::error('高亮显示合同差异失败: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => '高亮显示合同差异失败: ' . $e->getMessage()
            ], 500);
        }
    }
            );
            
            // 合并比对结果与知识图谱
            $mergedData = $this->visualizationService->mergeComparisonWithGraph(
                $comparisonResult,
                $graphData,
                $validated['options'] ?? []
            );
            
            // 保存可视化数据
            $comparison->visualization_data = $mergedData;
            $comparison->save();
            
            return response()->json([
                'success' => true,
                'data' => $mergedData,
                'comparison_id' => $comparison->id
            ]);
        } catch (\Exception $e) {
            Log::error('合并合同比对与知识图谱失败: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => '合并合同比对与知识图谱失败: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 获取合同比对可视化数据
     * 
     * @param int $id 比对记录ID
     * @return \Illuminate\Http\JsonResponse JSON响应
     */
    public function getComparisonVisualization($id)
    {
        try {
            // 从数据库中获取比对结果
            $comparison = ContractComparison::findOrFail($id);
            
            // 检查权限（如果需要）
            if ($comparison->tenant_id && $comparison->tenant_id != request()->input('tenant_id')) {
                return response()->json([
                    'success' => false,
                    'message' => '无权访问此比对结果'
                ], 403);
            }
            
            // 如果已经有可视化数据，直接返回
            if (!empty($comparison->visualization_data)) {
                return response()->json([
                    'success' => true,
                    'data' => $comparison->visualization_data
                ]);
            }
            
            // 否则生成可视化数据
            $visualizationData = [];
            
            // 如果有比对数据，生成可视化
            if (!empty($comparison->comparison_data)) {
                // 这里可以根据需要生成不同类型的可视化
                $visualizationData = [
                    'similarity' => [
                        'value' => $comparison->overall_similarity,
                        'formatted' => $comparison->formatted_similarity
                    ],
                    'differences' => [
                        'count' => count($comparison->key_differences),
                        'items' => $comparison->key_differences
                    ],
                    'created_at' => $comparison->created_at->format('Y-m-d H:i:s')
                ];
                
                // 保存可视化数据
                $comparison->visualization_data = $visualizationData;
                $comparison->save();
            }
            
            return response()->json([
                'success' => true,
                'data' => $visualizationData
            ]);
        } catch (\Exception $e) {
            Log::error('获取合同比对可视化数据失败: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => '获取合同比对可视化数据失败: ' . $e->getMessage()
            ], 500);
        }
    }
}