<!DOCTYPE html>
<html>
<head>
    <title>数据库监控面板</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/moment"></script>
</head>
<body>
    <div class="container-fluid p-4">
        <!-- 新增图表类型选择器 -->
        <div class="row mb-4">
            <div class="col-md-3">
                <select class="form-select" id="chartTypeSelector">
                    <option value="line">折线图</option>
                    <option value="bar">柱状图</option>
                    <option value="radar">雷达图</option>
                    <option value="scatter">散点图</option>
                    <option value="heatmap">热力图</option>
                </select>
            </div>
            <div class="col-md-3">
                <button class="btn btn-primary" onclick="exportAnalytics()">导出分析报告</button>
            </div>
        </div>

        <!-- 顶部统计卡片 -->
        <div class="row mb-4" id="statsCards">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">活跃连接</h5>
                        <h3 id="activeConnections">-</h3>
                    </div>
                </div>
            </div>
            <!-- ... 其他统计卡片 ... -->
        </div>

        <!-- 实时监控图表 -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <canvas id="connectionChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <canvas id="performanceChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- 告警历史记录 -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">告警历史</div>
                    <div class="card-body">
                        <div id="alertHistoryChart"></div>
                        <div class="table-responsive mt-3">
                            <table class="table" id="alertHistoryTable">
                                <thead>
                                    <tr>
                                        <th>时间</th>
                                        <th>类型</th>
                                        <th>级别</th>
                                        <th>消息</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 告警规则配置 -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between">
                        <h5>告警规则配置</h5>
                        <button class="btn btn-primary" onclick="exportMetrics()">导出指标数据</button>
                    </div>
                    <div class="card-body">
                        <form id="alertRulesForm">
                            <!-- 动态生成告警规则表单 -->
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- 指标分析面板 -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">性能趋势分析</div>
                    <div class="card-body">
                        <canvas id="performanceAnalytics"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">资源使用分析</div>
                    <div class="card-body">
                        <canvas id="resourceAnalytics"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>
    <script>
    const charts = {
        connection: null,
        performance: null
    };

    // 初始化图表
    function initCharts() {
        charts.connection = new Chart(
            document.getElementById('connectionChart'),
            {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: '活跃连接数',
                        data: [],
                        borderColor: 'rgb(75, 192, 192)'
                    }]
                },
                options: {
                    responsive: true,
                    animation: false
                }
            }
        );
        // ... 初始化其他图表 ...
    }

    // 更新监控数据
    async function updateMetrics() {
        const response = await fetch('/api/monitor/metrics');
        const data = await response.json();
        
        // 更新统计卡片
        document.getElementById('activeConnections').textContent = data.active_connections;
        
        // 更新图表数据
        charts.connection.data.labels.push(moment().format('HH:mm:ss'));
        charts.connection.data.datasets[0].data.push(data.active_connections);
        
        // 保持最近30个数据点
        if (charts.connection.data.labels.length > 30) {
            charts.connection.data.labels.shift();
            charts.connection.data.datasets[0].data.shift();
        }
        
        charts.connection.update();
    }

    // 导出指标数据
    function exportMetrics() {
        fetch('/api/monitor/export', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.blob())
        .then(blob => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `metrics_${moment().format('YYYYMMDD_HHmmss')}.csv`;
            a.click();
        });
    }

    // 初始化告警历史图表
    const initAlertHistoryChart = () => {
        const chart = echarts.init(document.getElementById('alertHistoryChart'));
        
        fetch('/api/monitor/alert-history')
            .then(r => r.json())
            .then(data => {
                chart.setOption({
                    tooltip: { trigger: 'axis' },
                    legend: { data: ['严重', '警告', '信息'] },
                    xAxis: { type: 'time' },
                    yAxis: { type: 'value' },
                    series: [
                        {
                            name: '严重',
                            type: 'line',
                            data: data.critical
                        },
                        {
                            name: '警告',
                            type: 'line',
                            data: data.warning
                        },
                        {
                            name: '信息',
                            type: 'line',
                            data: data.info
                        }
                    ]
                });
            });
    };

    // 导出分析报告
    const exportAnalytics = async () => {
        const response = await fetch('/api/monitor/analytics-export');
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `performance_analytics_${new Date().toISOString()}.pdf`;
        a.click();
    };

    // 初始化页面
    document.addEventListener('DOMContentLoaded', () => {
        initCharts();
        setInterval(updateMetrics, 5000);
        initAlertHistoryChart();
        // 监听图表类型切换
        document.getElementById('chartTypeSelector').addEventListener('change', (e) => {
            updateChartType(e.target.value);
        });
    });
    </script>
</body>
</html>
