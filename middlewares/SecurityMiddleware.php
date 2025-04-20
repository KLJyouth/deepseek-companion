<?php
namespace Middlewares;

use Libs\CryptoHelper;
use Libs\SessionHelper;
use Exception;

class SecurityMiddleware {
    private $csrfTokenName = 'csrf_token';
    private $csrfHeaderName = 'X-CSRF-TOKEN';
    private $securityManager;
    private $headers = [
        'X-Frame-Options' => 'DENY',
        'X-XSS-Protection' => '1; mode=block',
        'X-Content-Type-Options' => 'nosniff',
        'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline';",
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()'
    ];

    public function __construct() {
        $this->securityManager = \Libs\SecurityManager::getInstance();
    }

    public function handle($request, $next) {
        // 验证CSRF令牌
        if (in_array($request['method'], ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $this->validateCsrfToken($request);
        }
        
        // 生成新的CSRF令牌
        $this->generateCsrfToken();
        
        $this->setSecurityHeaders();
        $this->validateRequest();
        $this->enforceHttps();
        
        return $next($request);
    }
    
    private function validateCsrfToken($request) {
        $token = $_SERVER['HTTP_'.$this->csrfHeaderName] ?? 
                ($request[$this->csrfTokenName] ?? null);
                
        $session = SessionHelper::getInstance();
        if (!$token || !hash_equals($session->get($this->csrfTokenName), $token)) {
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

    private function setSecurityHeaders() {
        foreach ($this->headers as $header => $value) {
            header("$header: $value");
        }
    }

    private function validateRequest() {
        if (!$this->securityManager->validateRequest($_REQUEST)) {
            throw new \Exception("检测到潜在安全威胁");
        }
    }

    private function enforceHttps() {
        if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
            header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
            exit;
        }
    }
}