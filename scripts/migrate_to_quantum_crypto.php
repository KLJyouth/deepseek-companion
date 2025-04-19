<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../libs/CryptoHelper.php';
require_once __DIR__ . '/../libs/QuantumCryptoHelper.php';
require_once __DIR__ . '/../libs/DatabaseHelper.php';

class CryptoMigrator {
    private $db;
    private $batchSize = 100;
    private $reportInterval = 1000;
    
    public function __construct() {
        $this->db = DatabaseHelper::getInstance();
        QuantumCryptoHelper::init();
    }
    
    public function migrateSensitiveTables(): void {
        $tables = [
            'users' => ['password_hash', 'auth_token'],
            'sessions' => ['data'],
            'secrets' => ['content']
        ];
        
        foreach ($tables as $table => $columns) {
            $this->migrateTable($table, $columns);
        }
    }
    
    private function migrateTable(string $table, array $columns): void {
        $total = $this->db->query("SELECT COUNT(*) FROM $table")->fetchColumn();
        $processed = 0;
        
        echo "Migrating $table ($total records)...\n";
        
        $offset = 0;
        while ($offset < $total) {
            $rows = $this->db->query(
                "SELECT * FROM $table LIMIT $this->batchSize OFFSET $offset"
            )->fetchAll();
            
            foreach ($rows as $row) {
                $updateData = [];
                foreach ($columns as $column) {
                    if (!empty($row[$column])) {
                        try {
                            $legacy = CryptoHelper::decrypt($row[$column]);
                            $encrypted = QuantumCryptoHelper::encrypt($legacy);
                            $updateData[$column] = json_encode($encrypted);
                        } catch (Exception $e) {
                            echo "Error migrating $table.id={$row['id']}.$column: " . $e->getMessage() . "\n";
                            continue;
                        }
                    }
                }
                
                if (!empty($updateData)) {
                    $this->db->update($table, $updateData, ['id' => $row['id']]);
                }
                
                $processed++;
                if ($processed % $this->reportInterval === 0) {
                    echo "Processed $processed/$total records\n";
                }
            }
            
            $offset += $this->batchSize;
        }
        
        echo "Completed migrating $table\n";
    }
}

// Execute migration
try {
    $migrator = new CryptoMigrator();
    $migrator->migrateSensitiveTables();
    echo "Migration completed successfully\n";
    exit(0);
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}