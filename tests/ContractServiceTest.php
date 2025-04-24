<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use Services\ContractService;
use Libs\DatabaseHelper;
use Services\CacheService;
use Libs\CryptoHelper;
use DeepSeek\Security\Quantum\QuantumKeyManager;
use Models\Contract;
use Libs\Exception\SecurityException;

class ContractServiceTest extends TestCase {
    private $contractService;
    private $dbMock;
    private $cacheMock;
    private $cryptoMock;
    private $keyManagerMock;
    
    protected function setUp(): void {
        $this->dbMock = $this->createMock(DatabaseHelper::class);
        $this->cacheMock = $this->createMock(CacheService::class);
        $this->cryptoMock = $this->createMock(CryptoHelper::class);
        $this->keyManagerMock = $this->createMock(QuantumKeyManager::class);
        
        $this->contractService = new ContractService(
            $this->dbMock,
            $this->cacheMock,
            $this->cryptoMock,
            $this->keyManagerMock
        );
    }
    
    public function testCreateContractSuccess(): void {
        $this->dbMock->method('insert')
            ->willReturn(1);
        
        $contract = $this->contractService->createContract([
            'title' => 'Test Contract',
            'content' => 'Contract content',
            'created_by' => 1
        ]);
        
        $this->assertInstanceOf(Contract::class, $contract);
        $this->assertEquals(1, $contract->id);
    }
    
    public function testCreateContractMissingTitle(): void {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('合同标题不能为空');
        
        $this->contractService->createContract([
            'content' => 'Contract content',
            'created_by' => 1
        ]);
    }
    
    public function testSignContractSuccess(): void {
        $this->dbMock->method('getRow')
            ->willReturn(['id' => 1]);
        
        $this->keyManagerMock->method('getCurrentKeyId')
            ->willReturn('quantum-key-123');
        
        $this->keyManagerMock->method('getMasterPublicKey')
            ->willReturn('public-key-123');
        
        $this->keyManagerMock->method('getKeyExpiration')
            ->willReturn(date('Y-m-d H:i:s', strtotime('+1 year')));
        
        $result = $this->contractService->signContract(
            1, 
            1, 
            'signature-data', 
            'SM9'
        );
        
        $this->assertArrayHasKey('contract_id', $result);
        $this->assertArrayHasKey('signature_id', $result);
        $this->assertArrayHasKey('signed_at', $result);
    }
    
    public function testSignContractNotFound(): void {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('合同不存在');
        
        $this->dbMock->method('getRow')
            ->willReturn(null);
        
        $this->contractService->signContract(
            999, 
            1, 
            'signature-data', 
            'SM9'
        );
    }
    
    public function testValidateContractDataSuccess(): void {
        $this->expectNotToPerformAssertions();
        
        $reflection = new \ReflectionClass($this->contractService);
        $method = $reflection->getMethod('validateContractData');
        $method->setAccessible(true);
        
        $method->invokeArgs($this->contractService, [[
            'title' => 'Valid Title',
            'content' => 'Valid Content',
            'created_by' => 1
        ]]);
    }
}