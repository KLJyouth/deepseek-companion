<?php
namespace Libs;

require_once __DIR__ . '/DatabaseHelper.php';

class SecurityAuditHelper {
    const RISK_LEVELS = [
        'critical' => 4,
        'high' => 3,
        'medium' => 2,
        'low' => 1
    ];

    public static function auditConfiguration() {
        $report = [
            'ssl_validation' => self::checkSSLConfig(),
            'encryption' => self::checkEncryptionConfig(),
            'session_security' => self::checkSessionSecurity()
        ];
        return array_merge($report, self::scanDirectoryPermissions());
    }

    private static function checkSSLConfig() {
        return [
            'valid_cert' => file_exists(__DIR__.'/ssl/ca-cert.pem'),
            'protocol_version' => OPENSSL_VERSION_TEXT,
            'weak_ciphers' => self::detectWeakCiphers()
        ];
    }

    private static function checkEncryptionConfig() {
        return [
            'key_length' => strlen(ENCRYPTION_KEY)*4,
            'iv_reuse' => ENCRYPTION_IV === bin2hex(random_bytes(8)),
            'method_strength' => in_array(ENCRYPTION_METHOD, openssl_get_cipher_methods())
        ];
    }

    private static function checkSessionSecurity() {
        return [
            'cookie_httponly' => (bool)ini_get('session.cookie_httponly'),
            'cookie_secure' => (bool)ini_get('session.cookie_secure'),
            'use_strict_mode' => (bool)ini_get('session.use_strict_mode')
        ];
    }

    public static function scanVulnerabilities($code) {
        $patterns = [
            '/eval\s*\(/i' => 'critical',
            '/\$_(GET|POST|REQUEST)\s*\[/' => 'high',
            '/mysql_query\s*\(/' => 'high',
            '/password\s*=\s*\$_(POST|GET)/' => 'medium'
        ];

        $findings = [];
        foreach ($patterns as $pattern => $level) {
            if (preg_match_all($pattern, $code, $matches)) {
                $findings[] = [
                    'risk' => $level,
                    'pattern' => $pattern,
                    'matches' => array_unique($matches[0])
                ];
            }
        }
        return $findings;
    }

    private static function scanDirectoryPermissions() {
        $report = [];
        foreach (REQUIRED_DIRS as $dir => $expected) {
            $current = substr(sprintf('%o', fileperms($dir)), -4);
            $report[$dir] = [
                'expected' => $expected,
                'actual' => $current,
                'compliant' => $current === $expected
            ];
        }
        return ['directory_permissions' => $report];
    }

    private static function detectWeakCiphers() {
        $weak = ['RC4', 'MD5', 'DES', 'SSLv3'];
        return array_filter($weak, function($cipher) {
            return stripos(OPENSSL_VERSION_TEXT, $cipher) !== false;
        });
    }

    public static function logSecurityEvent(string $eventType, ?int $userId = null, array $eventData = []) {
        $db = DatabaseHelper::getInstance();
        $db->insert('security_logs', [
            'event_type' => $eventType,
            'user_id' => $userId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'event_data' => json_encode($eventData),
            'created_at' => date('Y-m-d H:i:s')
        ]);

        // 高风险事件实时告警
        if (in_array($eventType, ['AUTH_FAILURE', 'SESSION_HIJACK'])) {
            error_log("Security Alert [{$eventType}] - UserID: " . ($userId ?? 'null'));
        }
    }
}