<?php
/**
 * QuantumKeyManager - 量子密钥管理器
 *
 * 负责量子安全密钥的生成、存储、检索和生命周期管理
 * 该组件是DeepSeek Companion安全架构的核心组件之一
 * 
 * @package DeepSeek\Security\Quantum
 * @author DeepSeek Security Team
 * @copyright © 广西港妙科技有限公司
 * @version 1.0.0
 * @license Proprietary
 */

namespace DeepSeek\Security\Quantum;

use Exception;

/**
 * QuantumKeyManager类 - 实现量子密钥管理的核心功能
 * 
 * 该类提供了基于后量子密码学的密钥生成、存储和管理功能
 * 支持多种后量子密码学算法，确保密钥安全性达到国际标准
 */
class QuantumKeyManager
{
    /**
     * @var array 密钥存储库
     */
    private array $keyStore = [];
    
    /**
     * @var string 密钥存储路径
     */
    private string $keyStorePath;
    
    /**
     * @var array 支持的密钥类型
     */
    private array $supportedKeyTypes = ['encryption', 'signing', 'verification', 'decryption'];
    
    /**
     * @var int 密钥有效期（秒）
     */
    private int $keyLifetime = 86400 * 30; // 默认30天
    
    /**
     * 构造函数
     * 
     * @param string|null $keyStorePath 密钥存储路径
     */
    public function __construct(string $keyStorePath = null)
    {
        $this->keyStorePath = $keyStorePath ?? sys_get_temp_dir() . '/quantum_keys';
        $this->initializeKeyStore();
    }
    
    /**
     * 初始化密钥存储
     */
    private function initializeKeyStore(): void
    {
        // 确保密钥存储目录存在
        if (!is_dir($this->keyStorePath)) {
            mkdir($this->keyStorePath, 0700, true);
        }
        
        // 加载现有密钥
        $this->loadKeys();
        
        // 清理过期密钥
        $this->cleanExpiredKeys();
    }
    
    /**
     * 生成密钥对
     * 
     * @param array $entropy 熵源数据
     * @return array 生成的密钥对
     */
    public function generateKeyPair(array $entropy): array
    {
        // 使用提供的熵源生成密钥材料
        $keyMaterial = $this->deriveKeyMaterial($entropy['data']);
        
        // 生成唯一ID
        $keyId = $this->generateKeyId();
        
        // 创建密钥对
        $keyPair = [
            'id' => $keyId,
            'public_key' => $this->generatePublicKey($keyMaterial),
            'private_key' => $this->generatePrivateKey($keyMaterial),
            'created_at' => time(),
            'expires_at' => time() + $this->keyLifetime,
            'entropy_quality' => $entropy['quality'] ?? 0.0
        ];
        
        // 存储密钥对
        $this->storeKeyPair($keyId, $keyPair);
        
        return $keyPair;
    }
    
    /**
     * 获取或创建加密密钥
     * 
     * @param string $algorithm 加密算法
     * @return array 加密密钥
     */
    public function getOrCreateEncryptionKey(string $algorithm): array
    {
        // 查找有效的加密密钥
        foreach ($this->keyStore as $keyId => $keyData) {
            if ($keyData['type'] === 'encryption' && 
                $keyData['algorithm'] === $algorithm && 
                $keyData['expires_at'] > time()) {
                return $keyData;
            }
        }
        
        // 没有找到有效密钥，创建新密钥
        return $this->createEncryptionKey($algorithm);
    }
    
    /**
     * 获取解密密钥
     * 
     * @param string $keyId 密钥ID
     * @return array 解密密钥
     * @throws Exception 如果密钥不存在或已过期
     */
    public function getDecryptionKey(string $keyId): array
    {
        if (!isset($this->keyStore[$keyId])) {
            throw new Exception('解密密钥不存在: ' . $keyId);
        }
        
        $keyData = $this->keyStore[$keyId];
        
        if ($keyData['expires_at'] < time()) {
            throw new Exception('解密密钥已过期: ' . $keyId);
        }
        
        return $keyData;
    }
    
