<?php

namespace app\Models;

use app\Models\BaseModel;

/**
 * 租户模型
 * 
 * 用于多租户SaaS架构中的租户管理，支持租户数据库隔离和配置隔离
 * 
 * @property int $id 主键ID
 * @property string $name 租户名称
 * @property string $domain 租户域名
 * @property string $subdomain 租户子域名
 * @property string $db_name 租户数据库名称
 * @property string $db_host 租户数据库主机
 * @property string $db_port 租户数据库端口
 * @property string $db_username 租户数据库用户名
 * @property string $db_password 租户数据库密码
 * @property string $storage_path 租户存储路径
 * @property array $config 租户配置
 * @property int $status 状态(1:有效, 0:无效)
 * @property string $created_at 创建时间
 * @property string $updated_at 更新时间
 */
class Tenant extends BaseModel
{
    /**
     * 与模型关联的表名
     *
     * @var string
     */
    protected $table = 'tenants';

    /**
     * 可批量赋值的属性
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'domain',
        'subdomain',
        'db_name',
        'db_host',
        'db_port',
        'db_username',
        'db_password',
        'storage_path',
        'config',
        'status'
    ];

    /**
     * 自动转换的属性
     *
     * @var array
     */
    protected $casts = [
        'config' => 'array',
        'status' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 隐藏的属性
     *
     * @var array
     */
    protected $hidden = [
        'db_password'
    ];

    /**
     * 获取活跃的租户
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    /**
     * 获取租户的用户
     */
    public function users()
    {
        return $this->hasMany(UserModel::class, 'tenant_id');
    }

    /**
     * 获取租户的合同模板
     */
    public function contractTemplates()
    {
        return $this->hasMany(ContractTemplate::class, 'tenant_id');
    }

    /**
     * 获取租户的合同
     */
    public function contracts()
    {
        return $this->hasMany(Contract::class, 'tenant_id');
    }

    /**
     * 获取租户的数据库连接配置
     *
     * @return array
     */
    public function getDatabaseConfig()
    {
        return [
            'driver' => config('database.connections.mysql.driver'),
            'host' => $this->db_host ?? config('database.connections.mysql.host'),
            'port' => $this->db_port ?? config('database.connections.mysql.port'),
            'database' => $this->db_name,
            'username' => $this->db_username ?? config('database.connections.mysql.username'),
            'password' => $this->db_password ?? config('database.connections.mysql.password'),
            'charset' => config('database.connections.mysql.charset'),
            'collation' => config('database.connections.mysql.collation'),
            'prefix' => config('database.connections.mysql.prefix'),
        ];
    }

    /**
     * 获取租户的存储配置
     *
     * @return array
     */
    public function getStorageConfig()
    {
        return [
            'driver' => 'local',
            'root' => storage_path('tenants/' . $this->id),
        ];
    }

    /**
     * 检查租户是否有效
     *
     * @return bool
     */
    public function isActive()
    {
        return $this->status === 1;
    }
}