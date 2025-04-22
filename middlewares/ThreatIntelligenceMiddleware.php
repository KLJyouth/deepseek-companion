<?php
namespace Middlewares;

require_once __DIR__ . '/../libs/SecurityAuditHelper.php';
require_once __DIR__ . '/../libs/DatabaseHelper.php';

use Libs\SecurityAuditHelper;
use Libs\DatabaseHelper;

class ThreatIntelligenceMiddleware {
    private static $maliciousIPs = [];
    private static $torNodes = [];
    private static $lastUpdate = 0;

    public static function refreshIntel() {
        $redisCluster = new \RedisCluster(null, [$_ENV['REDIS_CLUSTER_NODES']], 2.5, 2.5, true, $_ENV['REDIS_PASSWORD']);
        
        // 从多源API获取情报数据
        $intelService = new \Services\ThreatIntelligenceService(
            $redisCluster,
            new \GuzzleHttp\Client(['verify' => false, 'timeout' => 10]),
            new \Monolog\Logger('threat_intel')
        );
        
        // 多源数据融合
        $multiSourceData = $intelService->fetchLatestThreats();
        $mergedIps = array_unique(array_merge(
            $multiSourceData['微步在线']['attack_patterns'] ?? [],
            $multiSourceData['VirusTotal']['attack_patterns'] ?? [],
            self::fetchFromAbuseIPDB(),
            self::fetchFromFireHOL()
        ));
        
        // 集群模式数据同步
        try {
            $redisCluster->pipeline()
                ->sAddArray('threat_ips', $mergedIps)
                ->expire('threat_ips', 86400)
                ->exec();
        } catch (\RedisException $e) {
            LogHelper::logError('Redis集群操作失败: ' . $e->getMessage());
        }
        
        // 更新内存缓存
        self::$maliciousIPs = $mergedIps;
        self::$lastUpdate = time();
    }

    public static function checkRequest($request) {
        $clientIP = $request->getClientIp();
        
        // 多级缓存检查策略
        if (in_array($clientIP, self::$maliciousIPs)) {
            return false;
        }
        
        try {
            $redisCluster = new \RedisCluster(null, [$_ENV['REDIS_CLUSTER_NODES']], 2.5, 2.5, true, $_ENV['REDIS_PASSWORD']);
            if ($redisCluster->sIsMember('threat_ips', $clientIP)) {
                self::$maliciousIPs[] = $clientIP;
                return false;
            }
        } catch (\RedisException $e) {
            LogHelper::logError('Redis集群连接失败: ' . $e->getMessage());
        }
        
        return true;
    }

    public static function getMaliciousIPs() {
        try {
            $redisCluster = new \RedisCluster(null, [$_ENV['REDIS_CLUSTER_NODES']], 2.5, 2.5, true, $_ENV['REDIS_PASSWORD']);
            $cachedIps = $redisCluster->sMembers('threat_ips');
            return array_unique(array_merge(
                $cachedIps ?: [],
                parent::getMaliciousIPs()
            ));
        } catch (\RedisException $e) {
            LogHelper::logError('Redis集群连接失败: ' . $e->getMessage());
            if (time() - self::$lastUpdate > 3600) {
                self::refreshIntel();
            }
            return self::$maliciousIPs;
        }
    }

    public static function getTorNodes() {
        if (time() - self::$lastUpdate > 3600) {
            self::refreshIntel();
        }
        return self::$torNodes;
    }

    private static function fetchFromAbuseIPDB() {
        $apiKey = getenv('ABUSEIPDB_KEY');
        $url = "https://api.abuseipdb.com/api/v2/blacklist?limit=1000";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Key: {$apiKey}",
            "Accept: application/json"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = json_decode(curl_exec($ch), true);
        curl_close($ch);
        
        return array_column($response['data'] ?? [], 'ipAddress');
    }

    private static function fetchFromFireHOL() {
        $url = "https://raw.githubusercontent.com/firehol/blocklist-ipsets/master/firehol_level1.netset";
        $ips = [];
        
        $lines = file($url, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (!preg_match('/^#/', $line) && filter_var($line, FILTER_VALIDATE_IP)) {
                $ips[] = $line;
            }
        }
        return $ips;
    }

    private static function fetchTorExitNodes() {
        $url = "https://check.torproject.org/torbulkexitlist";
        $response = file_get_contents($url);
        return array_filter(explode("\n", $response), function($ip) {
            return filter_var($ip, FILTER_VALIDATE_IP);
        });
    }
}