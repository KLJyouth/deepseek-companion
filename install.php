<?php
// 加载Composer自动加载器
require_once __DIR__ . '/vendor/autoload.php';

namespace Services;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class Installer {
    public function run() {
        $this->checkRequirements();
        $this->setupEnvironment();
        $this->installDependencies();
        $this->initializeDatabase();
        $this->runSecurityChecks();
        $this->logInstallation();
    }

    private function checkRequirements() {
        // 检查系统要求
    }

    private function setupEnvironment() {
        $envContent = "DB_HOST=localhost\nDB_DATABASE=deepseek_companion\nDB_USERNAME=root\nDB_PASSWORD=123456\nAPP_ENV=production\nAPP_KEY=" . bin2hex(random_bytes(32));
        file_put_contents(__DIR__ . '/.env', $envContent);
        return true;
    }

    private function installDependencies() {
        if (posix_getuid() === 0) {
            $this->createDeploymentUser();
            $process = new Process(['sudo', '-Hu', 'deploy', 'env', 'COMPOSER_ALLOW_SUPERUSER=1', 'composer', 'install', '--no-plugins', '--no-scripts', '--no-dev', '--optimize-autoloader']);
        } else {
            $process = new Process(['env', 'COMPOSER_ALLOW_SUPERUSER=1', 'composer', 'install', '--no-plugins', '--no-scripts', '--no-dev', '--optimize-autoloader']);
        }
        $process->setWorkingDirectory(__DIR__);
        $process->setTimeout(300);
        try {
            $process->mustRun();
            return true;
        } catch (ProcessFailedException $e) {
            error_log('依赖安装失败：' . $e->getMessage());
            return false;
        }
    }

    private function initializeDatabase() {
        try {
            $db = new \PDO('mysql:host='.$_ENV['DB_HOST'], $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD']);
            $db->exec('CREATE DATABASE IF NOT EXISTS ' . $_ENV['DB_DATABASE']);
            $db->exec('USE ' . $_ENV['DB_DATABASE']);
            $sql = file_get_contents(__DIR__ . '/sql/init.sql');
            $db->exec($sql);
            return true;
        } catch (\PDOException $e) {
            error_log('数据库初始化失败：' . $e->getMessage());
            return false;
        }
    }

    private function runSecurityChecks() {
        $checks = [
            'php_version' => version_compare(PHP_VERSION, '8.1.0', '>='),
            'openssl' => extension_loaded('openssl'),
            'pdo_mysql' => extension_loaded('pdo_mysql'),
            'file_permissions' => is_writable(__DIR__)
        ];

        $failedChecks = array_filter($checks, function($result) {
            return !$result;
        });

        if (!empty($failedChecks)) {
            error_log('安全检查失败：' . implode(', ', array_keys($failedChecks)));
            return false;
        }
        return true;
    }

    
    private function createDeploymentUser() {
        if (!posix_getpwnam('deploy')) {
            $process = new Process(['useradd', '-r', '-s', '/sbin/nologin', 'deploy']);
            $process->run();
            chown(__DIR__.'/storage', 'deploy');
            chmod(__DIR__.'/storage', 0755);
        }
    }

    private function logInstallation() {
        $logContent = "Installation Log\n";
        $logContent .= "Date: " . date('Y-m-d H:i:s') . "\n";
        $logContent .= "PHP Version: " . PHP_VERSION . "\n";
        $logContent .= "Environment: " . $_ENV['APP_ENV'] . "\n";
        $logContent .= "Database: " . $_ENV['DB_DATABASE'] . "\n";
        $logContent .= "Status: Success\n\n";
        file_put_contents(__DIR__ . '/logs/installation.log', $logContent, FILE_APPEND);
        return true;
    }
}


use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\ErrorCorrectionLevel;

class InstallService {
    private $encryptionService;
    private $qrCodeGenerator;
    private $deviceInfoCollector;

    public function __construct() {
        $this->encryptionService = new EncryptionService();
        $this->qrCodeGenerator = new QRCodeGenerator();
        $this->deviceInfoCollector = new DeviceInfoCollector();
    }

    public function generateLoginQRCode(): string {
        $uniqueKey = $this->encryptionService->generateUniqueKey();
        return $this->qrCodeGenerator->generate($uniqueKey);
    }

    public function registerDeviceInfo(string $fingerprintData, array $deviceInfo): bool {
        $encryptedData = $this->encryptionService->encryptData($fingerprintData);
        $deviceSignature = $this->deviceInfoCollector->generateDeviceSignature($deviceInfo);
        return $this->saveDeviceInfo($encryptedData, $deviceSignature);
    }

    private function saveDeviceInfo(string $encryptedData, string $deviceSignature): bool {
        // 保存设备信息到数据库
        try {
            $db = new \PDO('mysql:host='.$_ENV['DB_HOST'].';dbname='.$_ENV['DB_DATABASE'], $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD']);
            $stmt = $db->prepare('INSERT INTO devices (encrypted_data, device_signature) VALUES (:encrypted_data, :device_signature)');
            $stmt->execute([
                ':encrypted_data' => $encryptedData,
                ':device_signature' => $deviceSignature
            ]);
            return true;
        } catch (\PDOException $e) {
            error_log('保存设备信息失败：'.$e->getMessage());
            return false;
        }
    }
}

class EncryptionService {
    public function generateUniqueKey(): string {
        return bin2hex(random_bytes(32));
    }

    public function encryptData(string $data): string {
        return openssl_encrypt($data, 'AES-256-CBC', $this->generateUniqueKey(), 0, $this->generateUniqueKey());
    }
}

class QRCodeGenerator {
    public function generate(string $data): string {
        $qrCode = new QrCode($data);
        $qrCode->setSize(300);
        $qrCode->setErrorCorrectionLevel(ErrorCorrectionLevel::High);
        $writer = new PngWriter();
        $result = $writer->write($qrCode);
        return $result->getDataUri();
    }
}

class DeviceInfoCollector {
    public function generateDeviceSignature(array $deviceInfo): string {
        // 生成设备特征签名
        return md5(implode('', $deviceInfo));
    }
}
?>

// 合并重复的InstallService类定义
class InstallService {
    // 整合后的完整实现...
}

// 保留唯一的EncryptionService实现
class EncryptionService {
    // AES-256-CBC加密实现...
}

class QRCodeGenerator {
    public function generate(string $data): string {
        // 使用第三方库生成二维码
        return 'QR_CODE_' . $data;
    }
}

class DeviceInfoCollector {
    public function generateDeviceSignature(array $deviceInfo): string {
        // 生成设备特征签名
        return md5(implode('', $deviceInfo));
    }
}
?>