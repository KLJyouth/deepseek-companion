<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 生物特征档案表迁移
 * 
 * 创建存储用户生物特征数据的表结构
 * 
 * @version 1.0.0
 * @author 广西港妙科技有限公司
 * @copyright 版权所有 © 广西港妙科技有限公司
 */
class CreateBiometricProfilesTable extends Migration
{
    /**
     * 运行迁移
     *
     * @return void
     */
    public function up()
    {
        Schema::create('biometric_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->text('template')->comment('加密存储的特征模板');
            $table->string('algorithm', 50)->comment('使用的算法类型(FIDO2, FINGERPRINT, FACE等)');
            $table->json('device_info')->nullable()->comment('采集设备信息');
            $table->tinyInteger('status')->default(1)->comment('状态(1:有效, 0:无效)');
            $table->timestamps();
            
            // 索引
            $table->index('user_id');
            $table->index(['user_id', 'algorithm']);
            $table->index(['user_id', 'status']);
            
            // 外键约束
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * 回滚迁移
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('biometric_profiles');
    }
}