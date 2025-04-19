<?php
namespace Admin\Models;

use Libs\DatabaseHelper;

class OperationLog extends DatabaseHelper {
    protected $table = 'admin_operation_logs';

    public function log($adminId, $action, $details) {
        $this->create([
            'admin_id' => $adminId,
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
            'action' => $action,
            'details' => json_encode($details),
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
}