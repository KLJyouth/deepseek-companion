<?php
namespace Services;

class AlertHistoryService {
    private $redis;
    private $db;
    
    public function __construct() {
        $this->redis = new \Redis();
        $this->db = \Libs\DatabaseHelper::getInstance();
    }
    
    public function recordAlert(array $alert): void {
        // 记录告警到数据库
        $this->db->insert('alert_history', [
            'type' => $alert['type'],
            'message' => $alert['message'],
            'level' => $alert['level'],
            'context' => json_encode($alert['context']),
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // 缓存最近告警
        $this->redis->lPush('recent:alerts', json_encode($alert));
        $this->redis->lTrim('recent:alerts', 0, 99); // 保留最近100条
    }

    public function getAlertHistory(array $filters = []): array {
        $sql = "SELECT * FROM alert_history WHERE 1=1";
        $params = [];

        if (!empty($filters['start_date'])) {
            $sql .= " AND created_at >= ?";
            $params[] = ['value' => $filters['start_date'], 'type' => 's'];
        }

        if (!empty($filters['type'])) {
            $sql .= " AND type = ?";
            $params[] = ['value' => $filters['type'], 'type' => 's'];
        }

        $sql .= " ORDER BY created_at DESC LIMIT 1000";
        
        return $this->db->getRows($sql, $params);
    }
}
