<?php
namespace Middlewares;

use Services\RateLimitService;
use Libs\ResponseHelper;

class RateLimitMiddleware {
    private $rateLimitService;
    
    public function __construct() {
        $this->rateLimitService = new RateLimitService();
    }
    
    public function handle($request, $next) {
        // 基于IP的限制
        $ip = $_SERVER['REMOTE_ADDR'];
        $ipLimit = $this->rateLimitService->check($ip, 'ip');
        
        // 基于用户的限制(如果已登录)
        $userId = $_SESSION['user_id'] ?? null;
        if ($userId) {
            $userLimit = $this->rateLimitService->check($userId, 'user');
            $this->addRateLimitHeaders($userLimit);
            
            if ($userLimit['remaining'] <= 0) {
                return ResponseHelper::error(429, '请求过于频繁，请稍后再试');
            }
        }
        
        $this->addRateLimitHeaders($ipLimit);
        
        if ($ipLimit['remaining'] <= 0) {
            return ResponseHelper::error(429, '请求过于频繁，请稍后再试');
        }
        
        return $next($request);
    }
    
    private function addRateLimitHeaders(array $limit): void {
        header("X-RateLimit-Limit: {$limit['limit']}");
        header("X-RateLimit-Remaining: {$limit['remaining']}");
        header("X-RateLimit-Reset: {$limit['reset']}");
    }

    /**
     * 检查速率限制
     * @param string $action 操作标识
     * @throws \Exception
     */
    public static function check(string $action)
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = "ratelimit:{$action}:{$ip}";
        $limit = 30; // 每分钟30次
        $window = 60;

        if (!function_exists('apcu_fetch')) return; // 若无APCu则跳过

        $data = apcu_fetch($key);
        $now = time();
        if (!$data || $data['expires'] < $now) {
            $data = ['count' => 1, 'expires' => $now + $window];
        } else {
            $data['count']++;
        }
        apcu_store($key, $data, $window);

        if ($data['count'] > $limit) {
            throw new \Exception('操作过于频繁，请稍后再试');
        }
    }
}