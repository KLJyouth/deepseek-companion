<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>合同风险分析 - 司单服Ai智能安全法务</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/data-visualization.css">
    <style>
        .risk-analysis-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .input-section {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }
        
        .result-section {
            display: none;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }
        
        .risk-score-container {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .risk-score-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: bold;
            color: white;
            position: relative;
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
            margin-bottom: 15px;
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
            margin-bottom: 5px;
        }
        
        .risk-point-description {
            color: #555;
            margin-bottom: 10px;
        }
        
        .risk-point-recommendation {
            font-style: italic;
            color: #3f51b5;
        }
        
        .graph-container {
            height: 500px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-top: 20px;
        }
        
        .export-options {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .export-btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            background-color: #3f51b5;
            color: white;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .export-btn:hover {
            background-color: #303f9f;
        }
        
        textarea {
            width: 100%;
            min-height: 300px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            resize: vertical;
            font-family: inherit;
        }
        
        .options-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 15px;
        }
        
        .option-group {
            flex: 1;
            min-width: 200px;
        }
        
        .option-title {
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .checkbox-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .range-container {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .range-value {
            text-align: right;
            font-weight: bold;
        }
        
        .submit-btn {
            padding: 12px 24px;
            background-color: #3f51b5;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
            margin-top: 20px;
        }
        
        .submit-btn:hover {
            background-color: #303f9f;
        }
        
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }
        
        .spinner {
            border: 4px solid rgba(0, 0, 0, 0.1);
            border-radius: 50%;
            border-top: 4px solid #3f51b5;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <?php include_once('../../navbar.php'); ?>
    
    <div class="risk-analysis-container">
        <h1>合同风险分析</h1>
        <p>上传合同文本进行智能风险分析，识别潜在法律风险点并提供专业建议。</p>
        
        <div class="input-section">
            <h2>输入合同文本</h2>
            <textarea id="contract-text" placeholder="请粘贴合同文本内容..."></textarea>
            
            <div class="options-container">
                <div class="option-group">
                    <div class="option-title">风险类别</div>
                    <div class="checkbox-group">
                        <label>
                            <input type="checkbox" name="risk_categories[]" value="legal_compliance" checked> 法律合规性
                        </label>
                        <label>
                            <input type="checkbox" name="risk_categories[]" value="financial_risk" checked> 财务风险
                        </label>
                        <label>
                            <input type="checkbox" name="risk_categories[]" value="operational_risk" checked> 运营风险
                        </label>
                        <label>
                            <input type="checkbox" name="risk_categories[]" value="contractual_obligations" checked> 合同义务
                        </label>
                    </div>
                </div>
                
                <div class="option-group">
                    <div class="option-title">分析选项</div>
                    <div class="checkbox-group">
                        <label>
                            <input type="checkbox" name="detailed_analysis" checked> 详细分析
                        </label>
                        <label>
                            <input type="checkbox" name="include_graph" checked> 包含知识图谱
                        </label>
                    </div>
                    
                    <div class="range-container">
                        <label for="risk-threshold">风险阈值</label>
                        <input type="range" id="risk-threshold" name="risk_threshold" min="0" max="1" step="0.1" value="0.7">
                        <div class="range-value">0.7</div>
                    </div>
                </div>
            </div>
            
            <button class="submit-btn" id="analyze-btn">分析合同风险</button>
        </div>
        
        <div class="loading" id="loading-section">
            <div class="spinner"></div>
            <p>正在分析合同风险，请稍候...</p>
        </div>
        
        <div class="result-section" id="result-section">
            <h2>风险分析结果</h2>
            
            <div class="risk-score-container">
                <div class="risk-score-circle" id="risk-score">
                    0.0
                </div>
                
                <div class="risk-details">
                    <h3 id="risk-level-text">风险等级: 未知</h3>
                    <p id="risk-summary">请先分析合同以获取风险评估。</p>
                </div>
            </div>
            
            <h3>风险点详情</h3>
            <div id="risk-points-container"></div>
            
            <div id="graph-section" style="display: none;">
                <h3>合同知识图谱</h3>
                <div class="graph-container" id="graph-container"></div>
            </div>
            
            <div class="export-options">
                <button class="export-btn" data-format="pdf">导出PDF报告</button>
                <button class="export-btn" data-format="docx">导出Word报告</button>
                <button class="export-btn" data-format="html">导出HTML报告</button>
            </div>
        </div>
    </div>
    
    <!-- 引入必要的JS库 -->
    <script src="https://cdn.jsdelivr.net/npm/echarts@5.4.0/dist/echarts.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 获取DOM元素
            const contractText = document.getElementById('contract-text');
            const analyzeBtn = document.getElementById('analyze-btn');
            const loadingSection = document.getElementById('loading-section');
            const resultSection = document.getElementById('result-section');
            const riskScore = document.getElementById('risk-score');
            const riskLevelText = document.getElementById('risk-level-text');
            const riskSummary = document.getElementById('risk-summary');
            const riskPointsContainer = document.getElementById('risk-points-container');
            const graphSection = document.getElementById('graph-section');
            const graphContainer = document.getElementById('graph-container');
            const riskThreshold = document.getElementById('risk-threshold');
            const riskThresholdValue = document.querySelector('.range-value');
            const exportBtns = document.querySelectorAll('.export-btn');
            
            // 更新风险阈值显示
            riskThreshold.addEventListener('input', function() {
                riskThresholdValue.textContent = this.value;
            });
            
            // 分析按钮点击事件
            analyzeBtn.addEventListener('click', function() {
                // 验证输入
                if (contractText.value.trim().length < 10) {
                    alert('请输入至少10个字符的合同文本');
                    return;
                }
                
                // 显示加载中
                loadingSection.style.display = 'block';
                resultSection.style.display = 'none';
                
                // 获取选中的风险类别
                const riskCategories = [];
                document.querySelectorAll('input[name="risk_categories[]"]:checked').forEach(function(checkbox) {
                    riskCategories.push(checkbox.value);
                });
                
                // 准备请求数据
                const requestData = {
                    contract_text: contractText.value,
                    risk_threshold: parseFloat(riskThreshold.value),
                    include_graph: document.querySelector('input[name="include_graph"]').checked,
                    detailed_analysis: document.querySelector('input[name="detailed_analysis"]').checked,
                    risk_categories: riskCategories
                };
                
                // 发送AJAX请求
                fetch('/contract-risk-analysis/analyze', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify(requestData)
                })
                .then(response => response.json())
                .then(data => {
                    // 隐藏加载中
                    loadingSection.style.display = 'none';
                    
                    if (data.success) {
                        // 显示结果
                        resultSection.style.display = 'block';
                        
                        // 更新风险分数和等级
                        const score = data.data.overall_risk_score;
                        riskScore.textContent = score.toFixed(2);
                        
                        // 设置风险等级样式
                        riskScore.className = 'risk-score-circle';
                        if (score < 0.4) {
                            riskScore.classList.add('risk-level-low');
                            riskLevelText.textContent = '风险等级: 低';
                        } else if (score < 0.7) {
                            riskScore.classList.add('risk-level-medium');
                            riskLevelText.textContent = '风险等级: 中';
                        } else {
                            riskScore.classList.add('risk-level-high');
                            riskLevelText.textContent = '风险等级: 高';
                        }
                        
                        // 更新风险摘要
                        riskSummary.textContent = data.data.summary || '未提供风险摘要';
                        
                        // 渲染风险点
                        riskPointsContainer.innerHTML = '';
                        if (data.data.risk_points && data.data.risk_points.length > 0) {
                            data.data.risk_points.forEach(point => {
                                const riskClass = point.severity >= 0.7 ? 'risk-point-high' : 
                                                 point.severity >= 0.4 ? 'risk-point-medium' : 'risk-point-low';
                                
                                const pointElement = document.createElement('div');
                                pointElement.className = `risk-point ${riskClass}`;
                                pointElement.innerHTML = `
                                    <div class="risk-point-title">${point.title}</div>
                                    <div class="risk-point-description">${point.description}</div>
                                    <div class="risk-point-recommendation">建议: ${point.recommendation}</div>
                                `;
                                
                                riskPointsContainer.appendChild(pointElement);
                            });
                        } else {
                            riskPointsContainer.innerHTML = '<p>未发现风险点</p>';
                        }
                        
                        // 处理知识图谱
                        if (data.data.graph_data && requestData.include_graph) {
                            graphSection.style.display = 'block';
                            
                            // 使用图谱可视化服务渲染图谱
                            renderGraph(data.data.graph_data);
                        } else {
                            graphSection.style.display = 'none';
                        }
                    } else {
                        alert('分析失败: ' + data.message);
                    }
                })
                .catch(error => {
                    loadingSection.style.display = 'none';
                    alert('请求错误: ' + error.message);
                });
            });
            
            // 导出报告按钮点击事件
            exportBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const format = this.getAttribute('data-format');
                    const includeGraph = document.querySelector('input[name="include_graph"]').checked;
                    
                    // 获取风险数据
                    const riskData = {
                        overall_risk_score: parseFloat(riskScore.textContent),
                        risk_level: riskLevelText.textContent.replace('风险等级: ', ''),
                        summary: riskSummary.textContent,
                        risk_points: []
                    };
                    
                    // 收集风险点数据
                    document.querySelectorAll('.risk-point').forEach(point => {
                        riskData.risk_points.push({
                            title: point.querySelector('.risk-point-title').textContent,
                            description: point.querySelector('.risk-point-description').textContent,
                            recommendation: point.querySelector('.risk-point-recommendation').textContent.replace('建议: ', '')
                        });
                    });
                    
                    // 创建表单并提交
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '/contract-risk-analysis/export-report';
                    form.target = '_blank';
                    
                    // 添加CSRF令牌
                    const csrfToken = document.createElement('input');
                    csrfToken.type = 'hidden';
                    csrfToken.name = '_token';
                    csrfToken.value = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                    form.appendChild(csrfToken);
                    
                    // 添加风险数据
                    const riskDataInput = document.createElement('input');
                    riskDataInput.type = 'hidden';
                    riskDataInput.name = 'risk_data';
                    riskDataInput.value = JSON.stringify(riskData);
                    form.appendChild(riskDataInput);
                    
                    // 添加格式
                    const formatInput = document.createElement('input');
                    formatInput.type = 'hidden';
                    formatInput.name = 'format';
                    formatInput.value = format;
                    form.appendChild(formatInput);
                    
                    // 添加是否包含图谱
                    const includeGraphInput = document.createElement('input');
                    includeGraphInput.type = 'hidden';
                    includeGraphInput.name = 'include_graph';
                    includeGraphInput.value = includeGraph ? '1' : '0';
                    form.appendChild(includeGraphInput);
                    
                    // 提交表单
                    document.body.appendChild(form);
                    form.submit();
                    document.body.removeChild(form);
                });
            });
            
            // 渲染知识图谱
            function renderGraph(graphData) {
                // 初始化ECharts实例
                const chart = echarts.init(graphContainer);
                
                // 准备图表选项
                const option = {
                    title: {
                        text: '合同风险知识图谱',
                        subtext: '基于图谱分析的风险关联',
                        top: 'top',
                        left: 'center'
                    },
                    tooltip: {
                        trigger: 'item',
                        formatter: function(params) {
                            if (params.dataType === 'node') {
                                return `<strong>${params.data.name}</strong><br/>类型: ${params.data.category}`;
                            } else {
                                return `<strong>${params.data.source} → ${params.data.target}</strong><br/>关系: ${params.data.name}`;
                            }
                        }
                    },
                    legend: {
                        data: ['条款', '实体', '义务', '权利', '风险'],
                        orient: 'vertical',
                        left: 'left'
                    },
                    animationDuration: 1500,
                    animationEasingUpdate: 'quinticInOut',
                    series: [{
                        name: '合同风险知识图谱',
                        type: 'graph',
                        layout: 'force',
                        data: graphData.nodes,
                        links: graphData.links,
                        categories: [
                            { name: '条款' },
                            { name: '实体' },
                            { name: '义务' },
                            { name: '权利' },
                            { name: '风险' }
                        ],
                        roam: true,
                        label: {
                            show: true,
                            position: 'right',
                            formatter: '{b}'
                        },
                        lineStyle: {
                            color: 'source',
                            curveness: 0.3
                        },
                        emphasis: {
                            focus: 'adjacency',
                            lineStyle: {
                                width: 4
                            }
                        },
                        force: {
                            repulsion: 100
                        }
                    }]
                };
                
                // 设置图表选项并渲染
                chart.setOption(option);
                
                // 响应窗口大小变化
                window.addEventListener('resize', function() {
                    chart.resize();
                });
            }
        });
    </script>
</body>
</html>