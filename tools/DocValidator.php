<?php
namespace Tools;

class DocValidator {
    private $docsPath;
    private $cacheFile;
    private $lastCheck;
    private $config;
    private $rules;
    private $metrics;
    
    public function __construct() {
        $this->docsPath = __DIR__ . '/../docs';
        $this->cacheFile = __DIR__ . '/../storage/cache/doc_validation.json';
        $this->loadLastCheck();
        
        // 增加更多验证规则
        $this->rules = [
            'api_docs' => [
                'endpoints_must_have_examples',
                'response_must_have_schema',
                'security_must_be_defined'
            ],
            'code_samples' => [
                'must_be_compilable',
                'must_have_comments',
                'must_follow_psr12'
            ],
            'architecture' => [
                'must_have_sequence_diagrams',
                'must_describe_dependencies',
                'must_list_security_measures'
            ]
        ];
        
        // 新增性能指标
        $this->metrics = [
            'doc_coverage' => 0,
            'code_sample_validity' => 0,
            'i18n_completeness' => 0
        ];
    }
    
    public function validateAll(): array {
        return [
            'freshness' => $this->checkFreshness(),
            'code_samples' => $this->validateCodeSamples(),
            'differences' => $this->generateDiffReport()
        ];
    }
    
    private function checkFreshness(): array {
        $staleFiles = [];
        foreach (glob($this->docsPath . '/*.md') as $file) {
            if ($this->isStale($file)) {
                $staleFiles[] = basename($file);
            }
        }
        return $staleFiles;
    }
    
    private function validateCodeSamples(): array {
        $results = [];
        foreach ($this->extractCodeSamples() as $sample) {
            $results[] = [
                'file' => $sample['file'],
                'valid' => $this->validateSyntax($sample['code']),
                'dependencies' => $this->checkDependencies($sample['code'])
            ];
        }
        return $results;
    }

    public function validateWithRules(array $customRules = []): array {
        $results = [];
        $rules = array_merge($this->rules, $customRules);
        
        foreach ($rules as $category => $categoryRules) {
            $results[$category] = $this->validateCategory($category, $categoryRules);
        }
        
        return $results;
    }
}
