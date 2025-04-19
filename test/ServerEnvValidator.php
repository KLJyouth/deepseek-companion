<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../libs/DatabaseHelper.php';
require_once __DIR__ . '/../libs/CryptoHelper.php';

/**
 * 服务器环境验证测试套件
 */
class ServerEnvValidator {
    const REQUIRED_PHP_VERSION = '7.4';
    const REQUIRED_EXTENSIONS = ['mysqli', 'redis', 'memcached', 'mbstring'];

    public function runFullTest() {
        $results = [
            'PHP版本' => $this->testPHPVersion(),
            'PHP扩展' => $this->testPHPExtensions(),
            'MySQL连接' => $this->testMySQLConnection(),
            'Redis连接' => $this->testRedisConnection(),
            'Memcached连接' => $this->testMemcachedConnection(),
            '加密功能' => $this->testEncryption(),
            '文件权限' => $this->testFilePermissions()
        ];

        $this->logResults($results);
        return $results;
    }

    private function testPHPVersion() {
        return version_compare(PHP_VERSION, self::REQUIRED_PHP_VERSION, '>=')
            ? ['status' => '通过', '当前版本' => PHP_VERSION]
            : ['status' => '失败', '当前版本' => PHP_VERSION];
    }

    private function testPHPExtensions() {
        $missing = array_filter(self::REQUIRED_EXTENSIONS, fn($ext) => !extension_loaded($ext));
        return empty($missing)
            ? ['status' => '通过']
            : ['status' => '失败', '缺失扩展' => $missing];
    }

    private function testMySQLConnection() {
        try {
            $db = DatabaseHelper::getInstance();
            return $db->testConnection()
                ? ['status' => '通过']
                : ['status' => '失败', '错误' => '连接测试失败'];
        } catch (Exception $e) {
            return ['status' => '失败', '错误' => $e->getMessage()];
        }
    }

    private function testRedisConnection() {
        try {
            $redis = new Redis();
            if ($redis->connect(REDIS_HOST, REDIS_PORT, 2)) {
                $redis->close();
                return ['status' => '通过'];
            }
            return ['status' => '失败', '错误' => '连接超时'];
        } catch (Exception $e) {
            return ['status' => '失败', '错误' => $e->getMessage()];
        }
    }

    private function testMemcachedConnection() {
        try {
            $mem = new Memcached();
            $mem->addServer(MEMCACHED_HOST, MEMCACHED_PORT);
            return $mem->getStats()
                ? ['status' => '通过']
                : ['status' => '失败', '错误' => '无法获取状态'];
        } catch (Exception $e) {
            return ['status' => '失败', '错误' => $e->getMessage()];
        }
    }

    private function testEncryption() {
        try {
            $testString = 'env_test_' . time();
            $encrypted = CryptoHelper::encrypt($testString);
            $decrypted = CryptoHelper::decrypt($encrypted);
            return ($decrypted === $testString)
                ? ['status' => '通过']
                : ['status' => '失败', '错误' => '解密不匹配'];
        } catch (Exception $e) {
            return ['status' => '失败', '错误' => $e->getMessage()];
        }
    }

    private function testFilePermissions() {
        $requiredWritable = [
            'logs' => 0755,
            'tmp' => 0777
        ];

        $results = [];
        foreach ($requiredWritable as $dir => $perm) {
            $path = __DIR__ . "/../$dir";
            $isWritable = is_writable($path);
            $currentPerm = decoct(fileperms($path) & 0777);
            $results[$dir] = $isWritable 
                ? ['status' => '通过', '权限' => $currentPerm]
                : ['status' => '失败', '当前权限' => $currentPerm];
        }
        return $results;
    }

    private function logResults($results) {
        $logContent = date('[Y-m-d H:i:s] ') . "环境测试结果：\n";
        foreach ($results as $testName => $result) {
            $logContent .= "$testName: " . $result['status'] . "\n";
            if (isset($result['错误'])) {
                $logContent .= "\t错误信息: " . $result['错误'] . "\n";
            }
        }
        file_put_contents(__DIR__ . '/../logs/env_test.log', $logContent, FILE_APPEND);
    }
}

// 执行测试
if (php_sapi_name() === 'cli') {
    $validator = new ServerEnvValidator();
    print_r($validator->runFullTest());
}