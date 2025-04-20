<?php
// 开启session
session_start();

// 错误处理
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

// 导入所需类
require_once __DIR__ . '/libs/CryptoHelper.php';
require_once __DIR__ . '/libs/ConfigHelper.php';
require_once __DIR__ . '/libs/DatabaseHelper.php';
require_once __DIR__ . '/libs/SecurityManager.php';
require_once __DIR__ . '/libs/SecurityPredictor.php';
require_once __DIR__ . '/libs/SecurityAuditHelper.php'; 
require_once __DIR__ . '/models/Contract.php';
require_once __DIR__ . '/models/ContractSignature.php';
require_once __DIR__ . '/models/ContractTemplate.php';

// 检查登录状态
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

try {
    // 初始化安全管理器
    $securityManager = new \Libs\SecurityManager();
    $securityManager->init();

    // 数据库配置
    $db = new \Libs\DatabaseHelper(
        $GLOBALS['config']['db']['host'],
        $GLOBALS['config']['db']['user'],
        $GLOBALS['config']['db']['pass'],
        $GLOBALS['config']['db']['name']
    );

    // 监控API路由 
    if (isset($_GET['monitor'])) {
        $monitor = new MonitorController($db);
        
        try {
            if ($_GET['monitor'] === 'metrics') {
                $result = $monitor->metrics();
                if($result !== null) {
                    echo json_encode($result);
                }
            } else {
                $monitor->dashboard();
            }
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    // 获取用户数据
    $user_id = (int)$_SESSION['user_id']; 
    $current_user = getUserData($user_id);

    // 获取统计数据
    $stats = getDashboardStats();
    $apiUsage = getApiUsageData();
    $userDistribution = getUserDistributionData();

    // 其他初始化
    $threatPrediction = null;
    if(class_exists('\Admin\Services\SecurityService')) {
        $securityService = new \Admin\Services\SecurityService();
        $threatPrediction = $securityService->predictThreat();
    }

} catch (\Exception $e) {
    error_log($e->getMessage());
    die('系统错误,请联系管理员');
}

// 辅助函数定义
function getUserData($user_id) {
    global $db;
    return $db->query(
        "SELECT * FROM users WHERE id = ?",
        [$user_id]
    )->fetch_assoc();
}

function getDashboardStats() {
    global $db;
    $result = $db->query("
        SELECT 
            COUNT(*) as total_users,
            SUM(last_active > NOW() - INTERVAL 7 DAY) as active_users,
            SUM(is_online = 1) as online_users,
            (SELECT SUM(api_calls) FROM api_usage) as api_calls
        FROM users
    ");
    return $result->fetch_assoc();
}

function getApiUsageData() {
    global $db;
    $result = $db->query("
        SELECT 
            DATE_FORMAT(date, '%Y-%m') as month,
            SUM(api_calls) as calls
        FROM api_usage
        GROUP BY month
        ORDER BY month
        LIMIT 12
    ");
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getUserDistributionData() {
    global $db;
    $result = $db->query("
        SELECT 
            country,
            COUNT(*) as users
        FROM users
        WHERE country IS NOT NULL
        GROUP BY country
    ");
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getModelSettings($user_id) {
    global $db;
    return $db->query(
        "SELECT * FROM user_settings WHERE user_id = ?",
        [$user_id]
    )->fetch_assoc();
}

function getAllUsers() {
    global $db;
    $result = $db->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 100");
    return $result->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI伴侣 - 管理后台</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/admin.css" rel="stylesheet">
    <link href="css/admin-enhanced.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>
</head>
<body class="bg-light">
    <!-- 包含导航栏 -->
    <?php include 'navbar.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <!-- 侧边栏导航 -->
            <?php include 'sidebar.php'; ?>
            
            <!-- 安全态势警告 -->
            <?php 
            if ($threatPrediction && $threatPrediction['risk_level'] === 'high'): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong>安全警告!</strong> 系统检测到高风险威胁: <?= implode(', ', $threatPrediction['attack_types']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

    <!-- 主内容区 -->
    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">
                <i class="bi bi-speedometer2 me-2"></i>
                系统仪表盘
            </h1>
            <div class="btn-toolbar mb-2 mb-md-0">
                <div class="btn-group me-2">
                    <button class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-download"></i> 导出
                    </button>
                    <button class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-printer"></i> 打印
                    </button>
                </div>
                <button class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-arrow-repeat"></i> 刷新
                </button>
            </div>
        </div>
        
        <div class="tab-content">
            <!-- 仪表盘 -->
            <div class="tab-pane fade show active" id="dashboard">
                <!-- 仪表盘内容 -->
                <?php include 'dashboard.php'; ?>
            </div>

                <!-- 模型设置 -->
                <div class="tab-pane fade" id="settings">
                    <!-- 模型设置内容 -->
                    <?php include 'settings.php'; ?>
                </div>

                <!-- 数据分析 -->
                <div class="tab-pane fade" id="analytics">
                    <!-- 数据分析内容 -->
                    <?php include 'analytics.php'; ?>
                </div>

                <!-- 用户管理 -->
                <div class="tab-pane fade" id="users">
                    <!-- 用户管理内容 -->
                    <?php include 'users.php'; ?>
                </div>
                
                <!-- 安全态势 -->
                <div class="tab-pane fade" id="security">
                    <!-- 安全态势内容 -->
                    <?php include 'security_dashboard.php'; ?>
                </div>
                
                <!-- 合同模板 -->
                <div class="tab-pane fade" id="contracts">
                    <!-- 合同模板内容 -->
                    <?php include 'contract_templates.php'; ?>
                </div>
                </div>
            </main>
        </div>
    </div>

    <!-- JavaScript 文件 -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/admin.js"></script>
    
    <!-- 初始化图表 -->
    <script>
        // 初始化API使用图表
        initApiUsageChart(<?php echo json_encode($apiUsage); ?>);
        
        // 初始化用户分布地图
        initUserMap(<?php echo json_encode($userDistribution); ?>);
    </script>
</body>
</html>