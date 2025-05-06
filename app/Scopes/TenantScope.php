<?php

namespace app\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * 租户数据范围
 * 
 * 用于多租户SaaS架构中的数据隔离，确保查询时自动添加租户条件
 * 应用此Scope的模型将自动按当前租户进行数据过滤
 * 
 * @version 1.0.0
 * @author 广西港妙科技有限公司
 * @copyright 版权所有 © 广西港妙科技有限公司
 */
class TenantScope implements Scope
{
    /**
     * 应用租户范围到查询
     *
     * @param \Illuminate\Database\Eloquent\Builder $builder 查询构建器
     * @param \Illuminate\Database\Eloquent\Model $model 模型实例
     * @return void
     */
    public function apply(Builder $builder, Model $model)
    {
        // 获取当前租户
        $tenant = app('tenant');
        
        // 如果存在租户上下文，添加租户条件
        if ($tenant) {
            $builder->where($model->getTable() . '.tenant_id', $tenant->id);
        }
    }
    
    /**
     * 扩展查询构建器，添加withoutTenant方法
     *
     * @param \Illuminate\Database\Eloquent\Builder $builder 查询构建器
     * @return void
     */
    public function extend(Builder $builder)
    {
        // 添加withoutTenant方法，用于临时禁用租户过滤
        $builder->macro('withoutTenant', function (Builder $builder) {
            return $builder->withoutGlobalScope($this);
        });
        
        // 添加forTenant方法，用于指定租户
        $builder->macro('forTenant', function (Builder $builder, $tenantId) {
            return $builder->withoutGlobalScope($this)
                ->where($builder->getModel()->getTable() . '.tenant_id', $tenantId);
        });
        
        // 添加allTenants方法，用于查询所有租户数据
        $builder->macro('allTenants', function (Builder $builder) {
            return $builder->withoutGlobalScope($this);
        });
    }
}