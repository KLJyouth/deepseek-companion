<?php
/**
 * 安装验证脚本
 * 用于验证系统是否已正确安装
 */

// 设置响应头
header('Content-Type: text/html; charset=utf-8');

// 定义验证结果数组
$results = [];

// 1. 检查配置文件是否存在
$configFile = dirname(__DIR__) . '/config.php';
if (file_exists($configFile)) {
    $results['config_file'] = [
        'status' => 'success',
        'message' => '配置文件存在'
    ];
    
    // 尝试加载配置文件
    try {
        $config = require $configFile;
        $results['config_load'] = [
            'status' => 'success',
            'message' => '配置文件可正常加载'
        ];
    } catch (Exception $e) {
        $results['config_load'] = [
            'status' => 'error',
            'message' => '配置文件加载失败: ' . $e->getMessage()
        ];
    }
} else {
    $results['config_file'] = [
        'status' => 'error',
        'message' => '配置文件不存在'
    ];
}

// 2. 验证数据库连接
if (isset($config) && isset($config['database'])) {
    try {
        $dbConfig = $config['database'];
        $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};charset={$dbConfig['charset']}";
        $pdo = new PDO(
            $dsn,
            $dbConfig['username'],
            $dbConfig['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        // 检查数据库是否存在
        $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '{$dbConfig['database']}'");
        $dbExists = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($dbExists) {
            $results['database_connection'] = [
                'status' => 'success',
                'message' => '数据库连接成功'
            ];
            
            $results['database_exists'] = [
                'status' => 'success',
                'message' => '数据库存在'
            ];
            
            // 检查必要的表是否存在
            $pdo->exec("USE {$dbConfig['database']}");
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            
            if (count($tables) > 0) {
                $results['database_tables'] = [
                    'status' => 'success',
                    'message' => '数据库表存在 (' . count($tables) . ' 张表)'
                ];
            } else {
                $results['database_tables'] = [
                    'status' => 'warning',
                    'message' => '数据库中没有表'
                ];
            }
        } else {
            $results['database_exists'] = [
                'status' => 'error',
                'message' => '数据库不存在'
            ];
        }
    } catch (PDOException $e) {
        $results['database_connection'] = [
            'status' => 'error',
            'message' => '数据库连接失败: ' . $e->getMessage()
        ];
    }
} else {
    $results['database_connection'] = [
        'status' => 'error',
        'message' => '缺少数据库配置'
    ];
}

// 3. 检查存储目录权限
$storageDirs = [
    'cache' => dirname(__DIR__) . '/storage/cache',
    'logs' => dirname(__DIR__) . '/storage/logs',
    'sessions' => dirname(__DIR__) . '/storage/sessions'
];

foreach ($storageDirs as $name => $path) {
    if (file_exists($path) && is_writable($path)) {
        $results["storage_{$name}"] = [
            'status' => 'success',
            'message' => "{$name}目录可写"
        ];
    } else {
        $results["storage_{$name}"] = [
            'status' => 'error',
            'message' => "{$name}目录不可写或不存在"
        ];
    }
}

// 4. 检查管理员账户（模拟）
if (isset($config) && isset($config['admin'])) {
    $results['admin_config'] = [
        'status' => 'success',
        'message' => '管理员配置存在'
    ];
} else {
    $results['admin_config'] = [
        'status' => 'error',
        'message' => '缺少管理员配置'
    ];
}

// 输出验证结果
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>安装验证报告</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; margin: 20px; }
        h1 { color: #333; }
        .result { margin-bottom: 10px; padding: 10px; border-radius: 4px; }
        .success { background-color: #dff0d8; color: #3c763d; }
        .warning { background-color: #fcf8e3; color: #8a6d3b; }
        .error { background-color: #f2dede; color: #a94442; }
        .summary { margin-top: 20px; padding: 15px; font-weight: bold; }
    </style>
</head>
<body>
    <h1>系统安装验证报告</h1>
    <p>生成时间: <?php echo date('Y-m-d H:i:s'); ?></p>
    
    <h2>验证结果</h2>
    <?php
    $successCount = 0;
    $warningCount = 0;
    $errorCount = 0;
    
    foreach ($results as $key => $result) {
        echo '<div class="result ' . $result['status'] . '">';
        echo '<strong>' . ucfirst(str_replace('_', ' ', $key)) . ':</strong> ';
        echo $result['message'];
        echo '</div>';
        
        switch ($result['status']) {
            case 'success': $successCount++; break;
            case 'warning': $warningCount++; break;
            case 'error': $errorCount++; break;
        }
    }
    ?>
    
    <div class="summary">
        <p>总结: 
            <span style="color: green;">成功: <?php echo $successCount; ?></span> |
            <span style="color: orange;">警告: <?php echo $warningCount; ?></span> |
            <span style="color: red;">错误: <?php echo $errorCount; ?></span>
        </p>
        <?php if ($errorCount === 0): ?>
            <p style="color: green;">系统安装验证通过！</p>
        <?php else: ?>
            <p style="color: red;">系统安装存在问题，请修复上述错误。</p>
        <?php endif; ?>
    </div>
</body>
</html>