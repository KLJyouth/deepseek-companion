<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 法律知识图谱实体模型
 * 
 * 用于存储和管理法律条款实体数据，支持知识图谱构建
 * 
 * @property int $id 主键ID
 * @property int|null $tenant_id 租户ID
 * @property string $name 实体名称
 * @property string $type 实体类型
 * @property string|null $description 实体描述
 * @property array|null $properties 实体属性
 * @property string|null $source 数据来源
 * @property int|null $category_id 分类ID
 * @property int $status 状态
 * @property \Carbon\Carbon $created_at 创建时间
 * @property \Carbon\Carbon $updated_at 更新时间
 * 
 * @property-read \App\Models\Tenant|null $tenant 所属租户
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\LegalKnowledgeRelation[] $sourceRelations 作为源实体的关系
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\LegalKnowledgeRelation[] $targetRelations 作为目标实体的关系
 * 
 * @version 1.0.0
 * @author 广西港妙科技有限公司
 * @copyright 版权所有 © 广西港妙科技有限公司
 */
class LegalKnowledgeEntity extends Model
{
    /**
     * 与模型关联的表名
     *
     * @var string
     */
    protected $table = 'legal_knowledge_entities';

    /**
     * 可批量赋值的属性
     *
     * @var array
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'type',
        'description',
        'properties',
        'source',
        'category_id',
        'status'
    ];

    /**
     * 应该被转换为原生类型的属性
     *
     * @var array
     */
    protected $casts = [
        'properties' => 'array',
        'status' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * 实体类型常量
     */
    const TYPE_CONCEPT = 'concept';    // 概念
    const TYPE_TERM = 'term';          // 术语
    const TYPE_CLAUSE = 'clause';      // 条款
    const TYPE_REGULATION = 'regulation'; // 法规

    /**
     * 状态常量
     */
    const STATUS_ACTIVE = 1;   // 有效
    const STATUS_INACTIVE = 0;  // 无效

    /**
     * 获取所属租户
     *
     * @return BelongsTo
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * 获取作为源实体的关系
     *
     * @return HasMany
     */
    public function sourceRelations(): HasMany
    {
        return $this->hasMany(LegalKnowledgeRelation::class, 'source_entity_id');
    }

    /**
     * 获取作为目标实体的关系
     *
     * @return HasMany
     */
    public function targetRelations(): HasMany
    {
        return $this->hasMany(LegalKnowledgeRelation::class, 'target_entity_id');
    }

    /**
     * 获取所有相关实体
     *
     * @return array 相关实体集合
     */
    public function getRelatedEntities(): array
    {
        $sourceEntities = $this->sourceRelations()->with('targetEntity')->get()
            ->pluck('targetEntity');
            
        $targetEntities = $this->targetRelations()->with('sourceEntity')->get()
            ->pluck('sourceEntity');
            
        return $sourceEntities->merge($targetEntities)->unique('id')->toArray();
    }

    /**
     * 获取实体类型的中文名称
     *
     * @return string 类型中文名称
     */
    public function getTypeNameAttribute(): string
    {
        $typeMap = [
            self::TYPE_CONCEPT => '概念',
            self::TYPE_TERM => '术语',
            self::TYPE_CLAUSE => '条款',
            self::TYPE_REGULATION => '法规'
        ];
        
        return $typeMap[$this->type] ?? $this->type;
    }

    /**
     * 作用域：按类型筛选
     *
     * @param \Illuminate\Database\Eloquent\Builder $query 查询构建器
     * @param string $type 实体类型
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * 作用域：仅活跃实体
     *
     * @param \Illuminate\Database\Eloquent\Builder $query 查询构建器
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * 作用域：按名称搜索
     *
     * @param \Illuminate\Database\Eloquent\Builder $query 查询构建器
     * @param string $keyword 搜索关键词
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearch($query, string $keyword)
    {
        return $query->where('name', 'like', "%{$keyword}%")
            ->orWhere('description', 'like', "%{$keyword}%");
    }
}