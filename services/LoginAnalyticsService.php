<?php
namespace Services;

use Libs\DatabaseHelper;
use Services\DeviceManagementService;

class LoginAnalyticsService {
    private $db;
    private $deviceService;
    
    public function __construct(DatabaseHelper $db, DeviceManagementService $deviceService) {
        $this->db = $db;
        $this->deviceService = $deviceService;
    }
    
    public function analyzeLoginPattern($userId, $location) {
        $patterns = $this->db->getRows(
            "SELECT created_at as login_time, ip_address, location_country, location_city 
             FROM login_attempts 
             WHERE user_id = ? AND success = 1 
             ORDER BY created_at DESC LIMIT 10",
            [['value' => $userId, 'type' => 'i']]
        );
        
        $riskScore = 0;
        $riskFactors = [];
        
        // 分析登录时间模式
        if ($this->isUnusualLoginTime($patterns)) {
            $riskScore += 30;
            $riskFactors[] = 'unusual_time';
        }
        
        // 分析地理位置跳变
        if ($this->isLocationJump($patterns, $location)) {
            $riskScore += 50;
            $riskFactors[] = 'location_jump';
        }
        
        return [
            'risk_score' => $riskScore,
            'risk_factors' => $riskFactors
        ];
    }
    
    private function isUnusualLoginTime($patterns) {
        // 简单实现：如果最近一次登录时间与历史平均时间段偏差较大
        if (count($patterns) < 2) return false;
        $times = array_map(fn($p) => strtotime($p['login_time']), $patterns);
        $avg = array_sum($times) / count($times);
        $last = $times[0];
        return abs($last - $avg) > 4 * 3600; // 超过4小时偏差
    }
    
    private function isLocationJump($patterns, $currentLocation) {
        // 简单实现：如果当前地理位置与历史位置不同
        if (empty($currentLocation['location_country']) || empty($currentLocation['location_city'])) return false;
        foreach ($patterns as $p) {
            if ($p['location_country'] === $currentLocation['location_country'] &&
                $p['location_city'] === $currentLocation['location_city']) {
                return false;
            }
        }
        return true;
    }
}
