<?php

use PHPUnit\Framework\TestCase;
use Services\SecurityService;
use Libs\LogHelper;

class SecurityServiceTest extends TestCase
{
    private $backupDir = __DIR__ . '/test_backups';
    private $gpgFile = __DIR__ . '/test_gpg_recipient';

    protected function setUp(): void
    {
        mkdir($this->backupDir, 0700, true);
        file_put_contents($this->gpgFile, 'test@example.com');
    }

    protected function tearDown(): void
    {
        exec("rm -rf {$this->backupDir}");
        @unlink($this->gpgFile);
    }

    public function testCheckBackupsReturnsArrayType()
    {
        $service = new SecurityService($this->backupDir);
        $result = $service->checkBackups();
        $this->assertIsArray($result);
        $this->assertArrayHasKey('healthy', $result);
        $this->assertArrayHasKey('message', $result);
    }

    public function testCheckBackupsDetectsMissingDirectory()
    {
        rmdir($this->backupDir);
        $service = new SecurityService($this->backupDir);
        $result = $service->checkBackups();
        $this->assertFalse($result['healthy']);
        $this->assertStringContainsString('备份目录不存在', $result['message']);
    }

    public function testUpdateGpgKeyValidatesEmailFormat()
    {
        $service = new SecurityService('', '', $this->gpgFile);
        
        $this->expectException(Exception::class);
        $service->updateGpgKey('invalid-email');
    }

    public function testUpdateGpgKeyChecksKeyExistence()
    {
        $service = new SecurityService('', '', $this->gpgFile);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('系统中不存在此邮箱的GPG密钥');
        $service->updateGpgKey('nonexist@example.com');
    }

    public function testGetBackupFilesReturnsSortedList()
    {
        // 创建测试备份文件
        touch("{$this->backupDir}/env_backup_1.gpg", strtotime('-2 hours'));
        touch("{$this->backupDir}/env_backup_2.gpg", strtotime('-1 hour'));

        $service = new SecurityService($this->backupDir);
        $files = $service->getBackupFiles();

        $this->assertCount(2, $files);
        $this->assertGreaterThan(
            $files[1]['modified'],
            $files[0]['modified']
        );
    }

    public function testCheckGpgKeyReturnsValidStructure()
    {
        $service = new SecurityService('', '', $this->gpgFile);
        $result = $service->checkGpgKey();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('healthy', $result);
        $this->assertArrayHasKey('message', $result);
    }
}