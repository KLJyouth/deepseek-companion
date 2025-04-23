<?php
namespace Middlewares;

use Security\Path\PathEncryptionService;
use Security\Blockchain\BlockchainService;
use Exception;

class PathValidationMiddleware {
    private $encryptionService;
    private $blockchainService;
    
    public function __construct() {
        $this->encryptionService = new PathEncryptionService();
        $this->blockchainService = new BlockchainService();
    }
    
    public function handle($request, $next) {
        // 验证所有传入的文件路径参数
        $this->validateRequestPaths($request);
        
        // 验证服务器端文件访问
        $this->validateServerPaths($request);
        
        return $next($request);
    }
    
    private function validateRequestPaths($request) {
        foreach ($request->all() as $key => $value) {
            if ($this->isPotentialPath($value)) {
                $this->validateSinglePath($value, 'REQUEST_PARAM:'.$key);
            }
        }
    }
    
    private function validateServerPaths($request) {
        $serverPaths = [
            'SCRIPT_FILENAME' => $_SERVER['SCRIPT_FILENAME'] ?? null,
            'PHP_SELF' => $_SERVER['PHP_SELF'] ?? null,
            'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? null
        ];
        
        foreach ($serverPaths as $type => $path) {
            if ($path && $this->isPotentialPath($path)) {
                $this->validateSinglePath($path, 'SERVER_'.$type);
            }
        }
    }
    
    private function validateSinglePath($path, $context) {
        try {
            // 提取签名和加密路径 (假设格式为 "encryptedPath|signature")
            $parts = explode('|', $path);
            if (count($parts) === 2) {
                [$encryptedPath, $signature] = $parts;
                
                if (!$this->encryptionService->verifyPath($encryptedPath, $signature)) {
                    $this->blockchainService->logOperation(
                        'PATH_VALIDATION_FAILED',
                        $path,
                        'INVALID_SIGNATURE'
                    );
                    throw new Exception('Invalid path signature');
                }
            } else {
                // 记录未加密路径访问尝试
                $this->blockchainService->logOperation(
                    'UNENCRYPTED_PATH_ACCESS',
                    $path,
                    $context
                );
                throw new Exception('Unencrypted path access detected');
            }
        } catch (Exception $e) {
            // 记录到安全事件系统
            $this->blockchainService->logSecurityEvent(
                'PATH_VALIDATION_ERROR',
                [
                    'path' => $path,
                    'error' => $e->getMessage(),
                    'context' => $context
                ]
            );
            throw $e;
        }
    }
    
    private function isPotentialPath($value): bool {
        return is_string($value) && 
              (strpos($value, '/') !== false || strpos($value, '..') !== false);
    }
}