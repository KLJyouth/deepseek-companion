<?php
namespace Database;

use Libs\DatabaseHelper;
use Libs\LogHelper;
use Exception;

class MigrationManager {
    private $db;
    private $logger;
    private $migrationsTable = 'migrations';
    private $migrationsPath = __DIR__ . '/migrations';

    public function __construct(DatabaseHelper $db, LogHelper $logger) {
        $this->db = $db;
        $this->logger = $logger;
        $this->ensureMigrationsTable();
    }

    private function ensureMigrationsTable(): void {
        $this->db->query("
            CREATE TABLE IF NOT EXISTS {$this->migrationsTable} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL,
                batch INT NOT NULL,
                executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    public function runMigrations(): array {
        $executed = $this->getExecutedMigrations();
        $files = $this->getMigrationFiles();
        $toRun = array_diff($files, $executed);
        $results = [];
        
        if (empty($toRun)) {
            $this->logger->info('No new migrations to run');
            return [];
        }

        $batch = $this->getNextBatchNumber();
        
        foreach ($toRun as $migration) {
            try {
                $this->runMigration($migration, $batch);
                $results[$migration] = 'success';
                $this->logger->info("Migration executed: {$migration}");
            } catch (Exception $e) {
                $results[$migration] = 'failed: ' . $e->getMessage();
                $this->logger->error("Migration failed: {$migration} - " . $e->getMessage());
                throw $e;
            }
        }

        return $results;
    }

    private function runMigration(string $migration, int $batch): void {
        require_once $this->migrationsPath . '/' . $migration;
        
        $className = $this->getMigrationClassName($migration);
        $instance = new $className();
        $instance->run($this->db);
        
        $this->db->insert($this->migrationsTable, [
            'migration' => $migration,
            'batch' => $batch
        ]);
    }

    public function rollback(int $steps = 1): array {
        $batch = $this->getLastBatchNumber();
        $migrations = $this->getMigrationsByBatch($batch, $steps);
        $results = [];
        
        foreach ($migrations as $migration) {
            try {
                $this->rollbackMigration($migration['migration']);
                $results[$migration['migration']] = 'rolled back';
                $this->logger->info("Migration rolled back: {$migration['migration']}");
            } catch (Exception $e) {
                $results[$migration['migration']] = 'failed: ' . $e->getMessage();
                $this->logger->error("Rollback failed: {$migration['migration']} - " . $e->getMessage());
                throw $e;
            }
        }

        return $results;
    }

    private function rollbackMigration(string $migration): void {
        require_once $this->migrationsPath . '/' . $migration;
        
        $className = $this->getMigrationClassName($migration);
        $instance = new $className();
        
        if (method_exists($instance, 'down')) {
            $instance->down($this->db);
        }
        
        $this->db->query(
            "DELETE FROM {$this->migrationsTable} WHERE migration = ?",
            [$migration]
        );
    }

    private function getMigrationFiles(): array {
        $files = scandir($this->migrationsPath);
        return array_values(array_filter($files, function($file) {
            return preg_match('/^\d{3}_.+\.php$/', $file);
        }));
    }

    private function getExecutedMigrations(): array {
        $results = $this->db->getRows(
            "SELECT migration FROM {$this->migrationsTable} ORDER BY migration"
        );
        return array_column($results, 'migration');
    }

    private function getMigrationsByBatch(int $batch, int $steps = 1): array {
        return $this->db->getRows(
            "SELECT migration FROM {$this->migrationsTable} 
             WHERE batch >= ? - ? + 1 AND batch <= ?
             ORDER BY batch DESC, migration DESC",
            [$batch, $steps, $batch]
        );
    }

    private function getLastBatchNumber(): int {
        $result = $this->db->getRow(
            "SELECT MAX(batch) as max_batch FROM {$this->migrationsTable}"
        );
        return (int)($result['max_batch'] ?? 0);
    }

    private function getNextBatchNumber(): int {
        return $this->getLastBatchNumber() + 1;
    }

    private function getMigrationClassName(string $migration): string {
        $name = preg_replace('/^\d{3}_/', '', $migration);
        $name = str_replace('.php', '', $name);
        return str_replace('_', '', ucwords($name, '_'));
    }
}