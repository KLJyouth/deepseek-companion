<?php
namespace Services;

class CacheWarmupService 
{
    private $cache;
    private $db;
    
    public function __construct()
    {
        $this->cache = CacheService::getInstance();
        $this->db = \Libs\DatabaseHelper::getInstance();
    }

    public function warmup(): array
    {
        $stats = ['success' => 0, 'failed' => 0];
        
        try {
            // 预热合同模板
            $templates = $this->db->getRows("SELECT * FROM contract_templates LIMIT 1000");
            foreach ($templates as $tpl) {
                $key = "template:{$tpl['id']}";
                $this->cache->set($key, $tpl, 3600);
                $stats['success']++;
            }

            // 预热用户数据
            $users = $this->db->getRows("SELECT id,name,status FROM users WHERE active=1 LIMIT 1000");
            foreach ($users as $user) {
                $key = "user:basic:{$user['id']}";
                $this->cache->set($key, $user, 1800);
                $stats['success']++;
            }

            // 预热配置数据
            $configs = $this->db->getRows("SELECT * FROM system_configs");
            $this->cache->set('system:configs', $configs, 7200);
            $stats['success']++;

        } catch (\Exception $e) {
            $stats['failed']++;
            throw $e;
        }

        return $stats;
    }
}
