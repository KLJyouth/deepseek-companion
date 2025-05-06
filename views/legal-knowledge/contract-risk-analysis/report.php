<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>合同风险分析报告 - 司单服Ai智能安全法务</title>
    <style>
        body {
            font-family: 'Microsoft YaHei', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f9f9f9;
        }
        
        .report-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
            background-color: #fff;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        
        .report-header {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 2px solid #3f51b5;
            margin-bottom: 30px;
        }
        
        .report-title {
            font-size: 24px;
            font-weight: bold;
            color: #3f51b5;
            margin-bottom: 10px;
        }
        
        .report-subtitle {
            font-size: 16px;
            color: #666;
        }
        
        .report-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            color: #666;
            font-size: 14px;
        }
        
        .report-section {
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 20px;
            font-weight: bold;
            color: #3f51b5;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
            margin-bottom: 15px;
        }
        
        .risk-summary {
            background-color: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .risk-score-container {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .risk-score-circle {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: bold;
            color: white;
        }
        
        .risk-level-low {
            background-color: #4caf50;
        }
        
        .risk-level-medium {
            background-color: #ff9800;
        }
        
        .risk-level-high {
            background-color: #f44336;
        }
        
        .risk-details {
            flex: 1;
        }
        
        .risk-point {
            margin-bottom: 20px;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #3f51b5;
            background-color: #f5f5f5;
        }
        
        .risk-point-high {
            border-left-color: #f44336;
        }
        
        .risk-point-medium {
            border-left-color: #ff9800;
        }
        
        .risk-point-low {
            border-left-color: #4caf50;
        }
        
        .risk-point-title {
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .risk-point-description {
            margin-bottom: 10px;
        }
        
        .risk-point-recommendation {
            font-style: italic;
            color: #3f51b5;
        }
        
        .graph-container {
            margin-top: 20px;
            text-align: center;
        }
        
        .graph-container img {
            max-width: 100%;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 14px;
            color: #666;
        }
        
        .disclaimer {
            font-size: 12px;
            color: #999;
            font-style: italic;
            margin-top: 10px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        
        @media print {
            body {
                background-color: #fff;
            }
            
            .report-container {
                box-shadow: none;
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="report-container">
        <div class="report-header">
            <div class="report-title">合同风险分析报告</div>
            <div class="report-subtitle">司单服Ai智能安全法务</div>
        </div>
        
        <div class="report-meta">
            <div>生成时间: <?php echo $generated_at; ?></div>
            <div>生成单位: <?php echo $company_name; ?></div>
        </div>
        
        <div class="report-section">
            <div class="section-title">风险评估摘要</div>
            <div class="risk-score-container">
                <?php 
                $score = $risk_data['overall_risk_score'] ?? 0;
                $riskLevelClass = $score < 0.4 ? 'risk-level-low' : ($score < 0.7 ? 'risk-level-medium' : 'risk-level-high');
                $riskLevelText = $score < 0.4 ? '低' : ($score < 0.7 ? '中' : '高');
                ?>
                
                <div class="risk-score-circle <?php echo $riskLevelClass; ?>">
                    <?php echo number_format($score, 2); ?>
                </div>
                
                <div class="risk-details">
                    <h3>风险等级: <?php echo $riskLevelText; ?></h3>
                    <div class="risk-summary">
                        <?php echo $risk_data['summary'] ?? '未提供风险摘要'; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="report-section">
            <div class="section-title">风险点详情</div>
            
            <?php if (!empty($risk_data['risk_points'])): ?>
                <?php foreach ($risk_data['risk_points'] as $index => $point): ?>
                    <?php 
                    $severity = $point['severity'] ?? 0;
                    $riskClass = $severity >= 0.7 ? 'risk-point-high' : ($severity >= 0.4 ? 'risk-point-medium' : 'risk-point-low');
                    ?>
                    <div class="risk-point <?php echo $riskClass; ?>">
                        <div class="risk-point-title"><?php echo ($index + 1) . '. ' . $point['title']; ?></div>
                        <div class="risk-point-description"><?php echo $point['description']; ?></div>
                        <div class="risk-point-recommendation">建议: <?php echo $point['recommendation']; ?></div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>未发现风险点</p>
            <?php endif; ?>
        </div>
        
        <?php if ($include_graph && !empty($risk_data['graph_data'])): ?>
        <div class="report-section">
            <div class="section-title">合同知识图谱</div>
            <div class="graph-container">
                <?php if (!empty($risk_data['graph_image'])): ?>
                    <img src="<?php echo $risk_data['graph_image']; ?>" alt="合同知识图谱">
                <?php else: ?>
                    <p>知识图谱数据可用，但图像未生成。请在在线版本中查看交互式图谱。</p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="report-section">
            <div class="section-title">风险分布统计</div>
            <table>
                <thead>
                    <tr>
                        <th>风险类别</th>
                        <th>风险点数量</th>
                        <th>平均风险值</th>
                        <th>最高风险值</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    // 计算风险分布统计
                    $categories = [
                        'legal_compliance' => ['name' => '法律合规性', 'count' => 0, 'sum' => 0, 'max' => 0],
                        'financial_risk' => ['name' => '财务风险', 'count' => 0, 'sum' => 0, 'max' => 0],
                        'operational_risk' => ['name' => '运营风险', 'count' => 0, 'sum' => 0, 'max' => 0],
                        'contractual_obligations' => ['name' => '合同义务', 'count' => 0, 'sum' => 0, 'max' => 0]
                    ];
                    
                    if (!empty($risk_data['risk_points'])) {
                        foreach ($risk_data['risk_points'] as $point) {
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
                        echo '<tr>';
                        echo '<td>' . $category['name'] . '</td>';
                        echo '<td>' . $category['count'] . '</td>';
                        echo '<td>' . number_format($avgRisk, 2) . '</td>';
                        echo '<td>' . number_format($category['max'], 2) . '</td>';
                        echo '</tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
        
        <div class="footer">
            <p>本报告由司单服Ai智能安全法务系统自动生成</p>
            <p>版权所有 © <?php echo date('Y'); ?> 广西港妙科技有限公司</p>
            <div class="disclaimer">
                免责声明：本报告仅供参考，不构成法律建议。具体法律问题请咨询专业律师。
            </div>
        </div>
    </div>
</body>
</html>