<?php

namespace Services;

use GeoIp2\Database\Reader;
use Libs\DatabaseHelper;
use Exception;

class DeviceManagementService {
    private $dbHelper;
    private $geoipReader;
    
    public function __construct(DatabaseHelper $dbHelper) {
        $this->dbHelper = $dbHelper;
        $this->geoipReader = new Reader(__DIR__ . '/../data/GeoLite2-City.mmdb');
    }

    public function generateDeviceFingerprint(): string {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $acceptLang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        $screenRes = $_POST['screen_resolution'] ?? '';
        
        return hash('sha256', $userAgent . $acceptLang . $screenRes);
    }

    public function validateDeviceFingerprint(string $username, string $fingerprint): bool {
        $knownDevices = $this->dbHelper->getRows(
            "SELECT fingerprint FROM user_devices WHERE username = ? AND trusted = 1",
            [['value' => $username, 'encrypt' => false]]
        );
        
        return in_array($fingerprint, array_column($knownDevices, 'fingerprint'));
    }

    public function getLocationFromIP(string $ip): array {
        try {
            $record = $this->geoipReader->city($ip);
            return [
                'country' => $record->country->name,
                'city' => $record->city->name,
                'latitude' => $record->location->latitude,
                'longitude' => $record->location->longitude
            ];
        } catch (Exception $e) {
            return [];
        }
    }

    public function isLocationSuspicious(string $username, array $location): bool {
        if (empty($location)) {
            return false;
        }

        $lastLogin = $this->dbHelper->getRow(
            "SELECT location_country, location_city FROM login_attempts 
             WHERE username = ? AND success = 1 
             ORDER BY attempt_time DESC LIMIT 1",
            [['value' => $username, 'encrypt' => false]]
        );

        return $lastLogin && 
               ($lastLogin['location_country'] !== $location['country'] ||
                $lastLogin['location_city'] !== $location['city']);
    }

    public function sendUnknownDeviceAlert(string $username): void {
        // 实现发送邮件通知的逻辑
        $user = $this->dbHelper->getRow(
            "SELECT email FROM users WHERE username = ?",
            [['value' => $username, 'encrypt' => false]]
        );
        
        if ($user && $user['email']) {
            // TODO: 实现邮件发送逻辑
            mail(
                $user['email'],
                '检测到新设备登录',
                "您的账户在新设备上有登录尝试。如果这不是您本人操作，请立即修改密码。"
            );
        }
    }
}
