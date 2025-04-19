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
        // 从可信威胁情报源获取数据
        self::$maliciousIPs = array_merge(
            self::fetchFromAbuseIPDB(),
            self::fetchFromFireHOL()
        );
        
        // 获取Tor出口节点列表
        self::$torNodes = self::fetchTorExitNodes();
        
        // 存储到安全事件表
        $db = DatabaseHelper::getInstance();
        $db->logAudit('threat_intel', 0, [
            'updated_ips' => count(self::$maliciousIPs),
            'updated_tor_nodes' => count(self::$torNodes)
        ]);
        
        self::$lastUpdate = time();
    }

    public static function getMaliciousIPs() {
        if (time() - self::$lastUpdate > 3600) {
            self::refreshIntel();
        }
        return self::$maliciousIPs;
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