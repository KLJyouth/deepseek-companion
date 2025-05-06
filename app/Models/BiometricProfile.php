<?php

namespace app\Models;

use app\Models\BaseModel;

/**
 * 生物特征档案模型
 * 
 * 用于存储用户生物特征模板数据，支持多种生物识别方式
 * 
 * @property int $id 主键ID
 * @property int $user_id 用户ID
 * @property string $template 加密存储的特征模板
 * @property string $algorithm 使用的算法类型(FIDO2, FINGERPRINT, FACE等)
 * @property string $device_info 采集设备信息
 * @property int $status 状态(1:有效, 0:无效)
 * @property string $created_at 创建时间
 * @property string $updated_at 更新时间
 */
class BiometricProfile extends BaseModel
{
    /**
     * 与模型关联的表名
     *
     * @var string
     */
    protected $table = 'biometric_profiles';

    /**
     * 可批量赋值的属性
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'template',
        'algorithm',
        'device_info',
        'status'
    ];

    /**
     * 自动转换的属性
     *
     * @var array
     */
    protected $casts = [
        'user_id' => 'integer',
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
        'template'
    ];

    /**
     * 获取关联的用户
     */
    public function user()
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }

    /**
     * 获取活跃的生物特征记录
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    /**
     * 检查用户是否已注册生物特征
     *
     * @param int $userId 用户ID
     * @param string|null $algorithm 算法类型
     * @return bool
     */
    public static function isRegistered($userId, $algorithm = null)
    {
        $query = self::where('user_id', $userId)->active();
        
        if ($algorithm) {
            $query->where('algorithm', $algorithm);
        }
        
        return $query->exists();
    }
}