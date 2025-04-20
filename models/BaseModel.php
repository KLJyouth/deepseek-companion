<?php
namespace Models;

use Libs\DatabaseHelper;
use Libs\LogHelper;

abstract class BaseModel
{
    protected static $table;
    protected static $primaryKey = 'id';
    protected $db;
    protected $logger;
    protected $attributes = [];
    
    public function __construct()
    {
        $this->db = DatabaseHelper::getInstance();
        $this->logger = LogHelper::getInstance();
    }

    /**
     * 通过ID查找记录
     */
    public static function find($id): ?static 
    {
        $instance = new static();
        $result = $instance->db->query(
            "SELECT * FROM " . static::$table . " WHERE " . static::$primaryKey . " = ?",
            [$id]
        );
        
        if ($result && $row = $result->fetch_assoc()) {
            $instance->attributes = $row;
            return $instance;
        }
        return null;
    }

    /**
     * 创建新记录
     */
    public static function create(array $data): int
    {
        $instance = new static();
        $instance->validate($data);
        
        $id = $instance->db->insert(static::$table, $data);
        $instance->logger->info("Created {$instance->table} record: {$id}");
        
        return $id;
    }

    abstract protected function validate(array $data): void;
}
