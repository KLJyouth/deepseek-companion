<?php
/**
 * 质量监控配置
 */

return [
    // 实时监控设置
    'realtime' => [
        'enabled' => env('MONITORING_REALTIME', true),
        'port' => (int) env('MONITORING_PORT', 8081),
        'interval' => (int) env('MONITORING_INTERVAL', 60),
        'history' => (int) env('MONITORING_HISTORY', 24)
    ],

    // 告警设置
    'alerts' => [
        'terminology' => [
            'threshold' => (float) env('ALERT_TERMINOLOGY_THRESHOLD', 90.0),
            'channels' => explode(',', env('ALERT_CHANNELS', 'slack,email'))
        ],
        'quality' => [
            'threshold' => (float) env('ALERT_QUALITY_THRESHOLD', 85.0),
            'channels' => explode(',', env('ALERT_CHANNELS', 'slack,email'))
        ]
    ],

    // 通知渠道配置
    'channels' => [
        'slack' => [
            'webhook' => env('SLACK_WEBHOOK'),
            'channel' => env('SLACK_CHANNEL', '#alerts')
        ],
        'email' => [
            'from' => env('MAIL_FROM', 'monitor@stanfai.org'),
            'to' => explode(',', env('MAIL_TO', 'admin@stanfai.org'))
        ]
    ],

    // 报告设置
    'reports' => [
        'terminology' => [
            'schedule' => env('REPORT_TERMINOLOGY_SCHEDULE', '0 3 * * *'),
            'format' => env('REPORT_FORMAT', 'html'),
            'recipients' => explode(',', env('REPORT_RECIPIENTS', 'admin@stanfai.org'))
        ],
        'quality' => [
            'schedule' => env('REPORT_QUALITY_SCHEDULE', '0 4 * * 1'),
            'format' => env('REPORT_FORMAT', 'html')
        ]
    ]
];