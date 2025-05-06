<?php
/**
 * 系统配置文件模板
 * 安装程序将根据用户输入替换以下标记:
 * {{DB_HOST}}, {{DB_NAME}}, {{DB_USER}}, {{DB_PASS}}
 */

return [
    // 数据库配置
    'database' => [
        'driver'    => 'mysql',
        'host'      => '{{DB_HOST}}',
        'database'  => '{{DB_NAME}}',
        'username'  => '{{DB_USER}}',
        'password'  => '{{DB_PASS}}',
        'charset'   => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix'    => '',
        'port'      => 3306,
        'strict'    => false,
    ],

    // 应用配置
    'app' => [
        'name'      => '我的应用',
        'env'       => 'production',
        'debug'     => false,
        'url'       => 'http://localhost',
        'timezone'  => 'Asia/Shanghai',
        'locale'    => 'zh-CN',
        'key'       => '{{APP_KEY}}', // 安装时生成随机密钥
    ],

    // 管理员配置
    'admin' => [
        'username'  => '{{ADMIN_USER}}',
        'email'     => '{{ADMIN_EMAIL}}',
        // 密码将使用password_hash()加密存储
    ],

    // 邮件配置
    'mail' => [
        'driver' => 'smtp',
        'host' => 'smtp.mailtrap.io',
        'port' => 2525,
        'username' => null,
        'password' => null,
        'encryption' => null,
    ],

    // 缓存配置
    'cache' => [
        'driver' => 'file',
        'path'   => '../storage/cache',
    ],

    // 会话配置
    'session' => [
        'driver' => 'file',
        'lifetime' => 120,
        'expire_on_close' => false,
        'encrypt' => false,
        'path' => '../storage/sessions',
    ],

    // 日志配置
    'logging' => [
        'default' => 'single',
        'path'    => '../storage/logs',
        'level'   => 'debug',
    ],

    // 宝塔面板特定配置
    'bt_panel' => [
        'enabled' => {{BT_ENABLED}},
        'path'    => '/www/server/panel',
    ],
];

/**
 * 安全提示:
 * 1. 安装完成后请确保此文件权限设置为644
 * 2. 不要将包含敏感信息的配置文件提交到版本控制
 * 3. 定期更换APP_KEY
 */
?>