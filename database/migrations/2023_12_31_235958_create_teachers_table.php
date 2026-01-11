<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 运行迁移
     */
    public function up(): void
    {
        Schema::create('teachers', function (Blueprint $table) {
            $table->id()->comment('教师ID，主键');
            $table->string('name')->comment('教师姓名');
            $table->string('email')->unique()->comment('教师邮箱（用于登录）');
            $table->timestamp('email_verified_at')->nullable()->comment('邮箱验证时间');
            $table->string('password')->comment('密码（加密存储）');
            $table->rememberToken()->comment('记住我令牌');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');

            // 索引
            $table->index('email', 'idx_teachers_email');
            $table->index('created_at', 'idx_teachers_created_at');
        });

        // 添加表注释
        DB::statement("COMMENT ON TABLE teachers IS '教师表，存储教师的基本信息。教师可以使用后台管理系统和教务管理系统'");
        
        // 添加索引注释
        DB::statement("COMMENT ON INDEX idx_teachers_email IS '邮箱索引，用于快速查询和登录'");
        DB::statement("COMMENT ON INDEX idx_teachers_created_at IS '创建时间索引，用于按时间排序'");
    }

    /**
     * 回滚迁移
     */
    public function down(): void
    {
        Schema::dropIfExists('teachers');
    }
};
