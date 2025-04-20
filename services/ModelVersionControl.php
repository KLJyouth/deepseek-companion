<?php
namespace Services;

class ModelVersionControl {
    private $db;
    
    public function createVersion(string $modelId, array $modelData): string {
        $version = $this->generateVersion();
        
        $this->db->transaction(function($db) use ($modelId, $modelData, $version) {
            $db->insert('model_versions', [
                'model_id' => $modelId,
                'version' => $version,
                'data' => json_encode($modelData),
                'created_at' => date('Y-m-d H:i:s')
            ]);
        });
        
        return $version;
    }
    
    public function promoteVersion(string $modelId, string $version): void {
        $this->validateVersion($modelId, $version);
        
        $this->db->transaction(function($db) use ($modelId, $version) {
            $db->update('models', 
                ['active_version' => $version],
                'id = ?',
                [['value' => $modelId]]
            );
        });
    }
}
