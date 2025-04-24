<?php
namespace Libs;

use PDO;
use PDOException;
use RuntimeException;
use ZipArchive;
use DateTime;

class DatabaseBackup {
    private $db;
    private $config;
    private $backupDir;
    
    public function __construct(DatabaseHelper $db, array $config = []) {
        $this->db = $db;
        $this->config = array_merge([
            'backup_dir' => __DIR__ . '/../storage/backups',
            'compress' => true,
            'keep_days' => 7,
            'remote' => null // ['type' => 'ftp', 'host' => '', 'user' => '', 'pass' => '']
        ], $config);
        
        $this->backupDir = $this->config['backup_dir'];
        $this->ensureBackupDir();
    }
    
    private function ensureBackupDir(): void {
        if (!file_exists($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }
    
    public function backup(string $type = 'full'): string {
        $timestamp = (new DateTime())->format('Ymd_His');
        $filename = "backup_{$type}_{$timestamp}.sql";
        $filepath = "{$this->backupDir}/{$filename}";
        
        try {
            $tables = $this->getTables();
            $sql = "";
            
            foreach ($tables as $table) {
                $sql .= $this->getTableSchema($table);
                
                if ($type === 'full') {
                    $sql .= $this->getTableData($table);
                }
            }
            
            file_put_contents($filepath, $sql);
            
            if ($this->config['compress']) {
                $filepath = $this->compressBackup($filepath);
            }
            
            if ($this->config['remote']) {
                $this->uploadToRemote($filepath);
            }
            
            $this->cleanOldBackups();
            
            return $filepath;
        } catch (Exception $e) {
            throw new RuntimeException("备份失败: " . $e->getMessage());
        }
    }
    
    private function getTables(): array {
        $stmt = $this->db->query("SHOW TABLES");
        return array_column($stmt, 'Tables_in_' . $this->db->getDatabaseName());
    }
    
    private function getTableSchema(string $table): string {
        $stmt = $this->db->query("SHOW CREATE TABLE `{$table}`");
        return "DROP TABLE IF EXISTS `{$table}`;\n" . $stmt[0]['Create Table'] . ";\n\n";
    }
    
    private function getTableData(string $table): string {
        $data = $this->db->query("SELECT * FROM `{$table}`");
        if (empty($data)) return "";
        
        $columns = array_keys($data[0]);
        $sql = "INSERT INTO `{$table}` (`" . implode("`, `", $columns) . "`) VALUES \n";
        
        $rows = [];
        foreach ($data as $row) {
            $values = array_map(function($value) {
                return is_null($value) ? 'NULL' : $this->db->quote($value);
            }, array_values($row));
            
            $rows[] = "(" . implode(", ", $values) . ")";
        }
        
        return $sql . implode(",\n", $rows) . ";\n\n";
    }
    
    private function compressBackup(string $filepath): string {
        $zipPath = str_replace('.sql', '.zip', $filepath);
        $zip = new ZipArchive();
        
        if ($zip->open($zipPath, ZipArchive::CREATE) === true) {
            $zip->addFile($filepath, basename($filepath));
            $zip->close();
            unlink($filepath);
            return $zipPath;
        }
        
        return $filepath;
    }
    
    private function uploadToRemote(string $filepath): void {
        $remote = $this->config['remote'];
        
        switch ($remote['type']) {
            case 'ftp':
                $this->ftpUpload($filepath, $remote);
                break;
            case 's3':
                // 未来实现S3支持
                break;
            default:
                throw new RuntimeException("不支持的远程存储类型: " . $remote['type']);
        }
    }
    
    private function ftpUpload(string $filepath, array $config): void {
        $conn = ftp_connect($config['host']);
        if (!$conn) {
            throw new RuntimeException("无法连接到FTP服务器");
        }
        
        if (!ftp_login($conn, $config['user'], $config['pass'])) {
            throw new RuntimeException("FTP登录失败");
        }
        
        if (!ftp_put($conn, basename($filepath), $filepath, FTP_BINARY)) {
            throw new RuntimeException("FTP上传失败");
        }
        
        ftp_close($conn);
    }
    
    private function cleanOldBackups(): void {
        if ($this->config['keep_days'] <= 0) return;
        
        $files = glob("{$this->backupDir}/backup_*.{sql,zip}", GLOB_BRACE);
        $now = time();
        $days = $this->config['keep_days'] * 86400;
        
        foreach ($files as $file) {
            if (filemtime($file) < ($now - $days)) {
                unlink($file);
            }
        }
    }
    
    public function restore(string $filepath): void {
        try {
            if (pathinfo($filepath, PATHINFO_EXTENSION) === 'zip') {
                $filepath = $this->uncompressBackup($filepath);
            }
            
            $sql = file_get_contents($filepath);
            $this->db->exec($sql);
            
        } catch (Exception $e) {
            throw new RuntimeException("恢复失败: " . $e->getMessage());
        }
    }
    
    private function uncompressBackup(string $filepath): string {
        $zip = new ZipArchive();
        $extractPath = dirname($filepath);
        
        if ($zip->open($filepath) === true) {
            $zip->extractTo($extractPath);
            $zip->close();
            
            $files = glob("{$extractPath}/*.sql");
            if (!empty($files)) {
                return $files[0];
            }
        }
        
        throw new RuntimeException("解压备份文件失败");
    }
    
    public function scheduleBackup(string $type, string $time): void {
        // 实现定时备份逻辑
        // 需要与系统任务调度集成
    }
}