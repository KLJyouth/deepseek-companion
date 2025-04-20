<?php
namespace Services;

class CustomReportBuilder {
    private $template;
    private $data;
    
    public function setTemplate(string $template): self {
        $this->template = $template;
        return $this;
    }
    
    public function setData(array $data): self {
        $this->data = $data;
        return $this;
    }
    
    public function generate(string $format = 'pdf'): string {
        $reportData = $this->processData();
        
        return match($format) {
            'pdf' => $this->generatePDF($reportData),
            'excel' => $this->generateExcel($reportData),
            'html' => $this->generateHTML($reportData),
            default => throw new \InvalidArgumentException("Unsupported format: {$format}")
        };
    }
    
    private function processData(): array {
        // 处理数据并应用模板
        return [
            'metrics' => $this->aggregateMetrics(),
            'charts' => $this->generateCharts(),
            'summary' => $this->generateSummary()
        ];
    }
}
