<?php
namespace Services;

class DocExportService {
    private $formats = [
        'pdf' => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'epub' => 'application/epub+zip'
    ];
    
    public function export(string $docId, string $format): string {
        if (!isset($this->formats[$format])) {
            throw new \InvalidArgumentException("Unsupported format: {$format}");
        }
        
        $content = $this->getDocContent($docId);
        return match($format) {
            'pdf' => $this->toPDF($content),
            'docx' => $this->toDocx($content),
            'epub' => $this->toEpub($content)
        };
    }
    
    private function toPDF(array $content): string {
        // 实现PDF导出
        $pdf = new \TCPDF();
        // ...配置和渲染逻辑
        return $pdf->Output('', 'S');
    }
}