    /**
     * 获取或创建签名密钥
     * 
     * @param string $algorithm 签名算法
     * @return array 签名密钥
     */
    public function getOrCreateSigningKey(string $algorithm): array
    {
        // 查找有效的签名密钥
        foreach ($this->keyStore as $keyId => $keyData) {
            if ($keyData['type'] === 'signing' && 
                $keyData['algorithm'] === $algorithm && 
                $keyData['expires_at'] > time()) {
                return $keyData;
            }
        }
        
        // 没有找到有效密钥，创建新密钥
        return $this->createSigningKey($algorithm);
    }
    
    /**
     * 获取验证密钥
     * 
     * @param string|null $keyId 密钥ID
     * @return array 验证密钥
     * @throws Exception 如果密钥不存在或已过期
     */
    public function getVerificationKey(?string $keyId): array
    {
        if ($keyId === null) {
            throw new Exception('未提供验证密钥ID');
        }
        
        if (!isset($this->keyStore[$keyId])) {
            throw new Exception('验证密钥不存在: ' . $keyId);
        }
        
        $keyData = $this->keyStore[$keyId];
        
        if ($keyData['expires_at'] < time()) {
            throw new Exception('验证密钥已过期: ' . $keyId);
        }
        
        return $keyData;
    }
    
    /**
     * 创建加密密钥
     * 
     * @param string $algorithm 加密算法
     * @return array 创建的加密密钥
     */
    private function createEncryptionKey(string $algorithm): array
    {
        // 生成熵源
        $entropy = [
            'data' => random_bytes(64),
            'quality' => 0.95 // 高质量熵源
        ];
        
        // 生成密钥材料
        $keyMaterial = $this->deriveKeyMaterial($entropy['data']);
        
        // 生成唯一ID
        $keyId = $this->generateKeyId();
        
        // 创建加密密钥
        $key = [
            'id' => $keyId,
            'type' => 'encryption',
            'algorithm' => $algorithm,
            'key_data' => $this->generateEncryptionKeyData($keyMaterial, $algorithm),
            'created_at' => time(),
            'expires_at' => time() + $this->keyLifetime,
            'entropy_quality' => $entropy['quality']
        ];
        
        // 存储密钥
        $this->storeKey($keyId, $key);
        
        return $key;
    }
    
    /**
     * 创建签名密钥
     * 
     * @param string $algorithm 签名算法
     * @return array 创建的签名密钥
     */
    private function createSigningKey(string $algorithm): array
    {
        // 生成熵源
        $entropy = [
            'data' => random_bytes(64),
            'quality' => 0.95 // 高质量熵源
        ];
        
        // 生成密钥材料
        $keyMaterial = $this->deriveKeyMaterial($entropy['data']);
        
        // 生成唯一ID
        $keyId = $this->generateKeyId();
        
        // 创建签名密钥
        $key = [
            'id' => $keyId,
            'type' => 'signing',
            'algorithm' => $algorithm,
            'key_data' => $this->generateSigningKeyData($keyMaterial, $algorithm),
            'created_at' => time(),
            'expires_at' => time() + $this->keyLifetime,
            'entropy_quality' => $entropy['quality']
        ];
        
        // 存储密钥
        $this->storeKey($keyId, $key);
        
        return $key;
    }
    
    /**
     * 从熵源派生密钥材料
     * 
     * @param string $entropy 熵源数据
     * @return string 密钥材料
     */
    private function deriveKeyMaterial(string $entropy): string
    {
        // 使用HKDF（基于HMAC的密钥派生函数）从熵源派生密钥材料
        // 在实际实现中，应使用更复杂的密钥派生函数
        $salt = random_bytes(32);
        $info = 'quantum_key_derivation';
        
        // 使用HKDF算法
        $prk = hash_hmac('sha384', $entropy, $salt, true);
        $keyMaterial = hash_hmac('sha384', $info . chr(1), $prk, true);
        
        return $keyMaterial;
    }
    
