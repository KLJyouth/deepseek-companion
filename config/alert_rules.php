<?php
return [
    'rules' => [
        'connection_pool' => [
            'active_connections' => ['warning' => 80, 'critical' => 95],
            'wait_time' => ['warning' => 100, 'critical' => 200],
            'error_rate' => ['warning' => 0.01, 'critical' => 0.05]
        ],
        'query_performance' => [
            'slow_query_count' => ['warning' => 10, 'critical' => 50],
            'avg_response_time' => ['warning' => 500, 'critical' => 1000]
        ],
        'resource_usage' => [
            'memory_usage' => ['warning' => 80, 'critical' => 90],
            'cpu_load' => ['warning' => 70, 'critical' => 85]
        ]
    ],
    'notification' => [
        'channels' => ['email', 'slack', 'webhook'],
        'throttle' => 300 // 5分钟内相同告警只发送一次
    ]
];
