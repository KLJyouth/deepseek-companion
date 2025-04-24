<?php
return [
    'default' => 'mysql',
    
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => getenv('DB_HOST') ?: 'localhost',
            'port' => getenv('DB_PORT') ?: '3306',
            'database' => getenv('DB_DATABASE') ?: 'stanfai',
            'username' => getenv('DB_USERNAME') ?: 'root',
            'password' => getenv('DB_PASSWORD') ?: '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ],
        
        'testing' => [
            'driver' => 'mysql',
            'host' => getenv('DB_TEST_HOST') ?: 'localhost',
            'port' => getenv('DB_TEST_PORT') ?: '3306',
            'database' => getenv('DB_TEST_DATABASE') ?: 'stanfai_test',
            'username' => getenv('DB_TEST_USERNAME') ?: 'root',
            'password' => getenv('DB_TEST_PASSWORD') ?: '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ]
    ]
];