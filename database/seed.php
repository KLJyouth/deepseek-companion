#!/usr/bin/env php
<?php
require_once __DIR__ . '/../init.php';

use Database\SeederManager;
use Libs\DatabaseHelper;
use Libs\LogHelper;

$db = DatabaseHelper::getInstance();
$logger = LogHelper::getInstance();

// 默认使用开发环境，可通过--env参数覆盖
$environment = 'development';
$options = getopt('', ['env:', 'test-data::']);

if (isset($options['env'])) {
    $environment = $options['env'];
}

$manager = new SeederManager($db, $logger, $environment);

try {
    if (isset($options['test-data'])) {
        $count = is_numeric($options['test-data']) ? (int)$options['test-data'] : 10;
        $manager->generateTestData($count);
        exit(0);
    }

    $command = $argv[1] ?? 'run';

    switch ($command) {
        case 'run':
            $results = $manager->runSeeders();
            if (empty($results)) {
                echo "No seeders to run.\n";
            } else {
                echo "Successfully ran seeders:\n";
                foreach ($results as $seeder => $status) {
                    echo "- {$seeder}: {$status}\n";
                }
            }
            break;
            
        case 'status':
            $executed = $manager->getExecutedSeeders();
            $all = $manager->getSeederFiles();
            $pending = array_diff($all, $executed);
            
            echo "Seeder Status ({$environment}):\n";
            echo "Executed: " . count($executed) . "\n";
            echo "Pending: " . count($pending) . "\n";
            
            if (!empty($pending)) {
                echo "\nPending seeders:\n";
                foreach ($pending as $seeder) {
                    echo "- {$seeder}\n";
                }
            }
            break;
            
        default:
            echo "Usage:\n";
            echo "  php database/seed.php run [--env=environment]  Run all pending seeders\n";
            echo "  php database/seed.php status [--env=environment]  Show seeder status\n";
            echo "  php database/seed.php --test-data[=count]  Generate test data (testing env only)\n";
            exit(1);
    }
    
    exit(0);
} catch (\Exception $e) {
    $logger->critical("Seeder error: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}