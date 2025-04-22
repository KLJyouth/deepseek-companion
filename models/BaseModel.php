<?php
namespace Models;

use Libs\DatabaseHelper;
use Libs\LogHelper;

abstract class BaseModel
{
    protected static string $table;
    protected static string $primaryKey = 'id';
    protected DatabaseHelper $db;
    protected LogHelper $logger;
    protected array $attributes = [];
    
    public function __construct()
    {
        $this->db = DatabaseHelper::getInstance();
        $this->logger = LogHelper::getInstance();
    }

    /**
     * 通过ID查找记录
     * @param int|string $id
     * @return static|null
     */
    public static function find(int|string $id): ?static 
    {
        $instance = new static();
        $rows = $instance->db->getRows(
            "SELECT * FROM " . static::$table . " WHERE " . static::$primaryKey . " = ?",
            [$id]
        );
        
        if (!empty($rows)) {
            $instance->attributes = $rows[0];
            return $instance;
        }
        return null;
    }

    /**
     * 创建新记录
     * @param array<string,mixed> $data
     * @return int
     */
    public static function create(array $data): int
    {
        $instance = new static();
        $instance->validate($data);
        
        $id = $instance->db->insert(static::$table, $data);
        $instance->logger->info("Created " . static::$table . " record: {$id}");
        
        return $id;
    }

    /**
     * 验证数据
     * @param array<string,mixed> $data
     * @throws \InvalidArgumentException
     */
    abstract protected function validate(array $data): void;
}
