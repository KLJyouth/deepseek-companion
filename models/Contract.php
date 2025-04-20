<?php
namespace Models;

use Traits\InputValidation;

class Contract extends BaseModel
{
    use InputValidation;

    protected static $table = 'contracts';
    
    /** @var array 必填字段 */
    protected $required = ['title', 'content', 'parties'];
    
    /** @var array 字段验证规则 */
    protected $rules = [
        'title' => ['type' => 'string', 'max' => 255],
        'content' => ['type' => 'string', 'min' => 10],
        'parties' => ['type' => 'array'],
        'status' => ['type' => 'string', 'enum' => ['draft', 'signed', 'completed']]
    ];

    /**
     * 验证合同数据
     * @param array $data
     * @throws \InvalidArgumentException
     */
    protected function validate(array $data): void
    {
        $this->validateRequired($data);
        $this->validateRules($data);
    }

    /**
     * 获取合同签名信息
     * @return array
     */
    public function signatures(): array
    {
        return ContractSignature::findByContract($this->attributes['id']);
    }

    /**
     * 检查合同是否已过期
     * @return bool
     */
    public function isExpired()
    {
        return strtotime($this->attributes['expire_at']) < time();
    }
}
