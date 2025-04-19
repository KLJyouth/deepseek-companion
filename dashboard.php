<?php
// 检查权限
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// 获取统计数据
require_once 'config.php';
$stats = getDashboardStats();
$recentActivities = getRecentActivities();
$apiUsage = getApiUsageData();
?>
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">系统概览</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-sm btn-outline-secondary">导出</button>
            <button type="button" class="btn btn-sm btn-outline-secondary">打印</button>
        </div>
        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#refreshModal">
            <i class="bi bi-arrow-repeat"></i> 刷新数据
        </button>
    </div>
</div>

<!-- 统计卡片 -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-white bg-primary mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title">总用户数</h5>
                        <h2 class="card-text"><?php echo number_format($stats['total_users']); ?></h2>
                    </div>
                    <i class="bi bi-people display-4 opacity-50"></i>
                </div>
                <small class="opacity-75">较上月增长 <?php echo $stats['user_growth']; ?>%</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-success mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title">活跃用户</h5>
                        <h2 class="card-text"><?php echo number_format($stats['active_users']); ?></h2>
                    </div>
                    <i class="bi bi-activity display-4 opacity-50"></i>
                </div>
                <small class="opacity-75">过去7天活跃</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-info mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title">在线用户</h5>
                        <h2 class="card-text"><?php echo number_format($stats['online_users']); ?></h2>
                    </div>
                    <i class="bi bi-circle-fill display-4 opacity-50"></i>
                </div>
                <small class="opacity-75">实时在线</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-warning mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title">API调用</h5>
                        <h2 class="card-text"><?php echo number_format($stats['api_calls']); ?></h2>
                    </div>
                    <i class="bi bi-plug display-4 opacity-50"></i>
                </div>
                <small class="opacity-75">本月总量</small>
            </div>
        </div>
    </div>
</div>

<!-- 图表区 -->
<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5>API调用统计</h5>
            </div>
            <div class="card-body">
                <canvas id="apiChart" height="300"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5>系统状态</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span>CPU使用率</span>
                        <span><?php echo $stats['cpu_usage']; ?>%</span>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $stats['cpu_usage']; ?>%"></div>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span>内存使用</span>
                        <span><?php echo $stats['memory_usage']; ?>%</span>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar bg-info" role="progressbar" style="width: <?php echo $stats['memory_usage']; ?>%"></div>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span>存储空间</span>
                        <span><?php echo $stats['storage_usage']; ?>%</span>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar bg-warning" role="progressbar" style="width: <?php echo $stats['storage_usage']; ?>%"></div>
                    </div>
                </div>
                <div class="alert alert-<?php echo $stats['system_status'] === 'normal' ? 'success' : 'danger'; ?> mb-0">
                    <i class="bi bi-<?php echo $stats['system_status'] === 'normal' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                    系统状态: <?php echo ucfirst($stats['system_status']); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 最近活动 -->
<div class="card">
    <div class="card-header">
        <h5>最近活动</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th width="120">时间</th>
                        <th>用户</th>
                        <th>活动</th>
                        <th width="100">状态</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentActivities as $activity): ?>
                    <tr>
                        <td><?php echo date('m-d H:i', strtotime($activity['time'])); ?></td>
                        <td><?php echo htmlspecialchars($activity['user']); ?></td>
                        <td><?php echo htmlspecialchars($activity['action']); ?></td>
                        <td>
                            <span class="badge bg-<?php echo $activity['status'] === 'success' ? 'success' : 'danger'; ?>">
                                <?php echo ucfirst($activity['status']); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 刷新数据模态框 -->
<div class="modal fade" id="refreshModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">刷新数据</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>确定要刷新仪表盘数据吗？这将从服务器获取最新数据。</p>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="clearCache">
                    <label class="form-check-label" for="clearCache">同时清除缓存</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                <button type="button" class="btn btn-primary" id="confirmRefresh">确认刷新</button>
            </div>
        </div>
    </div>
</div>

<script>
// 初始化API调用图表
function initApiUsageChart(data) {
    const ctx = document.getElementById('apiChart').getContext('2d');
    const chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.map(item => item.month),
            datasets: [{
                label: 'API调用量',
                data: data.map(item => item.calls),
                backgroundColor: 'rgba(78, 115, 223, 0.2)',
                borderColor: 'rgba(78, 115, 223, 1)',
                borderWidth: 2,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

// 刷新数据
document.getElementById('confirmRefresh').addEventListener('click', function() {
    const clearCache = document.getElementById('clearCache').checked;
    fetch('api/refresh_dashboard.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            clear_cache: clearCache
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('刷新失败: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('刷新失败');
    })
    .finally(() => {
        $('#refreshModal').modal('hide');
    });
});
</script>
