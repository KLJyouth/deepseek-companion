<?php
namespace Libs;

/**
 * 速率限制器类
 * 基于IP和用户ID的请求频率控制
 */
class RateLimiter {
    private $dbHelper;
    private $maxRequests;
    private $timeWindow;
    
    public function __construct($dbHelper, $maxRequests = 100, $timeWindow = 3600) {
        $this->dbHelper = $dbHelper;
        $this->maxRequests = $maxRequests;
        $this->timeWindow = $timeWindow;
    }
    
    /**
     * 检查请求频率
     */
    public function checkRateLimit($identifier) {
        $now = time();
        $windowStart = $now - $this->timeWindow;
        
        // 获取窗口期内的请求数
        $count = $this->dbHelper->getValue(
            "SELECT COUNT(*) FROM rate_limits 
             WHERE identifier = ? AND timestamp >= ?",
            [
                ['value' => $identifier, 'encrypt' => false],
                ['value' => $windowStart, 'type' => 'i']
            ]
        );
        
        // 记录当前请求
        $this->dbHelper->insert('rate_limits', [
            'identifier' => $identifier,
            'timestamp' => $now
        ]);
        
        // 清理过期记录
        $this->cleanupExpired();
        
        // 检查是否超限
        if ($count >= $this->maxRequests) {
            $retryAfter = $this->timeWindow - ($now - $this->getOldestTimestamp($identifier));
            throw new Exception("请求过于频繁，请 {$retryAfter} 秒后再试");
        }
        
        return [
            'remaining' => $this->maxRequests - $count - 1,
            'reset' => $windowStart + $this->timeWindow
        ];
    }
    
    /**
     * 获取标识符的最早请求时间
     */
    private function getOldestTimestamp($identifier) {
        return $this->dbHelper->getValue(
            "SELECT MIN(timestamp) FROM rate_limits 
             WHERE identifier = ?",
            [['value' => $identifier, 'encrypt' => false]]
        );
    }
    
    /**
     * 清理过期记录
     */
    private function cleanupExpired() {
        $windowStart = time() - $this->timeWindow;
        $this->dbHelper->execute(
            "DELETE FROM rate_limits WHERE timestamp < ?",
            [['value' => $windowStart, 'type' => 'i']]
        );
    }
    
    /**
     * 生成限速标识符
     */
    public static function getIdentifier($userId = null) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if ($userId) {
            return "user_{$userId}";
        } else {
            return "ip_{$ip}";
        }
    }
}
