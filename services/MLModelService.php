<?php
namespace Services;

use Rubix\ML\Pipeline;
use Rubix\ML\PersistentModel;
use Rubix\ML\Persisters\Filesystem;

class MLModelService {
    private $modelsPath;
    private $pipeline;
    private $redis;
    
    public function __construct(string $modelsPath = null) {
        $this->modelsPath = $modelsPath ?? __DIR__ . '/../storage/ml_models';
        $this->redis = new \Redis();
        $this->initializePipeline();
    }

    public function trainModel(string $modelName, array $trainingData): void {
        $model = $this->loadModel($modelName);
        $model->train($trainingData);
        $this->saveModel($modelName, $model);
    }
    
    public function predict(string $modelName, array $data): array {
        $cacheKey = "ml:predictions:{$modelName}:" . md5(json_encode($data));
        
        if ($cached = $this->redis->get($cacheKey)) {
            return json_decode($cached, true);
        }
        
        $model = $this->loadModel($modelName);
        $prediction = $model->predict($data);
        
        $this->redis->setex($cacheKey, 3600, json_encode($prediction));
        return $prediction;
    }

    private function initializePipeline(): void {
        $this->pipeline = new Pipeline([
            new \Rubix\ML\Transformers\NumericStringConverter(),
            new \Rubix\ML\Transformers\MissingDataImputer(),
            new \Rubix\ML\Transformers\OneHotEncoder()
        ]);
    }
}
