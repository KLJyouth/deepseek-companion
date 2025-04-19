<?php
namespace Admin\Controllers;

use Libs\DatabaseHelper;
use Libs\SecurityService;
use Libs\AnalyticsService;
use Libs\IncidentResponseService;

class ServiceManagementController {
    private $dbHelper;
    private $securityService;
    private $analyticsService;
    private $incidentService;

    public function __construct() {
        $this->dbHelper = new DatabaseHelper();
        $this->securityService = new SecurityService();
        $this->analyticsService = new AnalyticsService();
        $this->incidentService = new IncidentResponseService();
    }

    public function securityDashboard() {
        // 安全服务管理逻辑
        $status = $this->securityService->getStatus();
        $config = $this->dbHelper->getServiceConfig('security');
        
        return [
            'status' => $status,
            'config' => $config
        ];
    }

    public function analyticsDashboard() {
        // 分析服务管理逻辑
        $stats = $this->analyticsService->getStats();
        $config = $this->dbHelper->getServiceConfig('analytics');
        
        return [
            'stats' => $stats,
            'config' => $config
        ];
    }

    public function incidentDashboard() {
        // 事件响应服务管理逻辑
        $incidents = $this->incidentService->getRecentIncidents();
        $config = $this->dbHelper->getServiceConfig('incident');
        
        return [
            'incidents' => $incidents,
            'config' => $config
        ];
    }

    public function updateServiceConfig($service, $config) {
        // 记录操作前配置
        $oldConfig = $this->dbHelper->getServiceConfig($service);
        
        // 更新服务配置
        $result = $this->dbHelper->saveServiceConfig($service, $config);
        
        // 记录操作日志
        $this->logServiceOperation(
            'config_update',
            $service,
            [
                'old_config' => $oldConfig,
                'new_config' => $config,
                'changed_by' => $_SESSION['admin_id'] ?? null
            ]
        );
        
        return $result;
    }
    
    /**
     * 记录服务管理操作
     */
    private function logServiceOperation(
        string $action,
        string $service,
        array $data = []
    ) {
        $logData = [
            'admin_id' => $_SESSION['admin_id'] ?? null,
            'service' => $service,
            'action' => $action,
            'ip' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'data' => json_encode($data),
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        $this->dbHelper->insert('service_operation_logs', $logData);
    }
}