<?php
namespace Services;

class ReportTemplateService {
    private $db;
    private $cache;
    
    public function __construct() {
        $this->db = \Libs\DatabaseHelper::getInstance();
        $this->cache = new \Redis();
    }

    public function saveTemplate(array $template): int {
        $id = $this->db->insert('report_templates', [
            'name' => $template['name'],
            'content' => json_encode($template['content']),
            'variables' => json_encode($template['variables']),
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $this->cache->del('report:templates:list');
        return $id;
    }

    public function renderTemplate(int $templateId, array $data): string {
        $template = $this->getTemplate($templateId);
        if (!$template) {
            throw new \Exception("Template not found");
        }

        $content = $template['content'];
        foreach ($template['variables'] as $var) {
            $placeholder = "{{" . $var . "}}";
            $value = $data[$var] ?? '';
            $content = str_replace($placeholder, $value, $content);
        }

        return $content;
    }

    public function compileTemplate(array $template): callable {
        return eval('return function($data) { ' . 
            'extract($data); ' . 
            'ob_start(); ?>' . 
            $template['content'] . 
            '<?php return ob_get_clean(); };'
        );
    }
}
