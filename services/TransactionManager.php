<?php
namespace Services;

class TransactionManager {
    private static $instance;
    private $transactions = [];
    private $redis;
    
    public function __construct() {
        $this->redis = new \Redis();
        $this->redis->connect(
            $_ENV['REDIS_HOST'] ?? 'localhost',
            $_ENV['REDIS_PORT'] ?? 6379
        );
    }
    
    public static function begin(): string {
        $xid = uniqid('tx_', true);
        self::getInstance()->transactions[$xid] = [
            'status' => 'begin',
            'timestamp' => time(),
            'branches' => []
        ];
        return $xid;
    }
    
    public static function commit(string $xid): bool {
        $instance = self::getInstance();
        $tx = $instance->transactions[$xid] ?? null;
        
        if (!$tx) {
            return false;
        }
        
        try {
            foreach ($tx['branches'] as $branch) {
                $instance->commitBranch($branch);
            }
            
            $instance->transactions[$xid]['status'] = 'committed';
            return true;
        } catch (\Exception $e) {
            $instance->rollback($xid);
            throw $e;
        }
    }
    
    private static function getInstance(): self {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
