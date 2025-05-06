<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 合同比对结果模型
 * 
 * 存储合同智能比对的结果数据
 * 
 * @version 1.0.0
 * @author 广西港妙科技有限公司
 * @copyright 版权所有 © 广西港妙科技有限公司
 */
class ContractComparison extends Model
{
    use HasFactory;
    
    /**
     * 与模型关联的表名
     *
     * @var string
     */
    protected $table = 'contract_comparisons';
    
    /**
     * 可批量赋值的属性
     *
     * @var array
     */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'contract1_name',
        'contract2_name',
        'contract1_hash',
        'contract2_hash',
        'overall_similarity',
        'comparison_data',
        'key_differences',
        'visualization_data',
        'status',
        'comparison_options'
    ];
    
    /**
     * 应该被转换为原生类型的属性
     *
     * @var array
     */
    protected $casts = [
        'comparison_data' => 'array',
        'key_differences' => 'array',
        'visualization_data' => 'array',
        'comparison_options' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
    
    /**
     * 获取关联的租户
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
    
    /**
     * 获取关联的用户
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * 获取格式化的相似度
     *
     * @return string
     */
    public function getFormattedSimilarityAttribute(): string
    {
        return number_format($this->overall_similarity * 100, 2) . '%';
    }
    
    /**
     * 获取比对状态文本
     *
     * @return string
     */
    public function getStatusTextAttribute(): string
    {
        $statusMap = [
            'pending' => '处理中',
            'completed' => '已完成',
            'failed' => '失败'
        ];
        
        return $statusMap[$this->status] ?? $this->status;
    }
    
    /**
     * 获取关键差异数量
     *
     * @return int
     */
    public function getKeyDifferencesCountAttribute(): int
    {
        return count($this->key_differences ?? []);
    }
}