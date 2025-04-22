<?php
declare(strict_types=1);

namespace Middlewares;

use Services\RateLimitService;
use Libs\ResponseHelper;
use Libs\SessionHelper;
use Exception;

class RateLimitMiddleware {
    private RateLimitService $rateLimitService;
    
    public function __construct(RateLimitService $rateLimitService) {
        $this->rateLimitService = $rateLimitService;
    }
    
    public function handle(object $request, callable $next): mixed {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $sessionId = SessionHelper::getId();
            
            // 检查IP限制
            $ipLimit = $this->rateLimitService->check($ip, 'ip');
            $this->addRateLimitHeaders($ipLimit);
            
            if ($ipLimit['remaining'] <= 0) {
                return ResponseHelper::error(429, '请求过于频繁，请稍后再试');
            }

            // 检查会话限制（如果存在会话）
            if ($sessionId) {
                $sessionLimit = $this->rateLimitService->check($sessionId, 'session');
                $this->addRateLimitHeaders($sessionLimit);
                
                if ($sessionLimit['remaining'] <= 0) {
                    return ResponseHelper::error(429, '请求过于频繁，请稍后再试');
                }
            }

            return $next($request);
        } catch (Exception $e) {
            return ResponseHelper::error(500, '服务器内部错误');
        }
    }
    
    private function addRateLimitHeaders(array $limit): void {
        header("X-RateLimit-Limit: {$limit['limit']}");
        header("X-RateLimit-Remaining: {$limit['remaining']}");
        header("X-RateLimit-Reset: {$limit['reset']}");
        header("X-RateLimit-Policy: {$limit['policy']}");
    }
}
