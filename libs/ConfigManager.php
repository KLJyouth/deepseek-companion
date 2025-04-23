<?php
/**
 * 配置管理器
 */
class ConfigManager {
    private $config = [];
    private $envTemplate = <<<TEMPLATE
APP_ENV=production
APP_KEY={APP_KEY}
APP_DEBUG=false

DB_CONNECTION={DB_CONNECTION}
DB_HOST={DB_HOST}
DB_PORT={DB_PORT}
DB_DATABASE={DB_DATABASE}
DB_USERNAME={DB_USERNAME}
DB_PASSWORD={DB_PASSWORD}

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=mailhog
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
TEMPLATE;

    /**
     * 收集数据库配置
     */
    public function collectDatabaseConfig() {
        $this->config['db'] = [
            'type' => $this->ask("数据库类型(mysql/pgsql/sqlite) [mysql]: ", 'mysql'),
            'host' => $this->ask("数据库主机 [localhost]: ", 'localhost'),
            'port' => $this->ask("数据库端口 [3306]: ", '3306'),
            'name' => $this->ask("数据库名: "),
            'user' => $this->ask("数据库用户: "),
            'pass' => $this->ask("数据库密码: ", '', true)
        ];

        return $this->config['db'];
    }

    /**
     * 收集管理员配置
     */
    public function collectAdminConfig() {
        $this->config['admin'] = [
            'email' => $this->ask("管理员邮箱: "),
            'username' => $this->ask("管理员用户名 [admin]: ", 'admin'),
            'password' => $this->ask("管理员密码: ", '', true)
        ];

        // 生成密码哈希
        $this->config['admin']['password_hash'] = password_hash(
            $this->config['admin']['password'],
            PASSWORD_BCRYPT,
            ['cost' => 12]
        );

        return $this->config['admin'];
    }

    /**
     * 生成.env文件
     */
    public function generateEnvFile() {
        $replacements = [
            '{APP_KEY}' => bin2hex(random_bytes(16)),
            '{DB_CONNECTION}' => $this->config['db']['type'],
            '{DB_HOST}' => $this->config['db']['host'],
            '{DB_PORT}' => $this->config['db']['port'],
            '{DB_DATABASE}' => $this->config['db']['name'],
            '{DB_USERNAME}' => $this->config['db']['user'],
            '{DB_PASSWORD}' => $this->config['db']['pass']
        ];

        $envContent = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $this->envTemplate
        );

        file_put_contents(__DIR__.'/../.env', $envContent);
        LogService::info('.env文件生成成功');
    }

    /**
     * 辅助方法：用户输入
     */
    private function ask($prompt, $default = '', $hidden = false) {
        echo $prompt;
        return trim(fgets(STDIN)) ?: $default;
    }

    /**
     * 验证配置
     */
    public function validateConfig() {
        // 验证数据库配置
        if (empty($this->config['db']['name'])) {
            throw new Exception("数据库名不能为空");
        }

        // 验证管理员密码强度
        if (strlen($this->config['admin']['password']) < 8) {
            throw new Exception("管理员密码必须至少8个字符");
        }

        return true;
    }
}