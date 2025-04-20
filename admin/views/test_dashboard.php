<!DOCTYPE html>
<html>
<head>
    <title>系统测试仪表盘</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between mb-4">
            <h2>系统测试仪表盘</h2>
            <div>
                <button class="btn btn-primary" onclick="exportReport('pdf')">导出PDF报告</button>
                <button class="btn btn-success" onclick="exportReport('excel')">导出Excel</button>
            </div>
        </div>

        <!-- 性能指标卡片 -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">响应时间</h5>
                        <div class="metric" id="responseTime"></div>
                    </div>
                </div>
            </div>
            <!-- ...other metric cards... -->
        </div>

        <!-- 阈值设置表单 -->
        <div class="card mb-4">
            <div class="card-header">告警阈值设置</div>
            <div class="card-body">
                <form id="thresholdForm">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label>响应时间警告阈值(ms)</label>
                                <input type="number" class="form-control" name="response_time_warning">
                            </div>
                        </div>
                        <!-- ...other threshold inputs... -->
                    </div>
                    <button type="submit" class="btn btn-primary">保存设置</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // 实时更新指标
        function updateMetrics() {
            fetch('/api/metrics')
                .then(r => r.json())
                .then(updateDashboard);
        }

        // 导出报告
        function exportReport(format) {
            window.location.href = `/api/report/export?format=${format}`;
        }

        // 更新阈值设置
        document.getElementById('thresholdForm').onsubmit = async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            await fetch('/api/thresholds/update', {
                method: 'POST',
                body: formData
            });
            alert('设置已更新');
        };

        // 初始化页面
        updateMetrics();
        setInterval(updateMetrics, 30000);
    </script>
</body>
</html>
