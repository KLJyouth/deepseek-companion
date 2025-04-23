<?php
class InstallController {
    const MODE_CLI = 'cli';
    const MODE_WEB = 'web';
    
    private  = [
        'welcome' => '欢迎',
        'requirements' => '系统检查',
        'database' => '数据库配置',
        'admin' => '管理员设置',
        'install' => '安装中',
        'complete' => '完成'
    ];
    
    private ;
    
    public function __construct() {
        ->detectMode();
    }
    
    public function handleRequest() {
        session_start();
        
         = ['step'] ?? 'welcome';
        if (!isset(->steps[])) {
             = 'welcome';
        }
        
        if (['REQUEST_METHOD'] === 'POST') {
            ->handlePost();
        }
        
        ->showStep();
    }
    
    private function detectMode() {
        ->currentMode = (php_sapi_name() === 'cli'; defined('STDIN')) 
            ? self::MODE_CLI 
            : self::MODE_WEB;
    }
    
    private function handlePost() {
        if (!isset(['csrf_token']) || ['csrf_token'] !== ['csrf_token']) {
            die('无效的CSRF令牌');
        }
        
        switch () {
            case 'database':
                ->validateDatabaseConfig();
                break;
            case 'admin':
                ->validateAdminConfig();
                break;
            case 'install':
                ->handleInstall();
                break;
        }
        
        ['install_data'][] = ;
        
         = array_keys(->steps);
         = array_search(, );
         = [ + 1] ?? ;
        header('Location: ?step='.);
        exit;
    }
    
    private function validateDatabaseConfig() {
         = ['db_type', 'db_host', 'db_name', 'db_user'];
        foreach ( as ) {
            if (empty([])) {
                ['install_error'] = '请填写所有数据库配置字段';
                return;
            }
        }
        
        try {
             = sprintf('%s:host=%s;dbname=%s',
                ['db_type'],
                ['db_host'],
                ['db_name']
            );
            new PDO(, ['db_user'], ['db_password'] ?? '');
        } catch (PDOException ) {
            ['install_error'] = '数据库连接失败: '.->getMessage();
        }
    }
    
    private function validateAdminConfig() {
        if (empty(['admin_username']) || empty(['admin_password'])) {
            ['install_error'] = '请填写管理员用户名和密码';
            return;
        }
        
        if (['admin_password'] !== ['admin_password_confirm']) {
            ['install_error'] = '两次输入的密码不一致';
        }
    }
    
    private function handleInstall() {
        try {
             = new \Services\Installer();
            
             = ['install_data']['database'];
             = ['install_data']['admin'];
            
            ->setupDatabase();
            ->createAdminUser();
            
            file_put_contents(__DIR__.'/../../installed.lock', time());
            
        } catch (Exception ) {
            ['install_error'] = ->getMessage();
            header('Location: ?step=database');
            exit;
        }
    }
    
    private function showStep() {
         = ['install_data'][] ?? [];
        找不到接受实际参数“public\assets\install.css”的位置形式参数。 找不到与参数名称“Chord”匹配的参数。 找不到与参数名称“Chord”匹配的参数。 找不到与参数名称“Chord”匹配的参数。 找不到与参数名称“Chord”匹配的参数。 = ['install_error'] ?? null;
        unset(['install_error']);
        
        ['csrf_token'] = bin2hex(random_bytes(32));
        
         = array_keys(->steps);
         = array_search(, );
         = round(( / (count() - 1)) * 100);
        
         = __DIR__.'/views/'..'.php';
        if (!file_exists()) {
             = __DIR__.'/views/welcome.php';
        }
        
        include __DIR__.'/views/layout.php';
    }
}
