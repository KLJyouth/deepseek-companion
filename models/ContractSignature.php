<?php
namespace App\Models;

use App\Models\BaseModel;
use InvalidArgumentException;

class ContractSignature extends BaseModel
{
    protected static string $table = 'ac_signatures';
    
    /** @var array<string> 必填字段 */
    protected array $required = ['contract_id', 'signer_id', 'signature_data'];
    
    /** @var array<string,array<string,mixed>> 验证规则 */
    protected array $rules = [
        'contract_id' => ['type' => 'int'],
        'signer_id' => ['type' => 'int'],
        'signature_data' => ['type' => 'string', 'min' => 10],
        'signed_at' => ['type' => 'string', 'optional' => true]
    ];

    /**
     * 验证签名数据
     * @param array<string,mixed> $data
     * @throws \InvalidArgumentException
     */
    protected function validate(array $data): void
    {
        // 验证必填字段
        foreach ($this->required as $field) {
            if (!isset($data[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }
        
        // 验证数据类型
        foreach ($data as $field => $value) {
            if (isset($this->rules[$field])) {
                $rule = $this->rules[$field];
                if ($rule['type'] === 'int' && !is_int($value)) {
                    throw new \InvalidArgumentException("Field {$field} must be integer");
                }
                if ($rule['type'] === 'string' && !is_string($value)) {
                    throw new \InvalidArgumentException("Field {$field} must be string");
                }
                if ($rule['type'] === 'string' && isset($rule['min']) && strlen($value) < $rule['min']) {
                    throw new \InvalidArgumentException("Field {$field} too short");
                }
            }
        }
    }

    /**
     * 根据合同ID查找签名信息
     * @param int $contractId 合同ID
     * @return array<int,array<string,mixed>> 签名信息数组
     */
    public static function findByContract(int $contractId): array
    {
        $instance = new static();
        return $instance->db->getRows(
            "SELECT * FROM " . static::$table . " WHERE contract_id = ?",
            [$contractId]
        );
    }
}