<?php
namespace Services;

class RuleVersionService {
    private $redis;
    
    public function __construct() {
        $this->redis = new \Redis();
    }

    public function saveVersion(array $rules): string {
        $version = date('YmdHis') . '_' . substr(md5(json_encode($rules)), 0, 6);
        
        // 保存规则版本
        $this->redis->hSet('rules:versions', $version, json_encode([
            'rules' => $rules,
            'created_at' => time(),
            'created_by' => $_SESSION['user_id'] ?? 'system'
        ]));
        
        // 维护版本历史
        $this->redis->lPush('rules:history', $version);
        
        return $version;
    }

    public function rollback(string $version): bool {
        $ruleData = $this->redis->hGet('rules:versions', $version);
        if (!$ruleData) {
            throw new \Exception("规则版本不存在: " . $version);
        }

        $rules = json_decode($ruleData, true);
        
        // 执行回滚
        try {
            $this->redis->multi();
            $this->redis->set('rules:current', json_encode($rules['rules']));
            $this->redis->set('rules:last_rollback', json_encode([
                'version' => $version,
                'timestamp' => time(),
                'operator' => $_SESSION['user_id'] ?? 'system'
            ]));
            $this->redis->exec();
            
            $this->logger->info("规则回滚成功", ['version' => $version]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error("规则回滚失败", [
                'version' => $version,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
