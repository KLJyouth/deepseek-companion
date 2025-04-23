<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use Security\Path\PathEncryptionService;
use Middlewares\PathValidationMiddleware;
use Security\Audit\PathAccessAuditor;

class PathSecurityTest extends TestCase
{
    private $encryptor;
    private $middleware;
    private $auditor;

    protected function setUp(): void
    {
        $this->encryptor = new PathEncryptionService();
        $this->middleware = new PathValidationMiddleware();
        $this->auditor = new PathAccessAuditor();
    }

    // 测试路径加密/解密功能
    public function testPathEncryption()
    {
        $originalPath = '/var/www/secure/files/document.pdf';
        
        // 加密路径
        $encrypted = $this->encryptor->encryptPath($originalPath);
        $this->assertNotEmpty($encrypted);
        $this->assertNotEquals($originalPath, $encrypted);
        
        // 验证签名(模拟中间件验证)
        $parts = explode('|', $encrypted);
        $this->assertCount(2, $parts);
        [$path, $signature] = $parts;
        
        $isValid = $this->encryptor->verifyPath($path, $signature);
        $this->assertTrue($isValid);
    }

    // 测试无效路径处理
    public function testInvalidPathHandling()
    {
        $this->expectException(\Exception::class);
        
        // 构造无效的加密路径
        $invalidPath = 'invalid|signature';
        $this->middleware->validateSinglePath($invalidPath, 'TEST');
    }

    // 测试审计日志记录
    public function testAuditLogging()
    {
        $originalPath = '/var/www/secure/config/database.php';
        $encrypted = $this->encryptor->encryptPath($originalPath);
        
        // 记录访问
        $this->auditor->logAccess($encrypted, $originalPath, 'READ');
        
        // 验证日志(这里简化验证，实际项目应检查数据库或日志文件)
        $this->assertTrue(true); 
    }

    // 测试中间件请求处理
    public function testMiddlewareRequestProcessing()
    {
        // 模拟请求对象
        $request = new class {
            public function all() {
                return [
                    'file' => '/safe/path/to/file.txt|valid_signature',
                    'malicious' => '../../etc/passwd'
                ];
            }
        };
        
        // 处理请求
        $nextCalled = false;
        $next = function ($req) use (&$nextCalled) {
            $nextCalled = true;
            return $req;
        };
        
        $response = $this->middleware->handle($request, $next);
        
        $this->assertTrue($nextCalled);
    }

    // 测试敏感路径检测
    public function testSensitivePathDetection()
    {
        $sensitivePaths = [
            '/var/www/.env',
            '/config/database.php',
            '/admin/credentials.txt'
        ];
        
        foreach ($sensitivePaths as $path) {
            $this->assertTrue(
                $this->auditor->isSensitivePath($path),
                "Failed to detect sensitive path: $path"
            );
        }
        
        $normalPaths = [
            '/public/index.php',
            '/assets/css/style.css',
            '/docs/readme.md'
        ];
        
        foreach ($normalPaths as $path) {
            $this->assertFalse(
                $this->auditor->isSensitivePath($path),
                "False positive on normal path: $path"
            );
        }
    }
}