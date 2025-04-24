#!/usr/bin/env php
<?php
require_once __DIR__ . '/../init.php';

use Database\MigrationManager;
use Libs\DatabaseHelper;
use Libs\LogHelper;

$db = DatabaseHelper::getInstance();
$logger = LogHelper::getInstance();
$manager = new MigrationManager($db, $logger);

$command = $argv[1] ?? 'run';

try {
    switch ($command) {
        case 'run':
            $results = $manager->runMigrations();
            if (empty($results)) {
                echo "No migrations to run.\n";
            } else {
                echo "Successfully ran migrations:\n";
                foreach ($results as $migration => $status) {
                    echo "- {$migration}: {$status}\n";
                }
            }
            break;
            
        case 'rollback':
            $steps = (int)($argv[2] ?? 1);
            $results = $manager->rollback($steps);
            echo "Rolled back migrations:\n";
            foreach ($results as $migration => $status) {
                echo "- {$migration}: {$status}\n";
            }
            break;
            
        case 'status':
            $executed = $manager->getExecutedMigrations();
            $all = $manager->getMigrationFiles();
            $pending = array_diff($all, $executed);
            
            echo "Migration Status:\n";
            echo "Executed: " . count($executed) . "\n";
            echo "Pending: " . count($pending) . "\n";
            
            if (!empty($pending)) {
                echo "\nPending migrations:\n";
                foreach ($pending as $migration) {
                    echo "- {$migration}\n";
                }
            }
            break;
            
        default:
            echo "Usage:\n";
            echo "  php database/migrate.php run       Run all pending migrations\n";
            echo "  php database/migrate.php rollback  Rollback the last migration\n";
            echo "  php database/migrate.php status    Show migration status\n";
            exit(1);
    }
    
    exit(0);
} catch (Exception $e) {
    $logger->critical("Migration error: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}