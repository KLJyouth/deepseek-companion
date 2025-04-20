<?php
namespace Services;

class RuleUpdateService {
    private $redis;
    private $logger;
    private $lastUpdateKey = 'waf:rules:last_update';
    private $ruleVersionKey = 'waf:rules:version';
    
    public function __construct() {
        $this->redis = new \Redis();
        $this->logger = LogHelper::getInstance();
    }
    
    public function checkForUpdates(): bool {
        $lastUpdate = $this->redis->get($this->lastUpdateKey) ?? 0;
        if (time() - $lastUpdate < 3600) { // 每小时检查一次
            return false;
        }

        // 从远程规则服务获取最新规则版本
        $latestRules = $this->fetchLatestRules();
        if ($latestRules) {
            $this->updateRules($latestRules);
            return true;
        }
        return false;
    }

    private function updateRules(array $rules): void {
        $this->redis->multi();
        try {
            $this->redis->set($this->ruleVersionKey, $rules['version']);
            $this->redis->hMSet('waf:rules:patterns', $rules['patterns']);
            $this->redis->set($this->lastUpdateKey, time());
            $this->redis->exec();
            
            $this->logger->info('WAF规则已更新', ['version' => $rules['version']]);
        } catch (\Exception $e) {
            $this->redis->discard();
            throw $e;
        }
    }
}
