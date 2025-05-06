<?php

namespace app\Services\Biometric;

use app\Models\BiometricProfile;
use Libs\CryptoHelper;
use Libs\LogHelper;
use Libs\QuantumCryptoHelper;
use Exception;

/**
 * 生物识别认证服务
 * 
 * 提供生物特征注册、验证和活体检测功能，支持多种生物识别方式
 * 采用国密SM4算法和量子加密技术保护生物特征数据
 * 
 * @version 1.0.0
 * @author 广西港妙科技有限公司
 * @copyright 版权所有 © 广西港妙科技有限公司
 */
class BiometricAuth
{
    /**
     * 生物特征验证器实例
     * 
     * @var object
     */
    protected $verifier;
    
    /**
     * 活体检测器实例
     * 
     * @var object
     */
    protected $livenessDetector;
    
    /**
     * 加密助手实例
     * 
     * @var \Libs\CryptoHelper
     */
    protected $crypto;
    
    /**
     * 量子加密助手实例
     * 
     * @var \Libs\QuantumCryptoHelper
     */
    protected $quantumCrypto;
    
    /**
     * 日志助手实例
     * 
     * @var \Libs\LogHelper
     */
    protected $logger;
    
    /**
     * 构造函数
     */
    public function __construct()
    {
        // 初始化验证器
        $this->verifier = new \SecureBio\Verifier();
        
        // 初始化活体检测器
        $this->livenessDetector = new \SecureBio\LivenessDetector();
        
        // 初始化加密助手
        $this->crypto = new CryptoHelper();
        
        // 初始化量子加密助手
        $this->quantumCrypto = new QuantumCryptoHelper();
        
        // 初始化日志助手
        $this->logger = new LogHelper('biometric');
    }
    
    /**
     * 注册生物特征
     *
     * @param int $userId 用户ID
     * @param array $bioData 生物特征数据
     * @param string $algorithm 算法类型
     * @param array $deviceInfo 设备信息
     * @return int 生物特征档案ID
     * @throws \Exception 注册失败时抛出异常
     */
    public function register($userId, $bioData, $algorithm = 'FIDO2', $deviceInfo = [])
    {
        try {
            // 检查用户是否已注册该类型的生物特征
            if (BiometricProfile::isRegistered($userId, $algorithm)) {
                throw new Exception("用户已注册该类型的生物特征");
            }
            
            // 生成特征模板
            $template = $this->verifier->createTemplate($bioData);
            
            // 使用量子加密存储特征模板
            $encryptedTemplate = $this->quantumCrypto->encrypt($template);
            
            // 创建生物特征档案
            $profile = new BiometricProfile();
            $profile->user_id = $userId;
            $profile->template = $encryptedTemplate;
            $profile->algorithm = $algorithm;
            $profile->device_info = json_encode($deviceInfo);
            $profile->status = 1;
            $profile->save();
            
            // 记录日志
            $this->logger->info("用户 {$userId} 成功注册生物特征", [
                'algorithm' => $algorithm,
                'profile_id' => $profile->id
            ]);
            
            return $profile->id;
        } catch (Exception $e) {
            $this->logger->error("用户 {$userId} 注册生物特征失败: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 验证生物特征
     *
     * @param int $userId 用户ID
     * @param array $bioData 生物特征数据
     * @param array $options 验证选项
     * @return bool 验证结果
     * @throws \Exception 验证失败时抛出异常
     */
    public function verify($userId, $bioData, $options = [])
    {
        try {
            // 获取用户的生物特征档案
            $profile = BiometricProfile::where('user_id', $userId)
                ->where('status', 1)
                ->first();
            
            if (!$profile) {
                throw new Exception("未找到用户的生物特征档案");
            }
            
            // 解密特征模板
            $template = $this->quantumCrypto->decrypt($profile->template);
            
            // 设置默认验证选项
            $defaultOptions = [
                'threshold' => 0.75, // 匹配阈值
                'timeout' => 5000,  // 超时时间(毫秒)
                'liveness_check' => true // 是否进行活体检测
            ];
            
            $verifyOptions = array_merge($defaultOptions, $options);
            
            // 如果启用活体检测
            if ($verifyOptions['liveness_check'] && isset($bioData['image'])) {
                $livenessResult = $this->livenessCheck($bioData['image']);
                if (!$livenessResult) {
                    throw new Exception("活体检测失败");
                }
            }
            
            // 验证生物特征
            $result = $this->verifier->verify(
                $bioData,
                $template,
                [
                    'threshold' => $verifyOptions['threshold'],
                    'timeout' => $verifyOptions['timeout']
                ]
            );
            
            // 记录验证结果
            $this->logger->info("用户 {$userId} 生物特征验证" . ($result ? "成功" : "失败"));
            
            return $result;
        } catch (Exception $e) {
            $this->logger->error("用户 {$userId} 生物特征验证错误: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 活体检测
     *
     * @param mixed $bioImage 生物图像数据
     * @param array $options 检测选项
     * @return bool 检测结果
     */
    public function livenessCheck($bioImage, $options = [])
    {
        try {
            // 设置默认检测选项
            $defaultOptions = [
                'motion' => true,  // 运动检测
                'texture' => true, // 纹理分析
                'depth' => true,   // 深度检测
                'threshold' => 0.85 // 阈值
            ];
            
            $checkOptions = array_merge($defaultOptions, $options);
            
            // 执行活体检测
            $result = $this->livenessDetector->check([
                'image' => $bioImage,
                'motion' => $checkOptions['motion'],
                'texture' => $checkOptions['texture'],
                'depth' => $checkOptions['depth'],
                'threshold' => $checkOptions['threshold']
            ]);
            
            return $result;
        } catch (Exception $e) {
            $this->logger->error("活体检测错误: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 删除生物特征档案
     *
     * @param int $userId 用户ID
     * @param string|null $algorithm 算法类型
     * @return bool 删除结果
     */
    public function delete($userId, $algorithm = null)
    {
        try {
            $query = BiometricProfile::where('user_id', $userId);
            
            if ($algorithm) {
                $query->where('algorithm', $algorithm);
            }
            
            $count = $query->update(['status' => 0]);
            
            $this->logger->info("用户 {$userId} 删除生物特征档案", [
                'algorithm' => $algorithm,
                'count' => $count
            ]);
            
            return $count > 0;
        } catch (Exception $e) {
            $this->logger->error("删除生物特征档案错误: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 获取用户的生物特征档案列表
     *
     * @param int $userId 用户ID
     * @return array 生物特征档案列表
     */
    public function getUserProfiles($userId)
    {
        return BiometricProfile::where('user_id', $userId)
            ->where('status', 1)
            ->get()
            ->toArray();
    }
}