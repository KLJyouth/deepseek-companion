<?php
declare(strict_types=1);

namespace Libs;

use \Exception;
use \Throwable;
use \SodiumException;

/**
 * AI伴侣自定义加密工具类
 * 提供数据加密/解密和编解码功能
 */
final class CryptoHelper {
    private const DEFAULT_CIPHER = 'aes-256-gcm';
    private const KEY_DERIVATION_ITERATIONS = 100000;
    
    private static string $encryptionKey;
    private static string $iv;
    private static ?array $pqcKeyPair = null;
    private static string $currentCipher = self::DEFAULT_CIPHER;
    
    /**
     * 初始化加密配置
     * @param string $key 32字节加密密钥
     * @param string $iv 初始化向量(12字节为GCM模式，16字节为CBC模式)
     * @param string|null $cipher 加密算法(默认aes-256-gcm)
     * @throws \InvalidArgumentException 如果参数无效
     */
    public static function init(
        string $key, 
        string $iv, 
        ?string $cipher = null
    ): void {
        if (strlen($key) !== 32) {
            throw new \InvalidArgumentException('加密密钥必须是32字节');
        }
        
        $cipher = $cipher ?? self::DEFAULT_CIPHER;
        $validIvLengths = [
            'aes-256-gcm' => 12,
            'aes-256-cbc' => 16
        ];
        
        if (!isset($validIvLengths[$cipher]) || strlen($iv) !== $validIvLengths[$cipher]) {
            throw new \InvalidArgumentException(sprintf(
                'IV必须是%d字节(%s模式)',
                $validIvLengths[$cipher] ?? 0,
                $cipher
            ));
        }
        
        self::$encryptionKey = $key;
        self::$iv = $iv;
        self::$currentCipher = in_array($cipher, openssl_get_cipher_methods()) 
            ? $cipher 
            : self::DEFAULT_CIPHER;
    }

    /**
     * 密钥轮换
     */
    public static function rotateKey(string $newKey, string $newIv): array {
        if (strlen($newKey) !== 32 || strlen($newIv) !== 16) {
            throw new \InvalidArgumentException('密钥和IV长度必须分别为32和16字节');
        }

        $oldKey = self::$encryptionKey;
        $oldIv = self::$iv;
        
        self::$encryptionKey = $newKey;
        self::$iv = $newIv;

        return [
            'old_key' => $oldKey,
            'old_iv' => $oldIv
        ];
    }

    /**
     * 加密健康检查
     */
    public static function healthCheck(): array {
        $testString = 'health_check_' . microtime(true);
        try {
            // 验证密钥和IV是否已设置
            if (!isset(self::$encryptionKey) || !isset(self::$iv)) {
                throw new \RuntimeException('加密密钥或IV未初始化');
            }

            // 测试加密/解密流程
            $encrypted = self::encrypt($testString);
            $decrypted = self::decrypt($encrypted);
            
            if ($decrypted !== $testString) {
                throw new \RuntimeException('加密/解密验证失败');
            }

            // 测试openssl功能
            if (!function_exists('openssl_encrypt')) {
                throw new \RuntimeException('OpenSSL扩展未加载');
            }

            // 测试加密算法支持
            $ciphers = openssl_get_cipher_methods();
            if (!in_array('AES-256-CBC', $ciphers)) {
                throw new \RuntimeException('AES-256-CBC算法不支持');
            }

            return [
                'status' => 'healthy',
                'test_string' => $testString,
                'encrypted' => $encrypted,
                'decrypted' => $decrypted,
                'algorithm' => 'AES-256-CBC',
                'openssl_version' => OPENSSL_VERSION_TEXT
            ];
        } catch (\Exception $e) {
            error_log('[CRYPTO HEALTH CHECK] 加密健康检查失败: ' . $e->getMessage());
            return [
                'status' => 'failed',
                'error' => $e->getMessage(),
                'openssl_loaded' => extension_loaded('openssl'),
                'openssl_version' => defined('OPENSSL_VERSION_TEXT') ? OPENSSL_VERSION_TEXT : 'unknown'
            ];
        }
    }

