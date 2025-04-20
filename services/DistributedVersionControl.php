<?php
namespace Services;

class DistributedVersionControl {
    private $redis;
    private $currentVersion;
    
    public function __construct() {
        $this->redis = new \Redis();
        $this->loadCurrentVersion();
    }
    
    public function createVersion(string $branch, array $changes): string {
        $versionId = $this->generateVersionId();
        
        $versionData = [
            'id' => $versionId,
            'branch' => $branch,
            'changes' => $changes,
            'timestamp' => time(),
            'author' => $_SESSION['user_id'] ?? 'system',
            'parent' => $this->currentVersion
        ];
        
        // 原子性操作保存版本
        $this->redis->multi();
        try {
            $this->redis->hSet("versions:$versionId", $versionData);
            $this->redis->lPush("branch:$branch", $versionId);
            $this->redis->exec();
            
            return $versionId;
        } catch (\Exception $e) {
            $this->redis->discard();
            throw $e;
        }
    }
    
    public function merge(string $sourceBranch, string $targetBranch): bool {
        $this->redis->watch("branch:$targetBranch");
        
        try {
            $sourceVersion = $this->redis->lIndex("branch:$sourceBranch", 0);
            $targetVersion = $this->redis->lIndex("branch:$targetBranch", 0);
            
            // 检查冲突
            if ($this->hasConflicts($sourceVersion, $targetVersion)) {
                throw new \Exception("存在合并冲突");
            }
            
            // 创建合并版本
            $mergeVersion = $this->createMergeVersion($sourceVersion, $targetVersion);
            
            $this->redis->multi();
            $this->redis->lPush("branch:$targetBranch", $mergeVersion);
            return $this->redis->exec() !== false;
        } catch (\Exception $e) {
            $this->redis->discard();
            throw $e;
        }
    }
}
