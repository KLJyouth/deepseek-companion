<!DOCTYPE html>
<html>
<head>
    <title>系统监控仪表盘</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between mb-4">
            <h2>系统监控仪表盘</h2>
            <div class="btn-group">
                <button class="btn btn-primary" onclick="exportReport('pdf')">导出PDF</button>
                <button class="btn btn-success" onclick="exportReport('excel')">导出Excel</button>
            </div>
        </div>

        <!-- 性能指标卡片 -->
        <div class="row mb-4">
            <?php foreach ($metrics['performance']['data'] as $key => $value): ?>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title"><?= ucfirst($key) ?></h5>
                        <div class="metric-value"><?= $value ?></div>
                        <?php if (isset($metrics['performance']['alerts'][$key])): ?>
                            <div class="alert alert-warning mt-2">
                                <?= $metrics['performance']['alerts'][$key] ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- 阈值设置表单 -->
        <div class="card">
            <div class="card-header">
                <h5>阈值设置</h5>
            </div>
            <div class="card-body">
                <form id="thresholdForm">
                    <?php foreach ($thresholds as $category => $values): ?>
                    <div class="mb-3">
                        <h6><?= ucfirst($category) ?></h6>
                        <?php foreach ($values as $metric => $levels): ?>
                        <div class="row g-3 align-items-center mb-2">
                            <div class="col-auto">
                                <label><?= $metric ?></label>
                            </div>
                            <?php foreach ($levels as $level => $value): ?>
                            <div class="col-auto">
                                <input type="number" 
                                       class="form-control" 
                                       name="thresholds[<?= $category ?>][<?= $metric ?>][<?= $level ?>]"
                                       value="<?= $value ?>">
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
                    <button type="submit" class="btn btn-primary">保存设置</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // 导出报告
        function exportReport(format) {
            window.location.href = `/monitor/export-report?format=${format}`;
        }

        // 更新阈值设置
        document.getElementById('thresholdForm').onsubmit = async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            
            try {
                const response = await fetch('/monitor/update-thresholds', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                
                if (result.success) {
                    alert('设置已更新');
                    location.reload();
                }
            } catch (err) {
                alert('更新失败: ' + err.message);
            }
        };
    </script>
</body>
</html>
