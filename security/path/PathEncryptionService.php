<?php
namespace Security\Path;

use Security\Quantum\QuantumCryptoService;
use Security\Blockchain\BlockchainService;

class PathEncryptionService {
    private $quantumService;
    private $blockchainService;
    private $dynamicSalt;
    
    public function __construct() {
        $this->quantumService = new QuantumCryptoService();
        $this->blockchainService = new BlockchainService();
        $this->dynamicSalt = $this->generateDynamicSalt();
    }
    
    /**
     * 加密文件路径
     */
    public function encryptPath(string $originalPath): string {
        $timestamp = microtime(true);
        $pathHash = $this->quantumService->encrypt(
            $originalPath . $this->dynamicSalt . $timestamp
        );
        
        // 记录到区块链
        $this->blockchainService->logOperation(
            'PATH_ENCRYPT',
            $originalPath,
            $pathHash
        );
        
        return base64_encode($pathHash);
    }
    
    /**
     * 验证路径签名
     */
    public function verifyPath(string $encryptedPath, string $signature): bool {
        $decoded = base64_decode($encryptedPath);
        $isValid = $this->quantumService->verify($decoded, $signature);
        
        if ($isValid) {
            $this->blockchainService->logOperation(
                'PATH_VERIFY',
                $encryptedPath,
                'SUCCESS'
            );
        }
        
        return $isValid;
    }
    
    private function generateDynamicSalt(): string {
        return bin2hex(random_bytes(32)) . time();
    }
    
    /**
     * 获取当前动态盐值(仅供内部使用)
     */
    public function getCurrentSalt(): string {
        return $this->dynamicSalt;
    }
}