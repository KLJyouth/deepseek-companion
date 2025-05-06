<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Services\LegalKnowledge\ContractRiskAnalysisService;
use App\Services\LegalKnowledge\KnowledgeGraphVisualization;

/**
 * 合同风险分析控制器
 * 
 * 处理合同风险分析相关的请求
 * 
 * @version 1.0.0
 * @author 广西港妙科技有限公司
 * @copyright 版权所有 © 广西港妙科技有限公司
 */
class ContractRiskAnalysisController extends Controller
{
    /**
     * 风险分析服务实例
     * 
     * @var ContractRiskAnalysisService
     */
    protected $riskAnalysisService;
    
    /**
     * 知识图谱可视化服务实例
     * 
     * @var KnowledgeGraphVisualization
     */
    protected $visualizationService;
    
    /**
     * 构造函数
     * 
     * @param ContractRiskAnalysisService $riskAnalysisService 风险分析服务实例
     * @param KnowledgeGraphVisualization $visualizationService 知识图谱可视化服务实例
     */
    public function __construct(
        ContractRiskAnalysisService $riskAnalysisService,
        KnowledgeGraphVisualization $visualizationService
    ) {
        $this->riskAnalysisService = $riskAnalysisService;
        $this->visualizationService = $visualizationService;
    }
    
    /**
     * 显示合同风险分析页面
     * 
     * @return \Illuminate\View\View
     */
    public function index()
    {
        return view('legal-knowledge.contract-risk-analysis.index');
    }
    
