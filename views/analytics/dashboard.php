<!DOCTYPE html>
<html>
<head>
    <title>性能分析仪表板</title>
    <link href="https://cdn.jsdelivr.net/npm/echarts@5/dist/echarts.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/echarts@5/dist/echarts.min.js"></script>
</head>
<body>
    <div class="dashboard">
        <div id="performanceChart" style="height:400px;"></div>
        <div id="alertTrends" style="height:400px;"></div>
        <div id="predictionAnalysis" style="height:400px;"></div>
    </div>
    
    <script>
    const renderCharts = (data) => {
        // 性能指标图表
        const perfChart = echarts.init(document.getElementById('performanceChart'));
        perfChart.setOption({
            title: { text: '系统性能趋势' },
            tooltip: { trigger: 'axis' },
            xAxis: { type: 'time' },
            yAxis: { type: 'value' },
            series: [
                {
                    name: 'CPU使用率',
                    type: 'line',
                    data: data.performance.cpu
                },
                {
                    name: '内存使用率',
                    type: 'line',
                    data: data.performance.memory
                }
            ]
        });
        
        // 告警趋势图表
        const alertChart = echarts.init(document.getElementById('alertTrends'));
        alertChart.setOption({
            title: { text: '告警分布分析' },
            series: [{
                type: 'sunburst',
                data: data.alerts,
                emphasis: {
                    focus: 'ancestor'
                }
            }]
        });
    };

    // 加载数据并渲染图表
    fetch('/analytics/metrics')
        .then(r => r.json())
        .then(renderCharts);
    </script>
</body>
</html>