    /**
     * 生成公钥
     * 
     * @param string $keyMaterial 密钥材料
     * @return string 公钥数据
     */
    private function generatePublicKey(string $keyMaterial): string
    {
        // 在实际实现中，应使用后量子密码学库生成公钥
        // 这里使用模拟实现
        $publicKeyData = hash('sha512', $keyMaterial . 'public', true);
        return base64_encode($publicKeyData);
    }
    
    /**
     * 生成私钥
     * 
     * @param string $keyMaterial 密钥材料
     * @return string 私钥数据
     */
    private function generatePrivateKey(string $keyMaterial): string
    {
        // 在实际实现中，应使用后量子密码学库生成私钥
        // 这里使用模拟实现
        $privateKeyData = hash('sha512', $keyMaterial . 'private', true);
        return base64_encode($privateKeyData);
    }
    
    /**
     * 生成加密密钥数据
     * 
     * @param string $keyMaterial 密钥材料
     * @param string $algorithm 加密算法
     * @return string 加密密钥数据
     */
    private function generateEncryptionKeyData(string $keyMaterial, string $algorithm): string
    {
        // 根据不同算法生成适合的密钥数据
        // 在实际实现中，应使用特定算法的密钥生成函数
        $keyData = hash_hmac('sha384', $algorithm . $keyMaterial, 'encryption', true);
        return base64_encode($keyData);
    }
    
    /**
     * 生成签名密钥数据
     * 
     * @param string $keyMaterial 密钥材料
     * @param string $algorithm 签名算法
     * @return string 签名密钥数据
     */
    private function generateSigningKeyData(string $keyMaterial, string $algorithm): string
    {
        // 根据不同算法生成适合的密钥数据
        // 在实际实现中，应使用特定算法的密钥生成函数
        $keyData = hash_hmac('sha384', $algorithm . $keyMaterial, 'signing', true);
        return base64_encode($keyData);
    }
    
