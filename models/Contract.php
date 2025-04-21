<?php
namespace Models;

use Traits\InputValidation;
use Models\BaseModel;
use Models\ContractSignature;

class Contract extends BaseModel
{
    use InputValidation;

    protected static string $table = 'contracts';
    
    /** @var array<string,string> 必填字段 */
    protected array $required = ['title', 'content', 'parties'];
    
    /** 
     * @var array<string,array<string,mixed>> 字段验证规则
     */
    protected array $rules = [
        'title' => ['type' => 'string', 'max' => 255],
        'content' => ['type' => 'string', 'min' => 10],
        'parties' => ['type' => 'array'],
        'status' => ['type' => 'string', 'enum' => ['draft', 'signed', 'completed']]
    ];

    /**
     * 验证合同数据
     * @param array<string,mixed> $data 合同数据
     * @throws \InvalidArgumentException 当数据验证失败时
     */
    protected function validate(array $data): void
    {
        $this->validateRequired($data);
        $this->validateRules($data);
    }

    /**
     * 获取合同签名信息
     * @return array<int,array<string,mixed>> 签名信息数组
     * @throws \RuntimeException 当查询失败时
     */
    public function signatures(): array
    {
        if (!isset($this->attributes['id'])) {
            throw new \RuntimeException('Contract ID is not set');
        }
        return ContractSignature::findByContract($this->attributes['id']);
    }

    /**
     * 检查合同是否已过期
     * @return bool 是否过期
     */
    public function isExpired(): bool
    {
        return isset($this->attributes['expire_at']) && 
               strtotime($this->attributes['expire_at']) < time();
    }
}