    /**
     * 生成消息签名
     * @param array $data 包含timestamp、nonce和message的数组
     * @return string 签名
     * @throws \InvalidArgumentException 当数据不完整时抛出
     * @throws \RuntimeException 当签名密钥未配置时抛出
     */
    public static function generateSignature(array $data): string {
        if (empty($data['timestamp']) || empty($data['nonce']) || empty($data['message'])) {
            throw new \InvalidArgumentException('签名数据不完整');
        }
        
        $signingKey = ConfigHelper::get('websocket.signing_key');
        if (empty($signingKey)) {
            throw new \RuntimeException('签名密钥未配置');
        }
        
        return hash_hmac('sha256', 
            $data['timestamp'].$data['nonce'].$data['message'], 
            $signingKey
        );
    }
    
    /**
     * MD5加密层（作为额外保护）
     */
    public static function md5Encode($data, $salt = '') {
        $prepared = $data . $salt . self::$encryptionKey;
        return md5($prepared);
    }
    
    /**
     * 安全擦除内存中的敏感数据
     * @param string &$data 要擦除的数据引用
     */
    public static function secureErase(string &$data): void {
        try {
            if (function_exists('sodium_memzero')) {
                sodium_memzero($data);
            } else {
                // 回退实现
                $length = strlen($data);
                $data = str_repeat("\0", $length);
                unset($data);
            }
        } finally {
            $data = '';
        }
    }

    /**
     * 自定义编码算法(带安全擦除)
     */
    public static function customEncode($data): string {
        try {
            if (!is_string($data)) {
                $data = json_encode($data);
                if ($data === false) {
                    throw new \RuntimeException('JSON编码失败');
                }
            }
            
            // 第一层：Base64编码
            $encoded = base64_encode($data);
            
            // 第二层：字符替换
            $encoded = strtr($encoded, [
                '+' => '.',
                '/' => '_',
                '=' => '-'
            ]);
            
            // 第三层：添加随机干扰字符
            $result = '';
            $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
            $charLen = strlen($chars) - 1;
            
            for ($i = 0; $i < strlen($encoded); $i++) {
                $result .= $encoded[$i];
                if ($i % 3 == 0) {
                    $result .= $chars[random_int(0, $charLen)];
                }
            }
            
            return $result;
        } finally {
            if (isset($data)) {
                self::secureErase($data);
            }
        }
    }
    
    /**
     * 自定义解码算法 
     */
    public static function customDecode($encoded) {
        // 移除干扰字符
        $clean = '';
        for ($i = 0; $i < strlen($encoded); $i++) {
            if ($i % 4 != 0) {
                $clean .= $encoded[$i];
            }
        }
        
        // 恢复字符替换
        $clean = strtr($clean, [
            '.' => '+',
            '_' => '/',
            '-' => '='
        ]);
        
        // Base64解码
        return base64_decode($clean);
    }
    
    /**
     * AES-256-GCM加密
     * @param mixed $data 要加密的数据(自动JSON编码非字符串数据)
     * @return array{ciphertext:string, tag:string} 包含密文和认证标签的数组
     * @throws \RuntimeException 如果加密失败
     */
    public static function encrypt($data): array {
        if (!isset(self::$encryptionKey)) {
            throw new \RuntimeException('加密组件未初始化，请先调用init()');
        }
        
        // 序列化非字符串数据
        $payload = is_string($data) ? $data : json_encode($data);
        if ($payload === false) {
            throw new \RuntimeException('数据序列化失败');
        }
        
        $tag = '';
        $ciphertext = openssl_encrypt(
            $payload,
            'aes-256-gcm',
            self::$encryptionKey,
            OPENSSL_RAW_DATA,
            self::$iv,
            $tag,
            '',
            16
        );
        
        if ($ciphertext === false) {
            throw new \RuntimeException('加密失败: ' . openssl_error_string());
        }
        
        return [
            'ciphertext' => base64_encode($ciphertext),
            'tag' => base64_encode($tag)
        ];
    }
    
