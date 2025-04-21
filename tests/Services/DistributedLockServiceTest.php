<?php
namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Services\DistributedLockService;
use RedisException;

class DistributedLockServiceTest extends TestCase
{
    private DistributedLockService $lockService;

    protected function setUp(): void
    {
        $this->lockService = new DistributedLockService(
            host: 'localhost',
            port: 6379,
            timeout: 0.5
        );
    }

    public function testLockAcquisitionAndRelease(): void
    {
        $resource = 'test_resource';
        
        $this->assertTrue($this->lockService->acquire($resource, 5000));
        $this->assertTrue($this->lockService->release($resource));
    }

    public function testConcurrentLockPrevention(): void
    {
        $resource = 'concurrent_resource';
        
        // 第一次获取应该成功
        $this->assertTrue($this->lockService->acquire($resource));
        
        // 第二次尝试获取应该失败
        $this->assertFalse($this->lockService->acquire($resource, 100));
        
        // 释放后可以重新获取
        $this->assertTrue($this->lockService->release($resource));
        $this->assertTrue($this->lockService->acquire($resource));
    }

    public function testRedisConnectionFailure(): void
    {
        $this->expectException(RedisException::class);
        new DistributedLockService(host: 'invalid_host');
    }

    public function testLockExpiration(): void
    {
        $resource = 'expiring_resource';
        $this->assertTrue($this->lockService->acquire($resource, 100));
        usleep(200000);
        $this->assertTrue($this->lockService->acquire($resource));
    }

    public function testReleaseWithInvalidToken(): void
    {
        $resource = 'token_validation_resource';
        $this->assertTrue($this->lockService->acquire($resource));
        $this->lockService->release($resource);
        $this->assertFalse($this->lockService->release($resource));
    }

    protected function tearDown(): void
    {
        // 清理测试用的锁
        $this->lockService->release('test_resource');
        $this->lockService->release('concurrent_resource');
    }
}