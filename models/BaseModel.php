<?php
namespace Models;

use Libs\DatabaseHelper;
use Libs\LogHelper;

abstract class BaseModel {
    protected static $db;
    protected static $logger;
    
    protected $table;
    protected $primaryKey = 'id';
    
    public static function init(DatabaseHelper $db, LogHelper $logger): void {
        self::$db = $db;
        self::$logger = $logger;
    }
    
    public static function getInstance(): DatabaseHelper {
        return self::$db;
    }
    
    public static function find(int $id): ?self {
        $model = new static();
        $result = self::$db->getRow(
            "SELECT * FROM {$model->table} WHERE {$model->primaryKey} = ?",
            [$id]
        );
        return $result ? $model->fill($result) : null;
    }
    
    public static function where(string $column, $value): array {
        $model = new static();
        $rows = self::$db->getRows(
            "SELECT * FROM {$model->table} WHERE {$column} = ?",
            [$value]
        );
        return array_map([$model, 'fill'], $rows);
    }
    
    public static function whereIn(string $column, array $values): array {
        $model = new static();
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $rows = self::$db->getRows(
            "SELECT * FROM {$model->table} WHERE {$column} IN ($placeholders)",
            $values
        );
        return array_map([$model, 'fill'], $rows);
    }
    
    public function fill(array $data): self {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
        return $this;
    }
    
    public function save(): bool {
        $data = $this->toArray();
        
        if (empty($data[$this->primaryKey])) {
            $id = self::$db->insert($this->table, $data);
            if ($id) {
                $this->{$this->primaryKey} = $id;
                return true;
            }
        } else {
            return self::$db->update(
                $this->table,
                $data,
                [$this->primaryKey => $data[$this->primaryKey]]
            ) > 0;
        }
        
        return false;
    }
    
    public function toArray(): array {
        $data = [];
        foreach (get_object_vars($this) as $key => $value) {
            if ($key !== 'table' && $key !== 'primaryKey') {
                $data[$key] = $value;
            }
        }
        return $data;
    }
    
    public static function all(): array {
        $model = new static();
        $rows = self::$db->getRows("SELECT * FROM {$model->table}");
        return array_map([$model, 'fill'], $rows);
    }
    
    public function delete(): bool {
        if (!empty($this->{$this->primaryKey})) {
            return self::$db->delete(
                $this->table,
                [$this->primaryKey => $this->{$this->primaryKey}]
            ) > 0;
        }
        return false;
    }
}