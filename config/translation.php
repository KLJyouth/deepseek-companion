<?php
/**
 * 翻译模块配置
 */

return [
    // 并行处理设置
    'parallel' => [
        'enabled' => env('TRANSLATION_PARALLEL', true),
        'workers' => (int) env('TRANSLATION_WORKERS', 4),
        'timeout' => (int) env('TRANSLATION_TIMEOUT', 30)
    ],
    
    // 缓存策略
    'cache' => [
        'driver' => env('TRANSLATION_CACHE_DRIVER', 'file'),
        'ttl' => (int) env('TRANSLATION_CACHE_TTL', 3600),
        'preload' => (bool) env('TRANSLATION_CACHE_PRELOAD', false)
    ],
    
    // 术语库配置
    'glossary' => [
        'path' => env('TRANSLATION_GLOSSARY', 'i18n/glossary.yml'),
        'strict' => (bool) env('TRANSLATION_STRICT_MODE', true)
    ],
    
    // 质量检查
    'quality' => [
        'terminology' => [
            'threshold' => (float) env('QUALITY_TERMINOLOGY_THRESHOLD', 95.0),
            'check_on_update' => true
        ],
        'fluency' => [
            'threshold' => (float) env('QUALITY_FLUENCY_THRESHOLD', 90.0)
        ]
    ],
    
    // 服务提供商
    'providers' => [
        'local' => [
            'driver' => 'local',
            'path' => 'i18n/locales'
        ],
        'crowdin' => [
            'driver' => 'crowdin',
            'api_key' => env('CROWDIN_API_KEY'),
            'project_id' => env('CROWDIN_PROJECT_ID')
        ]
    ]
];