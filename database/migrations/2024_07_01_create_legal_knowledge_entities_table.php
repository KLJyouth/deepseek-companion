<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 法律知识图谱实体表迁移
 * 
 * 创建存储法律条款实体数据的表结构，支持知识图谱构建
 * 
 * @version 1.0.0
 * @author 广西港妙科技有限公司
 * @copyright 版权所有 © 广西港妙科技有限公司
 */
class CreateLegalKnowledgeEntitiesTable extends Migration
{
    /**
     * 运行迁移
     *
     * @return void
     */
    public function up()
    {
        Schema::create('legal_knowledge_entities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID');
            $table->string('name', 100)->comment('实体名称');
            $table->string('type', 50)->comment('实体类型(concept:概念, term:术语, clause:条款, regulation:法规)');
            $table->text('description')->nullable()->comment('实体描述');
            $table->json('properties')->nullable()->comment('实体属性(JSON格式)');
            $table->string('source', 255)->nullable()->comment('数据来源');
            $table->unsignedBigInteger('category_id')->nullable()->comment('分类ID');
            $table->tinyInteger('status')->default(1)->comment('状态(1:有效, 0:无效)');
            $table->timestamps();
            
            // 索引
            $table->index('tenant_id');
            $table->index('type');
            $table->index('category_id');
            $table->index(['tenant_id', 'type']);
            $table->index(['tenant_id', 'status']);
            
            // 全文索引
            $table->fullText(['name', 'description']);
            
            // 外键约束
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }

    /**
     * 回滚迁移
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('legal_knowledge_entities');
    }
}