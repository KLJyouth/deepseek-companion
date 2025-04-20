<?php
return [
    'performance' => [
        'response_time' => [
            'warning' => 1000,  // ms
            'critical' => 2000
        ],
        'memory_usage' => [
            'warning' => 128,   // MB
            'critical' => 256
        ],
        'cpu_usage' => [
            'warning' => 70,    // %
            'critical' => 90
        ]
    ],
    'security' => [
        'failed_logins' => [
            'warning' => 5,     // per minute
            'critical' => 10
        ]
    ],
    'system' => [
        'disk_usage' => [
            'warning' => 80,    // %
            'critical' => 90
        ]
    ]
];