    /**
     * 分析合同风险
     * 
     * @param Request $request 请求实例
     * @return \Illuminate\Http\JsonResponse
     */
    public function analyze(Request $request)
    {
        try {
            // 验证请求参数
            $validated = $request->validate([
                'contract_text' => 'required|string|min:10',
                'risk_threshold' => 'nullable|numeric|min:0|max:1',
                'include_graph' => 'nullable|boolean',
                'detailed_analysis' => 'nullable|boolean',
                'risk_categories' => 'nullable|array',
                'risk_categories.*' => 'string|in:legal_compliance,financial_risk,operational_risk,contractual_obligations'
            ]);
            
            // 获取当前租户ID
            $tenantId = Auth::user()->tenant_id;
            
            // 设置分析选项
            $options = [
                'risk_threshold' => $request->input('risk_threshold', 0.7),
                'include_graph' => $request->input('include_graph', true),
                'tenant_id' => $tenantId,
                'detailed_analysis' => $request->input('detailed_analysis', true),
                'risk_categories' => $request->input('risk_categories', [
                    'legal_compliance',
                    'financial_risk',
                    'operational_risk',
                    'contractual_obligations'
                ])
            ];
            
            // 分析合同风险
            $result = $this->riskAnalysisService->analyzeContractRisk(
                $request->input('contract_text'),
                $options
            );
            
            // 记录分析日志
            Log::info('合同风险分析完成', [
                'tenant_id' => $tenantId,
                'contract_length' => strlen($request->input('contract_text')),
                'risk_score' => $result['overall_risk_score'] ?? null,
                'risk_level' => $result['risk_level'] ?? null,
                'risk_points_count' => count($result['risk_points'] ?? [])
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('合同风险分析失败: ' . $e->getMessage(), [
                'contract_length' => strlen($request->input('contract_text', '')),
                'user_id' => Auth::id(),
                'tenant_id' => Auth::user()->tenant_id ?? null
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '合同风险分析失败: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 获取知识图谱可视化数据
     * 
     * @param Request $request 请求实例
     * @return \Illuminate\Http\JsonResponse
     */
    public function getGraphVisualization(Request $request)
    {
        try {
            // 验证请求参数
            $validated = $request->validate([
                'graph_data' => 'required|array',
                'layout_type' => 'nullable|string|in:force,circular,hierarchical',
                'node_size' => 'nullable|integer|min:5|max:50',
                'edge_width' => 'nullable|integer|min:1|max:10'
            ]);
            
            // 设置可视化选项
            $options = [
                'layout_type' => $request->input('layout_type', 'force'),
                'node_size' => $request->input('node_size', 20),
                'edge_width' => $request->input('edge_width', 2),
                'node_color_map' => [
                    'clause' => '#1f77b4',
                    'entity' => '#ff7f0e',
                    'obligation' => '#2ca02c',
                    'right' => '#d62728',
                    'risk' => '#9467bd',
                    'default' => '#8c564b'
                ],
                'edge_color_map' => [
                    'contains' => '#1f77b4',
                    'obligates' => '#ff7f0e',
                    'grants' => '#2ca02c',
                    'conflicts_with' => '#d62728',
                    'default' => '#7f7f7f'
                ]
            ];
            
            // 生成可视化数据
            $visualData = $this->visualizationService->generateVisualization(
                $request->input('graph_data'),
                $options
            );
            
            return response()->json([
                'success' => true,
                'data' => $visualData
            ]);
        } catch (\Exception $e) {
            Log::error('知识图谱可视化失败: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => '知识图谱可视化失败: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 导出风险分析报告
     * 
     * @param Request $request 请求实例
     * @return \Illuminate\Http\Response
     */
    public function exportReport(Request $request)
    {
        try {
            // 验证请求参数
            $validated = $request->validate([
                'risk_data' => 'required|array',
                'format' => 'required|string|in:pdf,docx,html',
                'include_graph' => 'nullable|boolean'
            ]);
            
            $riskData = $request->input('risk_data');
            $format = $request->input('format');
            $includeGraph = $request->input('include_graph', true);
            
            // 生成报告文件名
            $filename = '合同风险分析报告_' . date('YmdHis') . '.' . $format;
            
            // 根据不同格式生成报告
            switch ($format) {
                case 'pdf':
                    // 生成PDF报告
                    $content = $this->generatePdfReport($riskData, $includeGraph);
                    $headers = [
                        'Content-Type' => 'application/pdf',
                        'Content-Disposition' => 'attachment; filename="' . $filename . '"'
                    ];
                    break;
                    
                case 'docx':
                    // 生成Word报告
                    $content = $this->generateDocxReport($riskData, $includeGraph);
                    $headers = [
                        'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'Content-Disposition' => 'attachment; filename="' . $filename . '"'
                    ];
                    break;
                    
                case 'html':
                default:
                    // 生成HTML报告
                    $content = $this->generateHtmlReport($riskData, $includeGraph);
                    $headers = [
                        'Content-Type' => 'text/html',
                        'Content-Disposition' => 'attachment; filename="' . $filename . '"'
                    ];
                    break;
            }
            
            // 记录导出日志
            Log::info('导出风险分析报告', [
                'user_id' => Auth::id(),
                'tenant_id' => Auth::user()->tenant_id ?? null,
                'format' => $format,
                'risk_level' => $riskData['risk_level'] ?? null
            ]);
            
            return response($content, 200, $headers);
        } catch (\Exception $e) {
            Log::error('导出风险分析报告失败: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => '导出风险分析报告失败: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 生成PDF格式的风险分析报告
     * 
     * @param array $riskData 风险数据
     * @param bool $includeGraph 是否包含知识图谱
     * @return string 报告内容
     */
    protected function generatePdfReport(array $riskData, bool $includeGraph): string
    {
        // 使用TCPDF库生成PDF报告
        require_once(base_path('vendor/tecnickcom/tcpdf/tcpdf.php'));
        
        // 创建PDF文档实例
        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        
        // 设置文档信息
        $pdf->SetCreator('司单服Ai智能安全法务');
        $pdf->SetAuthor('广西港妙科技有限公司');
        $pdf->SetTitle('合同风险分析报告');
        $pdf->SetSubject('合同风险分析报告');
        $pdf->SetKeywords('合同, 风险分析, 法律, 智能分析');
        
        // 设置页眉和页脚
        $pdf->setHeaderFont(Array('stsongstd', '', 10));
        $pdf->setFooterFont(Array('stsongstd', '', 8));
        $pdf->SetHeaderData('', 0, '合同风险分析报告', '司单服Ai智能安全法务 - 广西港妙科技有限公司', array(0,64,128), array(0,64,128));
        $pdf->setFooterData(array(0,64,0), array(0,64,128));
        
        // 设置页边距
        $pdf->SetMargins(15, 20, 15);
        $pdf->SetHeaderMargin(5);
        $pdf->SetFooterMargin(10);
        
        // 设置自动分页
        $pdf->SetAutoPageBreak(TRUE, 15);
        
        // 设置字体
        $pdf->SetFont('stsongstd', '', 10);
        
        // 添加页面
        $pdf->AddPage();
        
        // 生成报告内容
        $html = '<h1 style="text-align:center;color:#3f51b5;">合同风险分析报告</h1>';
        
        // 添加风险评估摘要
        $score = $riskData['overall_risk_score'] ?? 0;
        $riskLevel = $score < 0.4 ? '低' : ($score < 0.7 ? '中' : '高');
        $riskColor = $score < 0.4 ? '#4caf50' : ($score < 0.7 ? '#ff9800' : '#f44336');
        
        $html .= '<h2 style="color:#3f51b5;">风险评估摘要</h2>';
        $html .= '<div style="background-color:#f5f5f5;padding:10px;border-radius:5px;margin-bottom:15px;">';
        $html .= '<p><strong>风险等级:</strong> <span style="color:'.$riskColor.';">'.$riskLevel.'</span> (风险分数: '.number_format($score, 2).')</p>';
        $html .= '<p><strong>摘要:</strong> '.$riskData['summary'] ?? '未提供风险摘要'.'</p>';
        $html .= '</div>';
        
        // 添加风险点详情
        $html .= '<h2 style="color:#3f51b5;">风险点详情</h2>';
        
        if (!empty($riskData['risk_points'])) {
            foreach ($riskData['risk_points'] as $index => $point) {
                $severity = $point['severity'] ?? 0;
                $borderColor = $severity >= 0.7 ? '#f44336' : ($severity >= 0.4 ? '#ff9800' : '#4caf50');
                
                $html .= '<div style="margin-bottom:15px;padding:10px;border-left:4px solid '.$borderColor.';background-color:#f5f5f5;">';
                $html .= '<h3>'.($index + 1).'. '.$point['title'].'</h3>';
                $html .= '<p>'.$point['description'].'</p>';
                $html .= '<p style="color:#3f51b5;font-style:italic;"><strong>建议:</strong> '.$point['recommendation'].'</p>';
                $html .= '</div>';
            }
        } else {
            $html .= '<p>未发现风险点</p>';
        }
        
        // 添加知识图谱（如果有）
        if ($includeGraph && !empty($riskData['graph_image'])) {
            $html .= '<h2 style="color:#3f51b5;">合同知识图谱</h2>';
            $html .= '<div style="text-align:center;">';
            // 如果有Base64编码的图像数据
            if (strpos($riskData['graph_image'], 'data:image') === 0) {
                $imgData = explode(',', $riskData['graph_image']);
                $pdf->Image('@'.base64_decode($imgData[1]), 15, $pdf->GetY(), 180, 0, '', '', '', false, 300);
                $html .= '<p style="margin-top:180px;">知识图谱可视化</p>';
            } else {
                $html .= '<p>知识图谱数据可用，但图像未生成。请在在线版本中查看交互式图谱。</p>';
            }
            $html .= '</div>';
        }
        
        // 添加风险分布统计
        $html .= '<h2 style="color:#3f51b5;">风险分布统计</h2>';
        $html .= '<table border="1" cellpadding="5">';
        $html .= '<tr style="background-color:#f2f2f2;font-weight:bold;">';
        $html .= '<th>风险类别</th><th>风险点数量</th><th>平均风险值</th><th>最高风险值</th>';
        $html .= '</tr>';
        
        // 计算风险分布统计
        $categories = [
            'legal_compliance' => ['name' => '法律合规性', 'count' => 0, 'sum' => 0, 'max' => 0],
            'financial_risk' => ['name' => '财务风险', 'count' => 0, 'sum' => 0, 'max' => 0],
            'operational_risk' => ['name' => '运营风险', 'count' => 0, 'sum' => 0, 'max' => 0],
            'contractual_obligations' => ['name' => '合同义务', 'count' => 0, 'sum' => 0, 'max' => 0]
        ];
        
        if (!empty($riskData['risk_points'])) {
            foreach ($riskData['risk_points'] as $point) {
                $category = $point['category'] ?? 'legal_compliance';
                $severity = $point['severity'] ?? 0;
                
                if (isset($categories[$category])) {
                    $categories[$category]['count']++;
                    $categories[$category]['sum'] += $severity;
                    $categories[$category]['max'] = max($categories[$category]['max'], $severity);
                }
            }
        }
        
        foreach ($categories as $category) {
            $avgRisk = $category['count'] > 0 ? $category['sum'] / $category['count'] : 0;
            $html .= '<tr>';
            $html .= '<td>'.$category['name'].'</td>';
            $html .= '<td>'.$category['count'].'</td>';
            $html .= '<td>'.number_format($avgRisk, 2).'</td>';
            $html .= '<td>'.number_format($category['max'], 2).'</td>';
            $html .= '</tr>';
        }
        
        $html .= '</table>';
        
        // 添加页脚信息
        $html .= '<div style="text-align:center;margin-top:20px;color:#666;font-size:9pt;">';
        $html .= '<p>本报告由司单服Ai智能安全法务系统自动生成</p>';
        $html .= '<p>版权所有 © '.date('Y').' 广西港妙科技有限公司</p>';
        $html .= '<p style="font-style:italic;font-size:8pt;color:#999;">免责声明：本报告仅供参考，不构成法律建议。具体法律问题请咨询专业律师。</p>';
        $html .= '</div>';
        
        // 写入HTML内容
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // 关闭并返回PDF文档
        return $pdf->Output('', 'S');
    }
    
    /**
     * 生成Word格式的风险分析报告
     * 
     * @param array $riskData 风险数据
     * @param bool $includeGraph 是否包含知识图谱
     * @return string 报告内容
     */
    protected function generateDocxReport(array $riskData, bool $includeGraph): string
    {
        // 使用PhpWord库生成Word文档
        // 如果PhpWord库不存在，则自动安装
        if (!class_exists('\PhpOffice\PhpWord\PhpWord')) {
            // 记录日志
            Log::info('PhpWord库不存在，正在自动安装...');
            
            // 尝试通过Composer安装PhpWord
            shell_exec('composer require phpoffice/phpword');
            
            // 检查是否安装成功
            if (!class_exists('\PhpOffice\PhpWord\PhpWord')) {
                throw new \Exception('无法安装PhpWord库，请手动运行: composer require phpoffice/phpword');
            }
        }
        
        // 创建PhpWord实例
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        
        // 设置文档属性
        $properties = $phpWord->getDocInfo();
        $properties->setCreator('司单服Ai智能安全法务');
        $properties->setCompany('广西港妙科技有限公司');
        $properties->setTitle('合同风险分析报告');
        $properties->setDescription('基于知识图谱的合同风险分析报告');
        $properties->setCategory('法律文档');
        $properties->setLastModifiedBy('司单服Ai智能安全法务系统');
        $properties->setCreated(time());
        $properties->setModified(time());
        $properties->setSubject('合同风险分析');
        $properties->setKeywords('合同 风险分析 法律 智能分析');
        
        // 添加文档样式
        $phpWord->addTitleStyle(1, ['bold' => true, 'size' => 18, 'color' => '3F51B5'], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
        $phpWord->addTitleStyle(2, ['bold' => true, 'size' => 14, 'color' => '3F51B5']);
        $phpWord->addTitleStyle(3, ['bold' => true, 'size' => 12]);
        
        // 创建节
        $section = $phpWord->addSection();
        
        // 添加页眉
        $header = $section->addHeader();
        $header->addText('合同风险分析报告', ['bold' => true], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
        
        // 添加页脚
        $footer = $section->addFooter();
        $footer->addText('司单服Ai智能安全法务 - 广西港妙科技有限公司 © ' . date('Y'), 
            ['size' => 8, 'color' => '666666'], 
            ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
        
        // 添加标题
        $section->addTitle('合同风险分析报告', 1);
        $section->addTextBreak(1);
        
        // 添加风险评估摘要
        $section->addTitle('风险评估摘要', 2);
        
        $score = $riskData['overall_risk_score'] ?? 0;
        $riskLevel = $score < 0.4 ? '低' : ($score < 0.7 ? '中' : '高');
        $riskColor = $score < 0.4 ? '4CAF50' : ($score < 0.7 ? 'FF9800' : 'F44336');
        
        // 创建风险评估表格
        $summaryTable = $section->addTable(['borderSize' => 1, 'borderColor' => 'DDDDDD', 'cellMargin' => 80]);
        $summaryTable->addRow();
        $summaryTable->addCell(3000, ['bgColor' => 'F5F5F5'])->addText('风险等级', ['bold' => true]);
        $summaryTable->addCell(7000)->addText($riskLevel, ['color' => $riskColor, 'bold' => true]);
        
        $summaryTable->addRow();
        $summaryTable->addCell(3000, ['bgColor' => 'F5F5F5'])->addText('风险分数', ['bold' => true]);
        $summaryTable->addCell(7000)->addText(number_format($score, 2));
        
        $summaryTable->addRow();
        $summaryTable->addCell(3000, ['bgColor' => 'F5F5F5'])->addText('摘要', ['bold' => true]);
        $summaryTable->addCell(7000)->addText($riskData['summary'] ?? '未提供风险摘要');
        
        $section->addTextBreak(1);
        
        // 添加风险点详情
        $section->addTitle('风险点详情', 2);
        
        if (!empty($riskData['risk_points'])) {
            foreach ($riskData['risk_points'] as $index => $point) {
                $severity = $point['severity'] ?? 0;
                $borderColor = $severity >= 0.7 ? 'F44336' : ($severity >= 0.4 ? 'FF9800' : '4CAF50');
                
                // 创建风险点表格
                $riskTable = $section->addTable(['borderSize' => 1, 'borderColor' => $borderColor, 'cellMargin' => 80]);
                
                $riskTable->addRow();
                $riskTable->addCell(10000, ['bgColor' => 'F5F5F5'])
                    ->addText(($index + 1) . '. ' . $point['title'], ['bold' => true]);
                
                $riskTable->addRow();
                $riskTable->addCell(10000)->addText($point['description']);
                
                $riskTable->addRow();
                $riskTable->addCell(10000)->addText('建议: ' . $point['recommendation'], 
                    ['italic' => true, 'color' => '3F51B5']);
                
                $section->addTextBreak(1);
            }
        } else {
            $section->addText('未发现风险点');
            $section->addTextBreak(1);
        }
        
        // 添加知识图谱（如果有）
        if ($includeGraph && !empty($riskData['graph_image'])) {
            $section->addTitle('合同知识图谱', 2);
            
            // 如果有Base64编码的图像数据
            if (strpos($riskData['graph_image'], 'data:image') === 0) {
                $imgData = explode(',', $riskData['graph_image']);
                $tempFile = tempnam(sys_get_temp_dir(), 'graph_image');
                file_put_contents($tempFile, base64_decode($imgData[1]));
                $section->addImage($tempFile, ['width' => 450, 'height' => 300, 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
                unlink($tempFile); // 删除临时文件
            } else {
                $section->addText('知识图谱数据可用，但图像未生成。请在在线版本中查看交互式图谱。', 
                    ['italic' => true], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
            }
            
            $section->addTextBreak(1);
        }
        
        // 添加风险分布统计
        $section->addTitle('风险分布统计', 2);
        
        // 创建风险分布表格
        $statsTable = $section->addTable(['borderSize' => 1, 'borderColor' => 'DDDDDD', 'cellMargin' => 80]);
        
        // 添加表头
        $statsTable->addRow(['bgColor' => 'F2F2F2']);
        $statsTable->addCell(2500)->addText('风险类别', ['bold' => true]);
        $statsTable->addCell(2500)->addText('风险点数量', ['bold' => true]);
        $statsTable->addCell(2500)->addText('平均风险值', ['bold' => true]);
        $statsTable->addCell(2500)->addText('最高风险值', ['bold' => true]);
        
        // 计算风险分布统计
        $categories = [
            'legal_compliance' => ['name' => '法律合规性', 'count' => 0, 'sum' => 0, 'max' => 0],
            'financial_risk' => ['name' => '财务风险', 'count' => 0, 'sum' => 0, 'max' => 0],
            'operational_risk' => ['name' => '运营风险', 'count' => 0, 'sum' => 0, 'max' => 0],
            'contractual_obligations' => ['name' => '合同义务', 'count' => 0, 'sum' => 0, 'max' => 0]
        ];
        
        if (!empty($riskData['risk_points'])) {
            foreach ($riskData['risk_points'] as $point) {
                $category = $point['category'] ?? 'legal_compliance';
                $severity = $point['severity'] ?? 0;
                
                if (isset($categories[$category])) {
                    $categories[$category]['count']++;
                    $categories[$category]['sum'] += $severity;
                    $categories[$category]['max'] = max($categories[$category]['max'], $severity);
                }
            }
        }
        
        foreach ($categories as $category) {
            $avgRisk = $category['count'] > 0 ? $category['sum'] / $category['count'] : 0;
            
            $statsTable->addRow();
            $statsTable->addCell(2500)->addText($category['name']);
            $statsTable->addCell(2500)->addText($category['count']);
            $statsTable->addCell(2500)->addText(number_format($avgRisk, 2));
            $statsTable->addCell(2500)->addText(number_format($category['max'], 2));
        }
        
        $section->addTextBreak(2);
        
        // 添加免责声明
        $section->addText('免责声明：本报告仅供参考，不构成法律建议。具体法律问题请咨询专业律师。', 
            ['italic' => true, 'size' => 8, 'color' => '999999'], 
            ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
        
        // 保存文档到内存
        $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $temp_file = tempnam(sys_get_temp_dir(), 'contract_risk_report');
        $objWriter->save($temp_file);
        
        // 读取文件内容并返回
        $content = file_get_contents($temp_file);
        unlink($temp_file); // 删除临时文件
        
        return $content;
    }
    
    /**
     * 生成HTML格式的风险分析报告
     * 
     * @param array $riskData 风险数据
     * @param bool $includeGraph 是否包含知识图谱
     * @return string 报告内容
     */
    protected function generateHtmlReport(array $riskData, bool $includeGraph): string
    {
        // 使用视图生成HTML报告
        return view('legal-knowledge.contract-risk-analysis.report', [
            'risk_data' => $riskData,
            'include_graph' => $includeGraph,
            'generated_at' => date('Y-m-d H:i:s'),
            'company_name' => '广西港妙科技有限公司'
        ])->render();
    }
}