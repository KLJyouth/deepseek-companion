<?php

namespace app\Models;

use app\Models\BaseModel;
use app\Scopes\TenantScope;

/**
 * 合同模板模型
 * 
 * 用于存储合同模板数据，支持可视化编辑器生成的模板结构
 * 
 * @property int $id 主键ID
 * @property int $tenant_id 租户ID
 * @property string $name 模板名称
 * @property string $type 模板类型(visual:可视化, html:HTML, pdf:PDF)
 * @property int $category_id 分类ID
 * @property string $description 模板描述
 * @property string $content 模板内容(JSON格式)
 * @property string $preview 预览内容(Base64编码)
 * @property int $status 状态(1:有效, 0:无效)
 * @property string $created_at 创建时间
 * @property string $updated_at 更新时间
 */
class ContractTemplate extends BaseModel
{
    /**
     * 与模型关联的表名
     *
     * @var string
     */
    protected $table = 'contract_templates';

    /**
     * 可批量赋值的属性
     *
     * @var array
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'type',
        'category_id',
        'description',
        'content',
        'preview',
        'status'
    ];

    /**
     * 自动转换的属性
     *
     * @var array
     */
    protected $casts = [
        'tenant_id' => 'integer',
        'category_id' => 'integer',
        'status' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 模型的「启动」方法
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();
        
        // 应用租户范围
        static::addGlobalScope(new TenantScope());
    }

    /**
     * 获取关联的租户
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * 获取关联的分类
     */
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * 获取关联的合同
     */
    public function contracts()
    {
        return $this->hasMany(Contract::class, 'template_id');
    }

    /**
     * 获取活跃的模板
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    /**
     * 获取指定类型的模板
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $type 模板类型
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * 获取模板内容的结构
     *
     * @return array
     */
    public function getStructureAttribute()
    {
        $content = json_decode($this->content, true);
        return $content['structure'] ?? [];
    }

    /**
     * 获取模板内容的变量
     *
     * @return array
     */
    public function getVariablesAttribute()
    {
        $content = json_decode($this->content, true);
        return $content['variables'] ?? [];
    }

    /**
     * 检查模板是否可视化类型
     *
     * @return bool
     */
    public function isVisual()
    {
        return $this->type === 'visual';
    }
}