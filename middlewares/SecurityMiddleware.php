<?php
namespace Middlewares;

use Libs\CryptoHelper;
use Libs\SessionHelper;
use Exception;

class SecurityMiddleware {
    private $csrfTokenName = 'csrf_token';
    private $csrfHeaderName = 'X-CSRF-TOKEN';
    
    public function handle($request, $next) {
        // 验证CSRF令牌
        if (in_array($request['method'], ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $this->validateCsrfToken($request);
        }
        
        // 生成新的CSRF令牌
        $this->generateCsrfToken();
        
        return $next($request);
    }
    
    private function validateCsrfToken($request) {
        $token = $_SERVER['HTTP_'.$this->csrfHeaderName] ?? 
                ($request[$this->csrfTokenName] ?? null);
                
        if (!$token || !hash_equals(SessionHelper::get($this->csrfTokenName), $token)) {
            throw new Exception('无效的CSRF令牌', 403);
        }
    }
    
    private function generateCsrfToken() {
        if (!SessionHelper::has($this->csrfTokenName)) {
            SessionHelper::set(
                $this->csrfTokenName,
                CryptoHelper::generateToken(32)
            );
        }
    }
}