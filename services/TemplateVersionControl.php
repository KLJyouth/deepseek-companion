<?php
namespace Services;

class TemplateVersionControl {
    private $db;
    
    public function createVersion(array $template): string {
        $version = $this->generateVersionHash($template);
        
        $this->db->transaction(function($db) use ($template, $version) {
            $db->insert('template_versions', [
                'version_hash' => $version,
                'template_id' => $template['id'],
                'content' => json_encode($template['content']),
                'metadata' => json_encode([
                    'author' => $template['author'],
                    'timestamp' => time(),
                    'changes' => $template['changes']
                ])
            ]);
            
            // 更新模板引用
            $db->update('templates', 
                ['current_version' => $version],
                'id = ?',
                [['value' => $template['id'], 'type' => 'i']]
            );
        });
        
        return $version;
    }
    
    public function checkout(string $version): array {
        return $this->db->getRow(
            "SELECT * FROM template_versions WHERE version_hash = ?",
            [['value' => $version, 'type' => 's']]
        );
    }
}
