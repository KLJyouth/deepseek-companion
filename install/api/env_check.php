<?php
header('Content-Type: application/json');

// 确保脚本安全执行
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    die(json_encode(['error' => '非法访问']));
}

// 执行环境检测脚本
$scriptPath = realpath(dirname(__FILE__) . '/../../env_check.sh');
if (!file_exists($scriptPath)) {
    die(json_encode(['error' => '环境检测脚本不存在']));
}

// 检查执行权限
if (!is_executable($scriptPath)) {
    die(json_encode(['error' => '环境检测脚本不可执行']));
}

// 执行脚本并捕获输出
$output = [];
$returnVar = 0;
exec($scriptPath . ' 2>&1', $output, $returnVar);

// 解析脚本输出
$result = [
    'os' => ['passed' => false, 'message' => '未知操作系统'],
    'php' => ['passed' => false, 'message' => 'PHP未检测到'],
    'mysql' => ['passed' => false, 'message' => 'MySQL未检测到'],
    'webserver' => ['passed' => false, 'message' => 'Web服务器未检测到'],
    'btPanel' => ['passed' => false, 'message' => '宝塔面板未检测到'],
    'allChecksPassed' => $returnVar === 0
];

foreach ($output as $line) {
    if (strpos($line, '类Unix系统') !== false) {
        $result['os']['passed'] = true;
        $result['os']['message'] = trim(str_replace(['✓ 通过:', '✗ 错误:'], '', $line));
    } elseif (strpos($line, 'PHP版本') !== false) {
        $result['php']['passed'] = !(strpos($line, '错误') !== false || strpos($line, '警告') !== false);
        $result['php']['message'] = trim(str_replace(['✓ 通过:', '✗ 错误:', '⚠ 警告:'], '', $line));
    } elseif (strpos($line, 'MySQL版本') !== false) {
        $result['mysql']['passed'] = !(strpos($line, '错误') !== false || strpos($line, '警告') !== false);
        $result['mysql']['message'] = trim(str_replace(['✓ 通过:', '✗ 错误:', '⚠ 警告:'], '', $line));
    } elseif (strpos($line, '检测到:') !== false) {
        $result['webserver']['passed'] = true;
        $result['webserver']['message'] = trim(str_replace(['✓ 检测到:', '⚠ 信息:'], '', $line));
    } elseif (strpos($line, '宝塔面板') !== false) {
        $result['btPanel']['passed'] = strpos($line, '已安装') !== false;
        $result['btPanel']['message'] = trim(str_replace(['✓ 检测到:', '⚠ 信息:'], '', $line));
    }
}

echo json_encode($result);
?>