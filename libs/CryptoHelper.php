<?php
namespace Libs;

class CryptoHelper {
    private static $instance = null;
    private $encryptionKey;
    private $iv;
    
    private function __construct() {
        $this->encryptionKey = 'default_encryption_key'; // 应该从配置中获取
        $this->iv = 'default_initialization_vector'; // 应该从配置中获取
    }
    
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public static function init(string $key, string $iv): void {
        $instance = self::getInstance();
        $instance->encryptionKey = $key;
        $instance->iv = $iv;
    }
    
    public function encrypt(string $data): string {
        return openssl_encrypt(
            $data, 
            'AES-256-CBC', 
            $this->encryptionKey, 
            0, 
            $this->iv
        );
    }
    
    public function decrypt(string $data): string {
        return openssl_decrypt(
            $data, 
            'AES-256-CBC', 
            $this->encryptionKey, 
            0, 
            $this->iv
        );
    }
    
    public function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }
    
    public function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_BCRYPT);
    }
    
    public function generateToken(int $length = 32): string {
        return bin2hex(random_bytes($length));
    }
    
    public function validateCsrfToken(string $token): bool {
        return hash_equals($_SESSION['csrf_token'] ?? '', $token);
    }
    
    public function customEncode(string $data): string {
        return base64_encode($data);
    }
    
    public function customDecode(string $data): string {
        return base64_decode($data);
    }
    
    public function generateSignature(string $data): string {
        return hash_hmac('sha256', $data, $this->encryptionKey);
    }
    
    public function verifyBiometricSignature(string $data, string $signature, string $publicKey): bool {
        return openssl_verify($data, $signature, $publicKey, OPENSSL_ALGO_SHA256) === 1;
    }
}