    /**
     * 生成唯一密钥ID
     * 
     * @return string 密钥ID
     */
    private function generateKeyId(): string
    {
        // 生成UUID v4
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // 设置版本为4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // 设置变体
        
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
    
    /**
     * 存储密钥对
     * 
     * @param string $keyId 密钥ID
     * @param array $keyPair 密钥对数据
     */
    private function storeKeyPair(string $keyId, array $keyPair): void
    {
        // 存储公钥和私钥
        $this->keyStore[$keyId] = [
            'id' => $keyId,
            'type' => 'keypair',
            'public_key' => $keyPair['public_key'],
            'private_key' => $keyPair['private_key'],
            'created_at' => $keyPair['created_at'],
            'expires_at' => $keyPair['expires_at'],
            'entropy_quality' => $keyPair['entropy_quality']
        ];
        
        // 持久化存储
        $this->persistKeys();
    }
    
    /**
     * 存储单个密钥
     * 
     * @param string $keyId 密钥ID
     * @param array $key 密钥数据
     */
    private function storeKey(string $keyId, array $key): void
    {
        // 存储密钥
        $this->keyStore[$keyId] = $key;
        
        // 持久化存储
        $this->persistKeys();
    }
    
    /**
     * 持久化存储密钥
     */
    private function persistKeys(): void
    {
        // 在实际实现中，应使用安全的存储机制
        // 例如加密数据库或硬件安全模块(HSM)
        // 这里使用简化的文件存储实现
        
        $encryptedData = $this->encryptKeyStore();
        $keyStorePath = $this->keyStorePath . '/keystore.dat';
        
        file_put_contents($keyStorePath, $encryptedData);
        chmod($keyStorePath, 0600); // 设置适当的权限
    }
    
    /**
     * 加载密钥
     */
    private function loadKeys(): void
    {
        $keyStorePath = $this->keyStorePath . '/keystore.dat';
        
        if (file_exists($keyStorePath)) {
            $encryptedData = file_get_contents($keyStorePath);
            $this->keyStore = $this->decryptKeyStore($encryptedData);
        }
    }
    
    /**
     * 加密密钥存储
     * 
     * @return string 加密后的密钥存储数据
     */
    private function encryptKeyStore(): string
    {
        // 在实际实现中，应使用强加密保护密钥存储
        // 这里使用简化的实现
        $data = json_encode($this->keyStore);
        $key = $this->getStorageEncryptionKey();
        
        // 使用AES-256-GCM加密
        $iv = random_bytes(16);
        $tag = '';
        $encrypted = openssl_encrypt($data, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        
        // 组合IV、认证标签和密文
        return base64_encode($iv . $tag . $encrypted);
    }
    
    /**
     * 解密密钥存储
     * 
     * @param string $encryptedData 加密的密钥存储数据
     * @return array 解密后的密钥存储
     */
    private function decryptKeyStore(string $encryptedData): array
    {
        try {
            $data = base64_decode($encryptedData);
            $key = $this->getStorageEncryptionKey();
            
            // 提取IV、认证标签和密文
            $iv = substr($data, 0, 16);
            $tag = substr($data, 16, 16);
            $ciphertext = substr($data, 32);
            
            // 使用AES-256-GCM解密
            $decrypted = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
            
            if ($decrypted === false) {
                throw new Exception('密钥存储解密失败');
            }
            
            return json_decode($decrypted, true) ?: [];
        } catch (Exception $e) {
            // 解密失败，返回空存储
            return [];
        }
    }
    
    /**
     * 获取存储加密密钥
     * 
     * @return string 存储加密密钥
     */
    private function getStorageEncryptionKey(): string
    {
        // 在实际实现中，应使用更安全的密钥派生和存储机制
        // 例如从环境变量、配置文件或硬件安全模块获取
        // 这里使用简化的实现
        
        $keyFile = $this->keyStorePath . '/master.key';
        
        if (!file_exists($keyFile)) {
            $masterKey = random_bytes(32); // 256位密钥
            file_put_contents($keyFile, $masterKey);
            chmod($keyFile, 0600); // 设置适当的权限
        } else {
            $masterKey = file_get_contents($keyFile);
        }
        
        return $masterKey;
    }
    
    /**
     * 清理过期密钥
     */
    private function cleanExpiredKeys(): void
    {
        $now = time();
        $modified = false;
        
        foreach ($this->keyStore as $keyId => $keyData) {
            if ($keyData['expires_at'] < $now) {
                unset($this->keyStore[$keyId]);
                $modified = true;
            }
        }
        
        if ($modified) {
            $this->persistKeys();
        }
    }
    
    /**
     * 设置密钥有效期
     * 
     * @param int $seconds 有效期（秒）
     * @return self
     */
    public function setKeyLifetime(int $seconds): self
    {
        if ($seconds < 3600) { // 最短1小时
            throw new Exception('密钥有效期不能少于1小时');
        }
        
        $this->keyLifetime = $seconds;
        return $this;
    }
    
    /**
     * 轮换所有密钥
     * 
     * 创建新密钥并标记旧密钥为即将过期
     * 
     * @return int 轮换的密钥数量
     */
    public function rotateAllKeys(): int
    {
        $rotatedCount = 0;
        $algorithms = [
            'encryption' => ['CRYSTALS-Kyber-768'],
            'signing' => ['CRYSTALS-Dilithium-3']
        ];
        
        // 为每种算法创建新密钥
        foreach ($algorithms as $type => $algoList) {
            foreach ($algoList as $algorithm) {
                if ($type === 'encryption') {
                    $this->createEncryptionKey($algorithm);
                    $rotatedCount++;
                } elseif ($type === 'signing') {
                    $this->createSigningKey($algorithm);
                    $rotatedCount++;
                }
            }
        }
        
        // 标记现有密钥为即将过期（7天后）
        $expiryTime = time() + 86400 * 7;
        
        foreach ($this->keyStore as $keyId => &$keyData) {
            // 只处理未过期且过期时间超过新设定时间的密钥
            if ($keyData['expires_at'] > $expiryTime) {
                $keyData['expires_at'] = $expiryTime;
            }
        }
        
        // 持久化更改
        $this->persistKeys();
        
        return $rotatedCount;
    }
}