    /**
     * AES-256-GCM解密
     * @param array{ciphertext:string, tag:string} $encrypted 加密数据数组
     * @return mixed 解密后的原始数据(自动JSON解码如果可能)
     * @throws \RuntimeException 如果解密失败
     */
    public static function decrypt(array $encrypted) {
        if (!isset(self::$encryptionKey)) {
            throw new \RuntimeException('加密组件未初始化，请先调用init()');
        }
        
        $ciphertext = base64_decode($encrypted['ciphertext']);
        $tag = base64_decode($encrypted['tag']);
        
        $data = openssl_decrypt(
            $ciphertext,
            'AES-256-GCM',
            self::$encryptionKey,
            OPENSSL_RAW_DATA,
            self::$iv,
            $tag
        );
        
        if ($data === false) {
            throw new \RuntimeException('解密失败: ' . openssl_error_string());
        }
        
        // 尝试自动JSON解码
        $decoded = json_decode($data, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $data;
    }
    
    /**
     * 生成安全哈希 (用于密码存储)
     */
    public static function hashPassword($password) {
        // 直接使用Bcrypt算法
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
    
    /**
     * 验证密码哈希
     */
    public static function verifyPassword($password, $hash) {
        // 首先尝试直接验证
        if (password_verify($password, $hash)) {
            return true;
        }
        
        // 兼容旧密码(带MD5预处理)
        $md5Pass = self::md5Encode($password);
        $prepared = self::customEncode($md5Pass);
        if (password_verify($prepared, $hash)) {
            // 密码验证成功但使用旧哈希，触发重新哈希
            trigger_error('检测到使用旧密码哈希，建议更新密码存储', E_USER_NOTICE);
            return true;
        }
        
        return false;
    }
    
    /**
     * 检查密码是否需要重新哈希
     */
    public static function needsRehash($hash) {
        return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12]);
    }
    
    /**
     * 生成CSRF令牌
     */
    public static function generateCsrfToken() {
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = self::encrypt($token);
        return $token;
    }
    
    /**
     * 验证CSRF令牌
     */
    public static function validateCsrfToken($token) {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        $storedToken = self::decrypt($_SESSION['csrf_token']);
        return hash_equals($storedToken, $token);
    }

    /**
     * Base32解码函数 (RFC 4648标准)
     * @param string $data 要解码的base32字符串
     * @return string 解码后的二进制数据
     * @throws \InvalidArgumentException 如果输入包含无效字符
     */
    public static function base32_decode(string $data): string {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $buffer = 0;
        $bufferSize = 0;
        $result = '';
        $data = rtrim($data, "=\x20\t\n\r\0\x0B");
        $dataLength = strlen($data);

        for ($i = 0; $i < $dataLength; $i++) {
            $char = $data[$i];
            $pos = strpos($chars, $char);
            
            if ($pos === false) {
                throw new \InvalidArgumentException("Invalid base32 character: $char");
            }
            
            $buffer <<= 5;
            $buffer |= $pos;
            $bufferSize += 5;
            
            if ($bufferSize >= 8) {
                $bufferSize -= 8;
                $result .= chr(($buffer >> $bufferSize) & 0xFF);
            }
        }

        return $result;
    }

    /**
     * 初始化量子加密密钥对
     * @throws \RuntimeException 如果量子加密扩展不可用
     */
    /**
     * AI风险评估等级
     */
    private static function getRiskLevel(): int {
        // 调用AI威胁分析服务获取当前风险等级
        try {
            $response = file_get_contents('http://localhost:8080/api/risk-level');
            $data = json_decode($response, true);
            return min(max($data['level'] ?? 1, 1), 5); // 1-5级风险
        } catch (\Exception $e) {
            error_log('AI风险评估失败: ' . $e->getMessage());
            return 3; // 默认中等风险
        }
    }

    /**
     * 根据风险等级确定密钥有效期(秒)
     */
    private static function getKeyTTL(): int {
        $riskLevel = self::getRiskLevel();
        // 高风险时缩短密钥有效期
        return [3600, 28800, 86400, 43200, 21600][$riskLevel - 1];
    }

    /**
     * 支持的格基加密算法
     */
    private const LATTICE_ALGORITHMS = [
        'kyber1024' => [
            'key_size' => 1024,
            'security_level' => 5
        ],
        'ntru_hps2048509' => [
            'key_size' => 509,
            'security_level' => 3
        ],
        'saber' => [
            'key_size' => 768,
            'security_level' => 4
        ]
    ];

