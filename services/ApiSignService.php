<?php
namespace App\Services;

use App\Libs\CryptoHelper;
use App\Libs\ConfigHelper;
use Exception;

class ApiSignService {
    private $secretKey;
    
    public function __construct() {
        $this->secretKey = ConfigHelper::get('api.secret_key');
        if (empty($this->secretKey)) {
            throw new Exception('API签名密钥未配置');
        }
    }
    
    /**
     * 生成API请求签名
     */
    public function generateSignature(array $data, string $timestamp): string {
        ksort($data);
        $signString = http_build_query($data) . $timestamp . $this->secretKey;
        return hash_hmac('sha256', $signString, $this->secretKey);
    }
    
    /**
     * 验证API请求签名
     */
    public function verifySignature(array $data, string $timestamp, string $signature): bool {
        $expected = $this->generateSignature($data, $timestamp);
        return hash_equals($expected, $signature);
    }
    
    /**
     * 验证请求时间戳有效性(防止重放攻击)
     */
    public function validateTimestamp(string $timestamp): bool {
        $requestTime = (int)$timestamp;
        $currentTime = time();
        // 允许5分钟时间差
        return abs($currentTime - $requestTime) <= 300;
    }
}