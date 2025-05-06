<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 法律知识图谱关系模型
 * 
 * 用于存储和管理法律条款实体间关系的数据，支持知识图谱构建和智能推理
 * 
 * @property int $id 主键ID
 * @property int|null $tenant_id 租户ID
 * @property int $source_entity_id 源实体ID
 * @property int $target_entity_id 目标实体ID
 * @property string $relation_type 关系类型
 * @property string|null $description 关系描述
 * @property array|null $properties 关系属性
 * @property float $weight 关系权重
 * @property string|null $source 数据来源
 * @property int $status 状态
 * @property \Carbon\Carbon $created_at 创建时间
 * @property \Carbon\Carbon $updated_at 更新时间
 * 
 * @property-read \App\Models\Tenant|null $tenant 所属租户
 * @property-read \App\Models\LegalKnowledgeEntity $sourceEntity 源实体
 * @property-read \App\Models\LegalKnowledgeEntity $targetEntity 目标实体
 * 
 * @version 1.0.0
 * @author 广西港妙科技有限公司
 * @copyright 版权所有 © 广西港妙科技有限公司
 */
class LegalKnowledgeRelation extends Model
{
    /**
     * 与模型关联的表名
     *
     * @var string
     */
    protected $table = 'legal_knowledge_relations';

    /**
     * 可批量赋值的属性
     *
     * @var array
     */
    protected $fillable = [
        'tenant_id',
        'source_entity_id',
        'target_entity_id',
        'relation_type',
        'description',
        'properties',
        'weight',
        'source',
        'status'
    ];

    /**
     * 应该被转换为原生类型的属性
     *
     * @var array
     */
    protected $casts = [
        'properties' => 'array',
        'weight' => 'float',
        'status' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * 关系类型常量
     */
    const TYPE_IS_A = 'is_a';                // 是一种
    const TYPE_PART_OF = 'part_of';          // 部分
    const TYPE_REFERS_TO = 'refers_to';      // 引用
    const TYPE_CONFLICTS_WITH = 'conflicts_with'; // 冲突
    const TYPE_SUPERSEDES = 'supersedes';    // 替代

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
     * 获取源实体
     *
     * @return BelongsTo
     */
    public function sourceEntity(): BelongsTo
    {
        return $this->belongsTo(LegalKnowledgeEntity::class, 'source_entity_id');
    }

    /**
     * 获取目标实体
     *
     * @return BelongsTo
     */
    public function targetEntity(): BelongsTo
    {
        return $this->belongsTo(LegalKnowledgeEntity::class, 'target_entity_id');
    }

    /**
     * 获取关系类型的中文名称
     *
     * @return string 类型中文名称
     */
    public function getTypeNameAttribute(): string
    {
        $typeMap = [
            self::TYPE_IS_A => '是一种',
            self::TYPE_PART_OF => '部分',
            self::TYPE_REFERS_TO => '引用',
            self::TYPE_CONFLICTS_WITH => '冲突',
            self::TYPE_SUPERSEDES => '替代'
        ];
        
        return $typeMap[$this->relation_type] ?? $this->relation_type;
    }

    /**
     * 作用域：按关系类型筛选
     *
     * @param \Illuminate\Database\Eloquent\Builder $query 查询构建器
     * @param string $type 关系类型
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('relation_type', $type);
    }

    /**
     * 作用域：仅活跃关系
     *
     * @param \Illuminate\Database\Eloquent\Builder $query 查询构建器
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * 作用域：按实体ID筛选关系
     *
     * @param \Illuminate\Database\Eloquent\Builder $query 查询构建器
     * @param int $entityId 实体ID
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRelatedToEntity($query, int $entityId)
    {
        return $query->where('source_entity_id', $entityId)
            ->orWhere('target_entity_id', $entityId);
    }

    /**
     * 作用域：按权重排序
     *
     * @param \Illuminate\Database\Eloquent\Builder $query 查询构建器
     * @param string $direction 排序方向 (asc|desc)
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOrderByWeight($query, string $direction = 'desc')
    {
        return $query->orderBy('weight', $direction);
    }

    /**
     * 获取反向关系类型
     *
     * @param string $relationType 关系类型
     * @return string|null 反向关系类型
     */
    public static function getInverseRelationType(string $relationType): ?string
    {
        $inverseMap = [
            self::TYPE_IS_A => null, // 是一种关系没有明确的反向关系
            self::TYPE_PART_OF => 'contains', // 部分的反向是包含
            self::TYPE_REFERS_TO => 'referenced_by', // 引用的反向是被引用
            self::TYPE_CONFLICTS_WITH => self::TYPE_CONFLICTS_WITH, // 冲突关系是对称的
            self::TYPE_SUPERSEDES => 'superseded_by' // 替代的反向是被替代
        ];
        
        return $inverseMap[$relationType] ?? null;
    }
}