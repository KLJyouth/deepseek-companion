<?php
namespace Libs;

use Exception;
use Rubix\ML\Datasets\Labeled;
use Rubix\ML\Datasets\Unlabeled;
use Rubix\ML\Extractors\CSV;
use Rubix\ML\Classifiers\RandomForest;
use Rubix\ML\CrossValidation\Metrics\Accuracy;

/**
 * 预测性安全运维系统
 */
class SecurityPredictor {
    private $model;
    private $trainingDataPath = '/var/security_data/training.csv';
    private $predictionWindow = 24; // 预测窗口(小时)
    
    public function __construct() {
        $this->initializeModel();
    }
    
    private function initializeModel(): void {
        // 使用随机森林算法
        $this->model = new RandomForest(100, 0.7, 10);
    }
    
    /**
     * 实时行为分析接口
     */
    public function realTimePredict(array $behaviorData): int {
        // 将行为数据转换为特征向量
        $features = $this->extractFeatures($behaviorData); 
        $dataset = new Unlabeled([$features]);
        
        // 使用预训练的随机森林模型预测威胁等级(0-5级)
        return $this->model->predict($dataset)[0]; 
    }
    
    /**
     * 特征提取方法
     */
    private function extractFeatures(array $data): array {
        return [
            $data['request_count'],       // 分钟内请求次数
            $data['time_since_last'],     // 上次请求间隔
            $data['ip_entropy'],          // IP地址熵值
            $data['uri_depth'],           // 请求URI深度
        ];
    }
    
    /**
     * 训练预测模型
     */
    public function trainModel(): void {
        try {
            // 加载训练数据
            $dataset = $this->loadTrainingData();
            
            // 训练模型
            $this->model->train($dataset);
            
            // 评估模型
            $accuracy = $this->evaluateModel($dataset);
            file_put_contents('/var/log/security_model.log', 
                date('Y-m-d H:i:s')." Model trained. Accuracy: $accuracy\n", FILE_APPEND);
            
        } catch (Exception $e) {
            error_log('模型训练失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 预测攻击窗口期
     */
    public function predictAttackWindow(): array {
        $predictions = [];
        $currentHour = (int)date('H');
        
        try {
            // 准备预测数据
            $dataset = $this->preparePredictionData();
            
            // 执行预测
            $results = $this->model->predict($dataset);
            
            // 生成预测结果
            for ($i = 0; $i < $this->predictionWindow; $i++) {
                $hour = ($currentHour + $i) % 24;
                $predictions[$hour] = [
                    'risk_level' => $results[$i],
                    'confidence' => $this->model->proba($dataset)[$i][1] ?? 0.5
                ];
            }
            
        } catch (Exception $e) {
            error_log('攻击窗口预测失败: ' . $e->getMessage());
        }
        
        return $predictions;
    }
    
    /**
     * 生成防护策略建议
     */
    public function generateDefenseStrategies(array $predictions): array {
        $strategies = [];
        $highRiskHours = array_filter($predictions, fn($p) => $p['risk_level'] === 'high');
        
        if (count($highRiskHours) > 0) {
            $strategies[] = [
                'type' => 'enhanced_monitoring',
                'hours' => array_keys($highRiskHours),
                'action' => '增加监控频率和日志记录'
            ];
            
            $strategies[] = [
                'type' => 'access_restriction',
                'hours' => array_keys($highRiskHours),
                'action' => '限制敏感API访问'
            ];
        }
        
        return $strategies;
    }
    
    /**
     * 可视化安全气象图数据
     */
    public function generateWeatherMapData(): array {
        $predictions = $this->predictAttackWindow();
        
        return [
            'predictions' => $predictions,
            'strategies' => $this->generateDefenseStrategies($predictions),
            'updated_at' => date('c')
        ];
    }
    
    private function loadTrainingData(): Labeled {
        $extractor = new CSV($this->trainingDataPath, true);
        return Labeled::fromIterator($extractor);
    }
    
    private function preparePredictionData(): Unlabeled {
        // 实际项目中应从监控系统获取实时数据
        $features = [];
        for ($i = 0; $i < $this->predictionWindow; $i++) {
            $features[] = [
                'hour' => $i,
                'day_of_week' => date('N'),
                'network_traffic' => rand(100, 1000),
                'failed_logins' => rand(0, 10)
            ];
        }
        return new Unlabeled($features);
    }
    
    private function evaluateModel(Labeled $dataset): float {
        $testing = $dataset->randomize()->take((int)($dataset->numRows() * 0.2));
        $predictions = $this->model->predict($testing);
        return (new Accuracy())->score($predictions, $testing->labels());
    }
}
