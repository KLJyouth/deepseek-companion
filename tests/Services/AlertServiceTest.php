<?php
namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Services\AlertService;
use RedisException;

class AlertServiceTest extends TestCase
{
    private AlertService $alertService;

    protected function setUp(): void
    {
        $this->alertService = new AlertService(
            host: 'localhost',
            port: 6379,
            timeout: 0.5
        );
    }

    public function testCheckThresholds(): void
    {
        $metrics = [
            'connection_wait' => 250,
            'pool_usage' => 85,
            'query_time' => 1200,
            'error_rate' => 0.05
        ];

        $alerts = $this->alertService->checkMetrics($metrics);
        
        $this->assertCount(4, $alerts, '应触发全部阈值告警');
        $this->assertEquals('connection_wait', $alerts[0]['metric']);
    }

    public function testRedisConnectionFailure(): void
    {
        $this->expectException(RedisException::class);
        new AlertService(host: 'invalid_host');
    }

    protected function tearDown(): void
    {
        unset($this->alertService);
    }
}