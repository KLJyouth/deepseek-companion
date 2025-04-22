<!DOCTYPE html>
<html>
<head>
    <title>性能分析仪表板</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/echarts@5/dist/echarts.min.css" rel="stylesheet">
    <link href="/css/data-visualization.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/echarts@5/dist/echarts.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
</head>
<body>
    <div class="container-fluid dashboard-container">
        <header class="dashboard-header">
            <h1>系统性能分析仪表板</h1>
            <div class="dashboard-actions">
                <button id="exportBtn" class="btn btn-primary">导出报告</button>
                <select id="timeRange" class="form-select">
                    <option value="7">最近7天</option>
                    <option value="30" selected>最近30天</option>
                    <option value="90">最近90天</option>
                </select>
            </div>
        </header>
        
        <div class="row dashboard-row">
            <div class="col-md-12">
                <div class="card dashboard-card">
                    <div class="card-header">系统性能趋势</div>
                    <div class="card-body">
                        <div id="performanceChart" class="chart-container"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row dashboard-row">
            <div class="col-md-6">
                <div class="card dashboard-card">
                    <div class="card-header">告警趋势分析</div>
                    <div class="card-body">
                        <div id="alertTrends" class="chart-container"></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card dashboard-card">
                    <div class="card-header">性能预测分析</div>
                    <div class="card-body">
                        <div id="predictionAnalysis" class="chart-container"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row dashboard-row">
            <div class="col-md-12">
                <div class="card dashboard-card">
                    <div class="card-header">异常检测</div>
                    <div class="card-body">
                        <div id="anomalyDetection" class="chart-container"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    /**
     * 渲染所有图表
     * @param {Object} data - 从API获取的指标数据
     */
    const renderCharts = (data) => {
        renderPerformanceChart(data.performance);
        renderAlertChart(data.alerts);
        renderPredictionChart(data.predictions);
        renderAnomalyChart(data.predictions.anomalies);
    };
    
    /**
     * 渲染性能指标图表
     * @param {Object} performanceData - 性能指标数据
     */
    const renderPerformanceChart = (performanceData) => {
        const chart = echarts.init(document.getElementById('performanceChart'));
        chart.setOption({
            title: { text: '系统资源使用趋势' },
            tooltip: { 
                trigger: 'axis',
                formatter: function(params) {
                    let result = moment(params[0].value[0]).format('YYYY-MM-DD') + '<br/>';
                    params.forEach(param => {
                        result += param.marker + ' ' + param.seriesName + ': ' + param.value[1] + '%<br/>';
                    });
                    return result;
                }
            },
            legend: { data: ['CPU使用率', '内存使用率'] },
            grid: { left: '3%', right: '4%', bottom: '3%', containLabel: true },
            xAxis: { 
                type: 'time',
                axisLabel: {
                    formatter: function(value) {
                        return moment(value).format('MM-DD');
                    }
                }
            },
            yAxis: { 
                type: 'value',
                min: 0,
                max: 100,
                axisLabel: {
                    formatter: '{value}%'
                }
            },
            series: [
                {
                    name: 'CPU使用率',
                    type: 'line',
                    data: performanceData.cpu,
                    smooth: true,
                    lineStyle: { width: 3 },
                    areaStyle: {
                        opacity: 0.2
                    }
                },
                {
                    name: '内存使用率',
                    type: 'line',
                    data: performanceData.memory,
                    smooth: true,
                    lineStyle: { width: 3 },
                    areaStyle: {
                        opacity: 0.2
                    }
                }
            ]
        });
        
        window.addEventListener('resize', () => chart.resize());
    };
    
    /**
     * 渲染告警趋势图表
     * @param {Object} alertData - 告警数据
     */
    const renderAlertChart = (alertData) => {
        const chart = echarts.init(document.getElementById('alertTrends'));
        chart.setOption({
            title: { text: '告警分布趋势' },
            tooltip: { 
                trigger: 'axis',
                formatter: function(params) {
                    let result = moment(params[0].value[0]).format('YYYY-MM-DD') + '<br/>';
                    params.forEach(param => {
                        result += param.marker + ' ' + param.seriesName + ': ' + param.value[1] + '次<br/>';
                    });
                    return result;
                }
            },
            legend: { data: ['严重告警', '警告告警', '信息告警'] },
            grid: { left: '3%', right: '4%', bottom: '3%', containLabel: true },
            xAxis: { 
                type: 'time',
                axisLabel: {
                    formatter: function(value) {
                        return moment(value).format('MM-DD');
                    }
                }
            },
            yAxis: { type: 'value' },
            series: [
                {
                    name: '严重告警',
                    type: 'bar',
                    stack: 'total',
                    data: alertData.critical,
                    itemStyle: { color: '#ff4d4f' }
                },
                {
                    name: '警告告警',
                    type: 'bar',
                    stack: 'total',
                    data: alertData.warning,
                    itemStyle: { color: '#faad14' }
                },
                {
                    name: '信息告警',
                    type: 'bar',
                    stack: 'total',
                    data: alertData.info,
                    itemStyle: { color: '#1890ff' }
                }
            ]
        });
        
        window.addEventListener('resize', () => chart.resize());
    };
    
    /**
     * 渲染预测分析图表
     * @param {Object} predictionData - 预测数据
     */
    const renderPredictionChart = (predictionData) => {
        const chart = echarts.init(document.getElementById('predictionAnalysis'));
        
        // 提取实际数据和预测数据
        const actualData = predictionData.trends?.cpu || [];
        const predictedData = predictionData.predictions?.cpu || [];
        
        // 合并数据用于图表显示
        const allData = [...actualData];
        if (predictedData.length > 0) {
            // 确保预测数据从最后一个实际数据点开始
            allData.push(...predictedData);
        }
        
        chart.setOption({
            title: { text: 'CPU使用率预测' },
            tooltip: {
                trigger: 'axis',
                formatter: function(params) {
                    let result = moment(params[0].value[0]).format('YYYY-MM-DD') + '<br/>';
                    params.forEach(param => {
                        result += param.marker + ' ' + param.seriesName + ': ' + param.value[1] + '%<br/>';
                    });
                    return result;
                }
            },
            legend: { data: ['历史数据', '预测数据'] },
            grid: { left: '3%', right: '4%', bottom: '3%', containLabel: true },
            xAxis: { 
                type: 'time',
                axisLabel: {
                    formatter: function(value) {
                        return moment(value).format('MM-DD');
                    }
                }
            },
            yAxis: { 
                type: 'value',
                axisLabel: {
                    formatter: '{value}%'
                }
            },
            series: [
                {
                    name: '历史数据',
                    type: 'line',
                    data: actualData,
                    smooth: true,
                    lineStyle: { width: 3 }
                },
                {
                    name: '预测数据',
                    type: 'line',
                    data: predictedData,
                    smooth: true,
                    lineStyle: { 
                        width: 3,
                        type: 'dashed'
                    }
                }
            ]
        });
        
        window.addEventListener('resize', () => chart.resize());
    };
    
    /**
     * 渲染异常检测图表
     * @param {Array} anomalyData - 异常检测数据
     */
    const renderAnomalyChart = (anomalyData) => {
        const chart = echarts.init(document.getElementById('anomalyDetection'));
        
        // 准备数据
        const data = anomalyData || [];
        
        chart.setOption({
            title: { text: '系统异常检测' },
            tooltip: { trigger: 'axis' },
            legend: { data: ['正常值', '异常值'] },
            grid: { left: '3%', right: '4%', bottom: '3%', containLabel: true },
            xAxis: { 
                type: 'time',
                axisLabel: {
                    formatter: function(value) {
                        return moment(value).format('MM-DD');
                    }
                }
            },
            yAxis: { type: 'value' },
            series: [
                {
                    name: '正常值',
                    type: 'scatter',
                    symbolSize: 8,
                    data: data.filter(item => !item.is_anomaly).map(item => [item.date, item.value]),
                    itemStyle: { color: '#52c41a' }
                },
                {
                    name: '异常值',
                    type: 'scatter',
                    symbolSize: 12,
                    data: data.filter(item => item.is_anomaly).map(item => [item.date, item.value]),
                    itemStyle: { color: '#ff4d4f' }
                }
            ]
        });
        
        window.addEventListener('resize', () => chart.resize());
    };

    // 加载数据并渲染图表
    const loadData = (days = 30) => {
        fetch(`/analytics/metrics?days=${days}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('网络请求失败');
                }
                return response.json();
            })
            .then(data => {
                renderCharts(data);
            })
            .catch(error => {
                console.error('获取数据失败:', error);
                alert('获取数据失败，请稍后重试');
            });
    };
    
    // 初始加载数据
    document.addEventListener('DOMContentLoaded', () => {
        loadData();
        
        // 时间范围选择事件
        document.getElementById('timeRange').addEventListener('change', (e) => {
            loadData(e.target.value);
        });
        
        // 导出报告事件
        document.getElementById('exportBtn').addEventListener('click', () => {
            fetch('/analytics/export-report', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    days: document.getElementById('timeRange').value
                })
            })
            .then(response => {
                if (response.ok) {
                    return response.blob();
                }
                throw new Error('导出失败');
            })
            .then(blob => {
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `analytics_report_${moment().format('YYYYMMDD_HHmmss')}.pdf`;
                a.click();
            })
            .catch(error => {
                console.error('导出报告失败:', error);
                alert('导出报告失败，请稍后重试');
            });
        });
    });
    </script>
</body>
</html>
