<?php
require_once 'config.php';
require_once __DIR__ . '/libs/CryptoHelper.php';
use Libs\CryptoHelper;

echo "=== 加密全流程测试 ===\n";

// 测试数据
$testData = [
    'user_id' => 123,
    'username' => 'test_user',
    'timestamp' => time()
];

try {
    // 1. 加密测试
    echo "测试数据: ".json_encode($testData)."\n";
    
    $encrypted = CryptoHelper::encrypt($testData);
    echo "加密结果: $encrypted\n";
    
    // 2. 解密测试
    $decrypted = CryptoHelper::decrypt($encrypted);
    echo "解密结果: ".json_encode($decrypted)."\n";
    
    // 3. 验证结果
    if ($decrypted['user_id'] !== $testData['user_id'] || 
        $decrypted['username'] !== $testData['username']) {
        throw new Exception("解密数据不匹配");
    }
    
    echo "√ 加密全流程测试通过\n";
    
    // 4. 记录测试结果
    file_put_contents(
        'logs/encryption_flow_test.log',
        date('[Y-m-d H:i:s] ')."加密全流程测试成功\n",
        FILE_APPEND
    );
    
} catch (Exception $e) {
    echo "× 加密测试失败: ".$e->getMessage()."\n";
    
    // 记录详细错误
    file_put_contents(
        'logs/encryption_flow_test.log',
        date('[Y-m-d H:i:s] ')."加密测试失败: ".$e->getMessage()."\n",
        FILE_APPEND
    );
    
    exit(1);
}
