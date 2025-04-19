<?php
namespace Middlewares;
require_once __DIR__ . '/libs/CryptoHelper.php';
use Libs\CryptoHelper;
use Libs\SecurityManager;

use Libs\DatabaseHelper;
use Exception;

class BiometricMiddleware {
    private $securityManager;
    private $dbHelper;

    public function __construct() {
        $this->securityManager = SecurityManager::getInstance();
        $this->dbHelper = new DatabaseHelper();
    }

    /**
     * 处理生物识别认证
     */
    public function handle($request, $next) {
        // 跳过非生物识别请求
        if (empty($request['biometric_token'])) {
            return $next($request);
        }

        try {
            // 验证请求安全性
            if (!$this->securityManager->validateBiometricRequest($request)) {
                throw new Exception('生物识别请求验证失败');
            }

            // 获取用户生物识别数据
            $user = $this->dbHelper->getRow("SELECT * FROM users WHERE id = ?", [['value' => $request['user_id'], 'type' => 'i']]);
            if (empty($user['biometric_data'])) {
                throw new Exception('用户未注册生物识别');
            }

            // 解密生物识别数据
            $biometricData = json_decode(CryptoHelper::decrypt($user['biometric_data']), true);
            if (!$biometricData) {
                throw new Exception('生物识别数据解析失败');
            }

            // 验证生物识别令牌
            $isValid = CryptoHelper::verifyBiometricSignature(
                $biometricData['publicKey'],
                $biometricData['challenge'],
                $request['biometric_token'],
                $biometricData['algorithm']
            );

            if (!$isValid) {
                throw new Exception('生物识别验证失败');
            }

            // 验证通过，继续处理请求
            return $next($request);

        } catch (Exception $e) {
            // 记录失败尝试
            error_log('生物识别中间件错误: ' . $e->getMessage());
            
            return [
                'error' => '生物识别认证失败',
                'code' => 403
            ];
        }
    }
}