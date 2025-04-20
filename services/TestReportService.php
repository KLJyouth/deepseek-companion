<?php
namespace Services;

class TestReportService {
    private $metrics;
    
    public function __construct(TestMetricsService $metrics) {
        $this->metrics = $metrics;
    }

    public function generateReport(string $format = 'pdf'): string {
        $data = $this->metrics->collectMetrics();
        
        switch ($format) {
            case 'pdf':
                return $this->generatePDFReport($data);
            case 'excel':
                return $this->generateExcelReport($data);
            case 'json':
                return $this->generateJSONReport($data);
            default:
                throw new \InvalidArgumentException('Unsupported format');
        }
    }

    private function generatePDFReport(array $data): string {
        // PDF生成逻辑
        $pdf = new \TCPDF();
        $pdf->AddPage();
        
        // 添加报告内容
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(0, 10, 'Test Report ' . date('Y-m-d H:i:s'), 0, 1);
        
        foreach ($data as $category => $metrics) {
            $pdf->Cell(0, 10, ucfirst($category) . ' Metrics:', 0, 1);
            foreach ($metrics['data'] as $key => $value) {
                $pdf->Cell(0, 8, "$key: $value", 0, 1);
            }
        }
        
        return $pdf->Output('report.pdf', 'S');
    }
}
