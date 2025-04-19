<?php
require_once __DIR__.'/../libs/Bootstrap.php';
require_once __DIR__.'/../controllers/MonitorController.php';

// 验证管理员权限
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// 默认查询最近1小时数据
$start = isset($_GET['start']) ? strtotime($_GET['start']) : time() - 3600;
$end = isset($_GET['end']) ? strtotime($_GET['end']) : time();

// 获取监控数据
$monitor = new MonitorController();
$metrics = $monitor->metrics();

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>历史监控数据</title>
    <link href="/css/admin.css" rel="stylesheet">
    <script src="/js/chart.min.js"></script>
</head>
<body>
    <?php include __DIR__.'/../navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__.'/../sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">历史监控数据</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button class="btn btn-sm btn-outline-secondary" onclick="refreshData()">
                                <i class="bi bi-arrow-repeat"></i> 刷新
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- 时间范围选择 -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form id="dateRangeForm" class="row g-3">
                            <div class="col-md-5">
                                <label for="startDate" class="form-label">开始时间</label>
                                <input type="datetime-local" class="form-control" id="startDate" 
                                    value="<?= date('Y-m-d\TH:i', $start) ?>">
                            </div>
                            <div class="col-md-5">
                                <label for="endDate" class="form-label">结束时间</label>
                                <input type="datetime-local" class="form-control" id="endDate" 
                                    value="<?= date('Y-m-d\TH:i', $end) ?>">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">查询</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- 监控图表 -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header">CPU使用率</div>
                            <div class="card-body">
                                <canvas id="cpuChart" height="250"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header">内存使用率</div>
                            <div class="card-body">
                                <canvas id="memoryChart" height="250"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header">并发请求数</div>
                            <div class="card-body">
                                <canvas id="concurrentChart" height="250"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header">请求吞吐量</div>
                            <div class="card-body">
                                <canvas id="throughputChart" height="250"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
    // 初始化图表
    const charts = {};
    ['cpu', 'memory', 'concurrent', 'throughput'].forEach(metric => {
        const ctx = document.getElementById(`${metric}Chart`).getContext('2d');
        charts[metric] = new Chart(ctx, {
            type: 'line',
            data: JSON.parse('<?= json_encode($metrics[$metric] ?? []) ?>'),
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    });

    // 处理表单提交
    document.getElementById('dateRangeForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const start = document.getElementById('startDate').value;
        const end = document.getElementById('endDate').value;
        window.location.href = `?start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}`;
    });

    // 刷新数据
    function refreshData() {
        window.location.reload();
    }
    </script>
</body>
</html>