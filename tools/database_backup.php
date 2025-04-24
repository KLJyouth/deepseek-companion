<?php
require_once __DIR__ . '/../init.php';

use Libs\DatabaseBackup;
use Libs\DatabaseHelper;
use Libs\LogHelper;

try {
    $db = DatabaseHelper::getInstance();
    $logger = new LogHelper();
    
    $backup = new DatabaseBackup($db, [
        'backup_dir' => getenv('BACKUP_DIR') ?: __DIR__ . '/../storage/backups',
        'compress' => getenv('BACKUP_COMPRESS') !== 'false',
        'keep_days' => (int)(getenv('BACKUP_KEEP_DAYS') ?: 7),
        'remote' => getenv('BACKUP_REMOTE') ? json_decode(getenv('BACKUP_REMOTE'), true) : null
    ]);

    $command = $argv[1] ?? 'run';
    $type = $argv[2] ?? 'full';

    switch ($command) {
        case 'run':
            $filepath = $backup->backup($type);
            $logger->info("备份成功创建: " . basename($filepath));
            break;
            
        case 'restore':
            if (empty($argv[2])) {
                die("请指定要恢复的备份文件\n");
            }
            $backup->restore($argv[2]);
            $logger->info("数据库恢复成功");
            break;
            
        case 'list':
            $files = glob($backup->getBackupDir() . '/backup_*.{sql,zip}', GLOB_BRACE);
            echo "可用备份:\n";
            foreach ($files as $file) {
                echo "- " . basename($file) . "\n";
            }
            break;
            
        case 'clean':
            $backup->cleanOldBackups();
            $logger->info("已清理旧备份");
            break;
            
        default:
            echo "数据库备份工具\n";
            echo "用法:\n";
            echo "  php tools/database_backup.php run [full|incremental]  # 创建备份\n";
            echo "  php tools/database_backup.php restore <file>         # 恢复备份\n";
            echo "  php tools/database_backup.php list                    # 列出备份\n";
            echo "  php tools/database_backup.php clean                   # 清理旧备份\n";
            break;
    }
} catch (Exception $e) {
    $logger->error("备份操作失败: " . $e->getMessage());
    die("错误: " . $e->getMessage() . "\n");
}