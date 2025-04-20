<?php
namespace Services;

class MLPredictionService {
    private $models = [];
    private $evaluator;
    private $redis;
    
    public function __construct() {
        $this->redis = new \Redis();
        $this->evaluator = new ModelEvaluationService();
        $this->initModels();
    }
    
    public function getPredictions(array $configs): array {
        $predictions = [];
        $modelMetrics = [];
        
        foreach ($configs as $model => $config) {
            // A/B测试支持
            if ($this->shouldRunABTest($model)) {
                $predictions[$model] = $this->runABTest($model, $config);
                continue;
            }
            
            // 正常预测
            $prediction = $this->models[$model]->predict($config);
            $predictions[$model] = $prediction;
            
            // 评估模型性能
            if ($config['evaluate'] ?? false) {
                $modelMetrics[$model] = $this->evaluator->evaluate($model, $prediction);
            }
        }
        
        return [
            'predictions' => $predictions,
            'metrics' => $modelMetrics
        ];
    }
    
    private function shouldRunABTest(string $model): bool {
        return (bool)$this->redis->get("ab_test:{$model}:enabled");
    }
    
    private function runABTest(string $model, array $config): array {
        $variants = ['A' => $this->models[$model], 'B' => $this->models["{$model}_new"]];
        $results = [];
        
        foreach ($variants as $variant => $model) {
            $prediction = $model->predict($config);
            $accuracy = $this->evaluator->evaluate($model, $prediction);
            
            $results[$variant] = [
                'prediction' => $prediction,
                'accuracy' => $accuracy
            ];
        }
        
        // 记录A/B测试结果
        $this->logABTestResult($model, $results);
        
        // 返回性能更好的变体结果
        return $results['A']['accuracy'] > $results['B']['accuracy'] 
            ? $results['A']['prediction'] 
            : $results['B']['prediction'];
    }
    
    private function initModels(): void {
        $this->models = [
            'lstm' => new \ML\Models\LSTM([
                'layers' => [64, 32],
                'optimizer' => 'adam'
            ]),
            'xgboost' => new \ML\Models\XGBoost([
                'objective' => 'reg:squarederror',
                'eval_metric' => 'rmse'
            ]),
            'prophet' => new \ML\Models\Prophet([
                'yearly_seasonality' => true,
                'weekly_seasonality' => true
            ])
        ];
    }
}
