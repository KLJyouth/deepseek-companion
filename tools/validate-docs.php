<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Tools\DocValidator;

$validator = new DocValidator();
$results = $validator->validateAll();

// 检查文档时效性
if (!empty($results['freshness'])) {
    echo "Warning: Found stale documentation files:\n";
    foreach ($results['freshness'] as $file) {
        echo "- {$file}\n";
    }
}

// 验证代码示例
if (!empty($results['code_samples'])) {
    foreach ($results['code_samples'] as $result) {
        if (!$result['valid']) {
            echo "Error: Invalid code sample in {$result['file']}\n";
        }
    }
}

// 生成差异报告
file_put_contents(
    __DIR__ . '/../reports/doc-validation.md',
    generateMarkdownReport($results)
);

function generateMarkdownReport($results): string {
    $report = "# Documentation Validation Report\n\n";
    $report .= "## Summary\n\n";
    $report .= "- Stale files: " . count($results['freshness']) . "\n";
    $report .= "- Invalid samples: " . count(array_filter($results['code_samples'], fn($r) => !$r['valid'])) . "\n";
    
    // 添加更多报告内容...
    
    return $report;
}

exit(empty($results['freshness']) && empty($results['invalid_samples']) ? 0 : 1);
