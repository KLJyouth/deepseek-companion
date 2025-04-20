<?php
return [
    'rules' => [
        'sql_injection' => [
            'pattern' => '/(\bunion\b|\bselect\b|\binsert\b|\bdrop\b)/i',
            'score' => 5,
            'action' => 'block'
        ],
        'xss' => [
            'pattern' => '/<script|\bjavascript:|\bonerror\b/i',
            'score' => 4,
            'action' => 'log'
        ],
        'path_traversal' => [
            'pattern' => '/\.\.\/|\.\.\\/',
            'score' => 5,
            'action' => 'block'
        ]
    ],
    'thresholds' => [
        'block_score' => 8,
        'alert_score' => 5
    ]
];