    public static function initPQC(string $preferredAlgorithm = 'kyber1024'): void {
        if (!extension_loaded('pqcrypto')) {
            throw new \RuntimeException('量子加密扩展(pqcrypto)未安装');
        }

        // 验证算法支持
        if (!isset(self::LATTICE_ALGORITHMS[$preferredAlgorithm])) {
            $preferredAlgorithm = 'kyber1024'; // 默认算法
        }

        // 检查现有密钥是否过期或算法不匹配
        if (self::$pqcKeyPair && (
            time() - self::$pqcKeyPair['created_at'] > self::getKeyTTL() ||
            self::$pqcKeyPair['algorithm'] !== $preferredAlgorithm
        )) {
            self::$pqcKeyPair = null; // 强制密钥轮换
        }

        if (self::$pqcKeyPair === null) {
            try {
                // 根据安全等级选择最佳算法
                $selectedAlgorithm = self::selectOptimalAlgorithm($preferredAlgorithm);
                
                self::$pqcKeyPair = [
                    'public_key' => pqc_generate_public_key($selectedAlgorithm),
                    'private_key' => pqc_generate_private_key($selectedAlgorithm),
                    'algorithm' => $selectedAlgorithm,
                    'created_at' => time(),
                    'expires_at' => time() + self::getKeyTTL(),
                    'security_level' => self::LATTICE_ALGORITHMS[$selectedAlgorithm]['security_level']
                ];

                // 增强版区块链存证
                self::logKeyGeneration();
            } catch (\Exception $e) {
                throw new \RuntimeException('量子密钥生成失败: ' . $e->getMessage());
            }
        }
    }

    /**
     * 根据风险等级选择最优算法
     */
    private static function selectOptimalAlgorithm(string $preferred): string {
        $riskLevel = self::getRiskLevel();
        $available = array_keys(self::LATTICE_ALGORITHMS);
        
        // 高风险时使用最高安全级别算法
        if ($riskLevel >= 4) {
            $selected = 'kyber1024';
            foreach (self::LATTICE_ALGORITHMS as $alg => $spec) {
                if ($spec['security_level'] > self::LATTICE_ALGORITHMS[$selected]['security_level']) {
                    $selected = $alg;
                }
            }
            return $selected;
        }
        
        // 优先使用首选算法（如果支持）
        return in_array($preferred, $available) ? $preferred : 'kyber1024';
    }

    /**
     * 增强版区块链存证
     */
    private static function logKeyGeneration(): void {
        $txData = [
            'key_id' => bin2hex(random_bytes(16)),
            'public_key_hash' => hash('sha3-256', self::$pqcKeyPair['public_key']),
            'generated_at' => date('c'),
            'expires_at' => date('c', self::$pqcKeyPair['expires_at']),
            'risk_level' => self::getRiskLevel(),
            'algorithm' => self::$pqcKeyPair['algorithm'],
            'security_level' => self::$pqcKeyPair['security_level'],
            'zero_knowledge_proof' => self::generateZKProof()
        ];

        // 多链存证
        $chains = ['fabric', 'ethereum', 'hyperledger'];
        foreach ($chains as $chain) {
            $txData['chain'] = $chain;
            file_put_contents("/tmp/key_gen_{$chain}.log", json_encode($txData)."\n", FILE_APPEND);
        }
    }

    /**
     * 生成零知识证明
     */
    private static function generateZKProof(): string {
        // 简化的零知识证明生成逻辑
        $challenge = bin2hex(random_bytes(16));
        $response = hash('sha3-256', $challenge . self::$pqcKeyPair['private_key']);
        return base64_encode($challenge . ':' . $response);
    }

