<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use Services\ContractService;
use Models\Contract;

class ContractServiceTest extends TestCase
{
    private $contractService;
    private $db;
    private $cache;

    protected function setUp(): void
    {
        $this->db = $this->createMock(\Libs\DatabaseHelper::class);
        $this->cache = $this->createMock(\Services\CacheService::class);
        $this->contractService = new ContractService($this->db, $this->cache);
    }

    public function testCreateContract(): void
    {
        $data = [
            'title' => 'Test Contract',
            'content' => 'Test content',
            'parties' => ['1', '2']
        ];

        $this->db->expects($this->once())
            ->method('transaction')
            ->willReturnCallback(function($callback) {
                return $callback($this->db);
            });

        $this->db->expects($this->once())
            ->method('insert')
            ->with('contracts', $data)
            ->willReturn(1);

        $this->cache->expects($this->once())
            ->method('delete')
            ->with('contracts:list');

        $id = $this->contractService->createContract($data);
        $this->assertEquals(1, $id);
    }

    public function testGetContractWithCache(): void
    {
        $contractData = [
            'id' => 1,
            'title' => 'Cached Contract'
        ];

        $this->cache->expects($this->once())
            ->method('remember')
            ->with(
                'contract:1',
                $this->callback(function($callback) use ($contractData) {
                    return $callback() === $contractData;
                }),
                3600
            )
            ->willReturn($contractData);

        $contract = $this->contractService->getContract(1);
        $this->assertEquals($contractData, $contract);
    }

    public function testCheckContractCompliance(): void
    {
        $contract = [
            'id' => 1,
            'content' => 'Valid contract content'
        ];

        $this->cache->expects($this->once())
            ->method('get')
            ->with('contract:1')
            ->willReturn($contract);

        $result = $this->contractService->checkContractCompliance(1);
        $this->assertArrayHasKey('overall_compliance', $result);
        $this->assertTrue($result['overall_compliance']);
    }
}
