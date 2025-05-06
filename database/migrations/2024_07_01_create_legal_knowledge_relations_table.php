<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 法律知识图谱关系表迁移
 * 
 * 创建存储法律条款实体间关系的表结构，支持知识图谱构建和智能推理
 * 
 * @version 1.0.0
 * @author 广西港妙科技有限公司
 * @copyright 版权所有 © 广西港妙科技有限公司
 */
class CreateLegalKnowledgeRelationsTable extends Migration
{
    /**
     * 运行迁移
     *
     * @return void
     */
    public function up()
    {
        Schema::create('legal_knowledge_relations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID');
            $table->unsignedBigInteger('source_entity_id')->comment('源实体ID');
            $table->unsignedBigInteger('target_entity_id')->comment('目标实体ID');
            $table->string('relation_type', 50)->comment('关系类型(is_a:是一种, part_of:部分, refers_to:引用, conflicts_with:冲突, supersedes:替代)');
            $table->text('description')->nullable()->comment('关系描述');
            $table->json('properties')->nullable()->comment('关系属性(JSON格式)');
            $table->float('weight', 8, 4)->default(1.0)->comment('关系权重');
            $table->string('source', 255)->nullable()->comment('数据来源');
            $table->tinyInteger('status')->default(1)->comment('状态(1:有效, 0:无效)');
            $table->timestamps();
            
            // 索引
            $table->index('tenant_id');
            $table->index('source_entity_id');
            $table->index('target_entity_id');
            $table->index('relation_type');
            $table->index(['tenant_id', 'source_entity_id']);
            $table->index(['tenant_id', 'target_entity_id']);
            $table->index(['source_entity_id', 'target_entity_id']);
            
            // 外键约束
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('source_entity_id')->references('id')->on('legal_knowledge_entities')->onDelete('cascade');
            $table->foreign('target_entity_id')->references('id')->on('legal_knowledge_entities')->onDelete('cascade');
        });
    }

    /**
     * 回滚迁移
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('legal_knowledge_relations');
    }
}