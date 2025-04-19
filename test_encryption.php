<?php
/**
 * 加密配置测试脚本
 */

require_once 'config.php';
require_once __DIR__ . '/libs/CryptoHelper.php';
use Libs\CryptoHelper;

echo "=== 加密配置测试 ===\n";

// 检查logs目录
if (!file_exists('logs') || !is_writable('logs')) {
    die("错误: logs目录不存在或不可写\n");
}

// 测试1: 基本加密/解密
$testString = 'test_encryption_' . time();
try {
    echo "测试字符串: $testString\n";
    $encrypted = CryptoHelper::encrypt($testString);
    echo "加密结果: $encrypted\n";
    $decrypted = CryptoHelper::decrypt($encrypted);
    echo "解密结果: $decrypted\n";
    
    if ($decrypted !== $testString) {
        throw new Exception("解密结果不匹配");
    }
    echo "√ 基本加密/解密测试通过\n";
} catch (Exception $e) {
    die("× 基本加密/解密测试失败: " . $e->getMessage() . "\n");
}

// 测试2: API密钥加密
$apiKey = 'sk-test-key-123'; // 测试用API密钥
try {
    $encryptedKey = CryptoHelper::encrypt($apiKey);
    $decryptedKey = CryptoHelper::decrypt($encryptedKey);
    
    if ($decryptedKey !== $apiKey) {
        throw new Exception("API密钥解密不匹配");
    }
    echo "√ API密钥加密测试通过\n";
} catch (Exception $e) {
    die("× API密钥加密测试失败: " . $e->getMessage() . "\n");
}

// 保存详细测试结果
$logContent = date('[Y-m-d H:i:s] ') . "加密测试结果:\n";
$logContent .= "加密方法: " . ENCRYPTION_METHOD . "\n";
$logContent .= "密钥长度: " . strlen(ENCRYPTION_KEY) . "字节\n";
$logContent .= "IV长度: " . strlen(ENCRYPTION_IV) . "字节\n";
$logContent .= "测试状态: 成功\n";

file_put_contents('logs/encryption_test.log', $logContent, FILE_APPEND);

echo "测试完成，详细结果已保存到logs/encryption_test.log\n";
