<?php
return [
    'message' => '系统正在进行维护升级，请稍后再访问。',
    'retry_after' => 1800, // 30分钟
    'start_time' => time(),
    'allowed_ips' => [
        '127.0.0.1',
        // 添加管理员IP地址
    ]
];
