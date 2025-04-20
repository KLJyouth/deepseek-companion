<?php
require_once __DIR__.'/../services/SecurityService.php';

class SecurityController 
{
    private $securityService;

    private $auditService;

    public function __construct() 
    {
        $this->securityService = new SecurityService();
        $this->auditService = new AuditService();
        $this->ensureAdmin();
    }

    // 确保管理员权限
    private function ensureAdmin()
    {
        AuthMiddleware::verifyAdmin();
    }

    // 安全仪表盘
    public function dashboard()
    {
        $backupStatus = $this->securityService->checkBackups();
        $logAlerts = $this->securityService->checkLogAlerts();
        $gpgStatus = $this->securityService->checkGpgKey();

        render('security/dashboard', [
            'backupStatus' => $backupStatus,
            'logAlerts' => $logAlerts,
            'gpgStatus' => $gpgStatus
        ]);
    }

    // 查看备份文件
    public function backups()
    {
        $backupFiles = $this->securityService->getBackupFiles();
        render('security/backups', ['backups' => $backupFiles]);
    }

    // 查看访问日志
    public function accessLogs()
    {
        // 操作验证：速率限制
        \Middlewares\RateLimitMiddleware::check('admin_access_logs');
        // 操作验证：权限检查
        \Middlewares\AuthMiddleware::check('admin');
        // 审计日志
        $this->auditService->log('view_access_logs', [
            'user_id' => $_SESSION['user_id'] ?? null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        
        $page = max(1, intval($_GET['page'] ?? 1));
        $search = $_GET['search'] ?? '';
        $type = $_GET['type'] ?? '';
        $logsPerPage = 20;

        $allLogs = $this->securityService->getAccessLogs();
        $filteredLogs = [];

        // 应用筛选
        foreach ($allLogs as $log) {
            $matchesSearch = empty($search) || stripos($log, $search) !== false;
            $matchesType = empty($type) ||
                ($type === 'normal' && strpos($log, '异常访问') === false) ||
                ($type === 'alert' && strpos($log, '异常访问') !== false);
            if ($matchesSearch && $matchesType) {
                $filteredLogs[] = $log;
            }
        }

        // 分页
        $total = count($filteredLogs);
        $totalPages = max(1, ceil($total / $logsPerPage));
        $logs = array_slice($filteredLogs, ($page - 1) * $logsPerPage, $logsPerPage);

        // 错误处理
        if ($page > $totalPages) {
            $page = $totalPages;
        }

        // 渲染视图
        render('security/access_logs', [
            'logs' => $logs,
            'page' => $page,
            'totalPages' => $totalPages
        ]);
    }

    // 更新GPG密钥
    public function updateGpgKey()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = $_POST['email'] ?? '';
            $result = $this->securityService->updateGpgKey($email);
            setFlashMessage($result['message'], $result['success'] ? 'success' : 'error');
            redirect('/security/dashboard');
        }

        $currentKey = $this->securityService->getCurrentGpgKey();
        render('security/update_gpg', ['currentKey' => $currentKey]);
    }

    // 执行立即备份
    public function runBackup()
    {
        try {
            exec(__DIR__.'/../../scripts/backup_env.sh', $output, $returnCode);
            if ($returnCode !== 0) {
                throw new Exception('备份执行失败: '.implode("\n", $output));
            }
            jsonResponse(['success' => true, 'message' => '备份任务已启动']);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // 删除备份文件
    public function deleteBackup()
    {
        $file = $_GET['file'] ?? '';
        $filePath = '/var/backups/env_files/'.basename($file);
        
        if (!preg_match('/^env_backup_\d+_\d+\.gpg$/', basename($file))) {
            jsonResponse(['success' => false, 'message' => '无效的文件名']);
            return;
        }

        if (!file_exists($filePath)) {
            jsonResponse(['success' => false, 'message' => '文件不存在']);
            return;
        }

        if (unlink($filePath)) {
            jsonResponse(['success' => true, 'message' => '备份已删除']);
        } else {
            jsonResponse(['success' => false, 'message' => '删除失败']);
        }
    }

    // 下载备份文件
    public function downloadBackup()
    {
        $file = $_GET['file'] ?? '';
        $filePath = '/var/backups/env_files/'.basename($file);
        
        if (!preg_match('/^env_backup_\d+_\d+\.gpg$/', basename($file))) {
            setFlashMessage('无效的文件名', 'error');
            redirect('/security/backups');
            return;
        }

        if (!file_exists($filePath)) {
            setFlashMessage('文件不存在', 'error');
            redirect('/security/backups');
            return;
        }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.basename($filePath).'"');
        readfile($filePath);
        exit;
    }

    // 查看审计日志
    public function auditLogs()
    {
        if (!$this->checkPermission('view_audit_logs')) {
            throw new \Exception('没有权限访问审计日志');
        }

        $this->auditService->log('view_audit_logs');
        
        $page = max(1, intval($_GET['page'] ?? 1));
        $search = $this->sanitizeInput($_GET['search'] ?? '');
        $logsPerPage = 20;

        $allLogs = $this->auditService->getLogs();
        $filteredLogs = $this->filterLogs($allLogs, $search);
        
        $totalLogs = count($filteredLogs);
        $totalPages = ceil($totalLogs / $logsPerPage);
        $page = min($page, $totalPages);
        
        $logs = array_slice(
            $filteredLogs, 
            ($page - 1) * $logsPerPage, 
            $logsPerPage
        );

        render('security/audit_logs', [
            'logs' => $logs,
            'page' => $page,
            'totalPages' => $totalPages
        ]);
    }

    private function checkPermission($action): bool 
    {
        return isset($_SESSION['permissions']) && 
               in_array($action, $_SESSION['permissions']);
    }

    private function sanitizeInput($input): string
    {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    private function filterLogs(array $logs, string $search): array
    {
        if (empty($search)) {
            return $logs;
        }

        return array_filter($logs, function($log) use ($search) {
            return stripos($log['action'], $search) !== false ||
                   stripos($log['user'], $search) !== false ||
                   stripos($log['ip'], $search) !== false;
        });
    }
}