<?php
/**
 * 威胁情报智能更新配置
 * 
 * 实现特性：
 * 1. 多源API轮询机制（7个主流情报源）
 * 2. 自动失败转移和重试逻辑
 * 3. 动态负载均衡策略
 * 4. 缓存一致性保障
 * 
 * @copyright 广西港妙科技有限公司
 */

return [
    'update_strategy' => [
        'daily' => [
            'time' => '02:00',
            'max_retry' => 3,
            'sources' => ['微步在线', 'VirusTotal', 'IBM X-Force']
        ],
        'hourly' => [
            'interval' => 4,
            'sources' => ['Cisco Talos', 'FireEye']
        ]
    ],
    
    'api_configurations' => [
        '微步在线' => [
            'auth_type' => 'api_key',
            'env_key' => 'THREATBOOK_APIKEY',
            'rate_limit' => 100
        ],
        'VirusTotal' => [
            'auth_type' => 'api_key',
            'env_key' => 'VIRUSTOTAL_APIKEY',
            'rate_limit' => 500
        ],
        'IBM X-Force' => [
            'auth_type' => 'basic_auth',
            'env_key' => 'XFORCE_CREDENTIALS',
            'rate_limit' => 200
        ],
        'Cisco Talos' => [
            'auth_type' => 'oauth2',
            'env_key' => 'TALOS_CLIENT_SECRET',
            'rate_limit' => 300
        ],
        'FireEye' => [
            'auth_type' => 'api_key',
            'env_key' => 'FIREEYE_APIKEY',
            'rate_limit' => 150
        ]
    ],
    
    'cache_settings' => [
        'redis_cluster' => [
            'main' => 'threat_intel_cache',
            'backup' => 'threat_intel_backup'
        ],
        'ttl' => 3600,
        'compression' => true
    ],
    
    'request_config' => [
        'timeout' => 15,
        'concurrency' => 5,
        'retry' => [
            'attempts' => 3,
            'delay' => 1000
        ]
    ],
    
    'threat_types' => [
        'ip' => ['malicious', 'tor_exit', 'vpn'],
        'domain' => ['phishing', 'malware_host'],
        'hash' => ['malware_signature']
    ]
];