<?php
namespace Database;

use Faker\Factory as FakerFactory;
use Libs\DatabaseHelper;
use Libs\LogHelper;

class TestDataFactory {
    private $faker;
    private $db;
    private $logger;
    private $config = [
        'min' => 1,
        'max' => 10,
        'locale' => 'en_US'
    ];

    public function __construct(DatabaseHelper $db, LogHelper $logger, array $config = []) {
        $this->db = $db;
        $this->logger = $logger;
        $this->config = array_merge($this->config, $config);
        $this->faker = FakerFactory::create($this->config['locale']);
    }

    public function create(string $model, array $overrides = [], ?int $count = null): array {
        $count = $count ?? $this->faker->numberBetween($this->config['min'], $this->config['max']);
        $instances = [];
        
        for ($i = 0; $i < $count; $i++) {
            $data = $this->getModelDefinition($model);
            $data = array_merge($data, $overrides);
            
            try {
                $id = $this->db->insert($this->getTableName($model), $data);
                $instances[] = array_merge(['id' => $id], $data);
                $this->logger->debug("Created {$model} #{$id}");
            } catch (\Exception $e) {
                $this->logger->error("Failed to create {$model}: " . $e->getMessage());
                throw $e;
            }
        }
        
        return $instances;
    }

    public function createForRelationship(string $model, string $relation, array $parentIds): array {
        $instances = [];
        
        foreach ($parentIds as $parentId) {
            $count = $this->faker->numberBetween($this->config['min'], $this->config['max']);
            for ($i = 0; $i < $count; $i++) {
                $data = $this->getModelDefinition($model);
                $data[$relation.'_id'] = $parentId;
                
                try {
                    $id = $this->db->insert($this->getTableName($model), $data);
                    $instances[] = array_merge(['id' => $id], $data);
                    $this->logger->debug("Created {$model} #{$id} for {$relation} #{$parentId}");
                } catch (\Exception $e) {
                    $this->logger->error("Failed to create {$model} for {$relation}: " . $e->getMessage());
                }
            }
        }
        
        return $instances;
    }

    protected function getModelDefinition(string $model): array {
        $method = 'define'.str_replace(' ', '', ucwords(str_replace('_', ' ', $model)));
        if (method_exists($this, $method)) {
            return $this->$method();
        }
        
        throw new \RuntimeException("No definition found for model: {$model}");
    }

    protected function getTableName(string $model): string {
        return str_replace('_', '', $model);
    }

    // 示例模型定义
    protected function defineUser(): array {
        return [
            'username' => $this->faker->userName,
            'password_hash' => password_hash('password', PASSWORD_BCRYPT),
            'email' => $this->faker->email,
            'is_admin' => $this->faker->boolean(20),
            'created_at' => $this->faker->dateTimeThisYear->format('Y-m-d H:i:s')
        ];
    }

    protected function defineContract(): array {
        return [
            'title' => $this->faker->sentence,
            'content' => $this->faker->paragraphs(3, true),
            'created_by' => 1, // 默认管理员创建
            'status' => $this->faker->randomElement(['draft', 'published', 'archived']),
            'created_at' => $this->faker->dateTimeThisYear->format('Y-m-d H:i:s')
        ];
    }
}