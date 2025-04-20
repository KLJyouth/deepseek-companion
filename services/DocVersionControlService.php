<?php
namespace Services;

class DocVersionControlService {
    private $db;
    private $redis;
    
    public function __construct() {
        $this->db = \Libs\DatabaseHelper::getInstance();
        $this->redis = new \Redis();
    }
    
    public function createVersion(string $docId, array $content): string {
        $version = date('YmdHis') . '_' . substr(md5(json_encode($content)), 0, 6);
        
        $this->db->transaction(function($db) use ($docId, $version, $content) {
            $db->insert('doc_versions', [
                'doc_id' => $docId,
                'version' => $version,
                'content' => json_encode($content),
                'created_at' => date('Y-m-d H:i:s'),
                'created_by' => $_SESSION['user_id'] ?? 'system'
            ]);
        });
        
        return $version;
    }
    
    public function diffVersions(string $v1, string $v2): array {
        // 实现文档版本差异对比
        return [
            'added' => [],
            'removed' => [],
            'modified' => []
        ];
    }
    
    public function handleConflict(string $docId, array $changes): array {
        $baseVersion = $this->getBaseVersion($docId);
        $currentChanges = $this->getCurrentChanges($docId);
        
        $conflicts = $this->detectConflicts($baseVersion, $changes, $currentChanges);
        
        if (!empty($conflicts)) {
            return [
                'status' => 'conflict',
                'conflicts' => $conflicts,
                'resolution_suggestions' => $this->generateResolutionSuggestions($conflicts)
            ];
        }
        
        return ['status' => 'success', 'merged' => $this->mergeChanges($changes)];
    }
    
    private function detectConflicts(array $base, array $changes, array $current): array {
        return array_filter($changes, function($change) use ($base, $current) {
            $path = $change['path'];
            return isset($current[$path]) && $current[$path] !== $base[$path];
        });
    }
}
