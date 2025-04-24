<?php
namespace Database;

use Libs\DatabaseHelper;
use Libs\LogHelper;

class SeederManager extends MigrationManager {
    private $seedersTable = 'seeders';
    private $seedersPath = __DIR__ . '/seeders';
    private $environment;

    public function __construct(DatabaseHelper $db, LogHelper $logger, string $environment = 'production') {
        parent::__construct($db, $logger);
        $this->environment = $environment;
        $this->ensureSeedersTable();
    }

    private function ensureSeedersTable(): void {
        $this->db->query("
            CREATE TABLE IF NOT EXISTS {$this->seedersTable} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                seeder VARCHAR(255) NOT NULL,
                environment VARCHAR(50) NOT NULL,
                batch INT NOT NULL,
                executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    public function runSeeders(): array {
        $executed = $this->getExecutedSeeders();
        $files = $this->getSeederFiles();
        $toRun = array_diff($files, $executed);
        $results = [];
        
        if (empty($toRun)) {
            $this->logger->info('No new seeders to run');
            return [];
        }

        $batch = $this->getNextBatchNumber();
        
        foreach ($toRun as $seeder) {
            try {
                $this->runSeeder($seeder, $batch);
                $results[$seeder] = 'success';
                $this->logger->info("Seeder executed: {$seeder}");
            } catch (\Exception $e) {
                $results[$seeder] = 'failed: ' . $e->getMessage();
                $this->logger->error("Seeder failed: {$seeder} - " . $e->getMessage());
                throw $e;
            }
        }

        return $results;
    }

    private function runSeeder(string $seeder, int $batch): void {
        require_once $this->seedersPath . '/' . $seeder;
        
        $className = $this->getSeederClassName($seeder);
        $instance = new $className();
        $instance->run($this->db);
        
        $this->db->insert($this->seedersTable, [
            'seeder' => $seeder,
            'environment' => $this->environment,
            'batch' => $batch
        ]);
    }

    private function getSeederFiles(): array {
        $files = scandir($this->seedersPath);
        return array_values(array_filter($files, function($file) {
            return preg_match('/^\d{3}_.+\.php$/', $file);
        }));
    }

    private function getExecutedSeeders(): array {
        $results = $this->db->getRows(
            "SELECT seeder FROM {$this->seedersTable} 
             WHERE environment = ?
             ORDER BY seeder",
            [$this->environment]
        );
        return array_column($results, 'seeder');
    }

    public function generateTestData(int $count = 10): void {
        if ($this->environment !== 'testing') {
            throw new \RuntimeException('Test data can only be generated in testing environment');
        }

        // 实现测试数据生成逻辑
        $this->logger->info("Generating {$count} test records");
    }
}