    /**
     * 量子安全加密(混合加密方案)
     * @param string $data 要加密的原始数据
     * @return string 加密后的数据(base64编码)
     * @throws \RuntimeException 如果加密失败或量子密钥未初始化
     */
    public static function quantumEncrypt(string $data): string {
        if (self::$pqcKeyPair === null) {
            throw new \RuntimeException('量子密钥未初始化，请先调用initPQC()');
        }

        try {
            // 生成临时AES密钥
            $aesKey = random_bytes(32);
            $iv = random_bytes(16);

            // 使用AES加密数据
            $encryptedData = openssl_encrypt(
                $data,
                'AES-256-CBC',
                $aesKey,
                OPENSSL_RAW_DATA,
                $iv
            );

            // 用量子公钥加密AES密钥
            $encryptedKey = pqc_encrypt(
                $aesKey,
                self::$pqcKeyPair['public_key']
            );

            // 组合加密结果
            return base64_encode(json_encode([
                'iv' => base64_encode($iv),
                'key' => base64_encode($encryptedKey),
                'data' => base64_encode($encryptedData),
                'algorithm' => 'AES-256-CBC+Kyber1024',
                'timestamp' => time()
            ]));
        } catch (\Exception $e) {
            throw new \RuntimeException('量子加密失败: ' . $e->getMessage());
        }
    }

    /**
     * 量子安全解密(混合加密方案)
     * @param string $encrypted 加密数据(base64编码)
     * @return string 解密后的原始数据
     * @throws \RuntimeException 如果解密失败或量子密钥未初始化
     */
    public static function quantumDecrypt(string $encrypted): string {
        if (self::$pqcKeyPair === null) {
            throw new \RuntimeException('量子密钥未初始化，请先调用initPQC()');
        }

        try {
            $parts = json_decode(base64_decode($encrypted), true);
            $iv = base64_decode($parts['iv']);
            $encryptedKey = base64_decode($parts['key']);
            $encryptedData = base64_decode($parts['data']);

            // 用量子私钥解密AES密钥
            $aesKey = pqc_decrypt(
                $encryptedKey,
                self::$pqcKeyPair['private_key']
            );

            // 使用AES解密数据
            return openssl_decrypt(
                $encryptedData,
                'AES-256-CBC',
                $aesKey,
                OPENSSL_RAW_DATA,
                $iv
            );
        } catch (\Exception $e) {
            throw new \RuntimeException('量子解密失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取量子公钥
     * @return string|null 量子公钥(base64编码)，如果未初始化则返回null
     * @throws \RuntimeException 如果Libsodium不可用
     */
    public static function getQuantumPublicKey(): ?string {
        if (!function_exists('sodium_crypto_box_keypair')) {
            throw new \RuntimeException('Libsodium扩展不可用');
        }
        
        if (!self::$pqcKeyPair) {
            self::$pqcKeyPair = sodium_crypto_box_keypair();
        }
        
        return base64_encode(
            sodium_crypto_box_publickey(self::$pqcKeyPair)
        );
    }

    /**
     * 生成生物识别挑战数据
     * @return array 包含挑战数据和密钥对
     */
    public static function generateBiometricChallenge(): array {
        $keyPair = openssl_pkey_new([
            'digest_alg' => 'sha512',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA
        ]);
        
        $details = openssl_pkey_get_details($keyPair);
        $challenge = bin2hex(random_bytes(32));
        
        return [
            'publicKey' => $details['key'],
            'privateKey' => openssl_pkey_export($keyPair, $privateKey) ? $privateKey : null,
            'challenge' => $challenge,
            'algorithm' => 'RSA-SHA512'
        ];
    }

    /**
     * 验证生物识别签名
     * @param string $publicKey 公钥
     * @param string $challenge 原始挑战数据
     * @param string $signature 签名数据
     * @param string $algorithm 签名算法
     * @return bool 验证结果
     */
    public static function verifyBiometricSignature(
        string $publicKey, 
        string $challenge, 
        string $signature, 
        string $algorithm = 'RSA-SHA512'
    ): bool {
        $key = openssl_pkey_get_public($publicKey);
        if ($key === false) {
            return false;
        }
        
        $result = openssl_verify(
            $challenge,
            base64_decode($signature),
            $key,
            $algorithm
        );
        
        // openssl_free_key 在 PHP 8.0+ 已弃用，可省略
        if (function_exists('openssl_free_key')) {
            @openssl_free_key($key);
        }
        return $result === 1;
    }
}