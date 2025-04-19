<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/test/ServerEnvValidator.php';

try {
    $validator = new ServerEnvValidator();
    $results = $validator->runFullTest();

    if (php_sapi_name() === 'cli') {
        echo "=== 服务器环境测试结果 ===\n";
        foreach ($results as $item => $result) {
            echo "{$item}: {$result['status']}\n";
            if (isset($result['错误'])) {
                echo "\t错误详情: {$result['错误']}\n";
            }
        }
    } else {
        echo "<h3>服务器环境测试报告</h3>";
        echo "<table border='1' style='border-collapse:collapse'>";
        echo "<tr><th>测试项目</th><th>状态</th><th>详情</th></tr>";
        foreach ($results as $item => $result) {
            $color = $result['status'] === '通过' ? 'green' : 'red';
            $details = isset($result['错误']) ? $result['错误'] : '';
            echo "<tr><td>{$item}</td><td style='color:{$color}'>{$result['status']}</td><td>{$details}</td></tr>";
        }
        echo "</table>";
    }

    echo "详细日志已保存至：logs/env_test.log";
} catch (Exception $e) {
    echo "环境测试异常: ".$e->getMessage();
}