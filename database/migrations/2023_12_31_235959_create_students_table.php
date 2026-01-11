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
        Schema::create('students', function (Blueprint $table) {
            $table->id()->comment('学生ID，主键');
            $table->string('name')->comment('学生姓名');
            $table->string('email')->unique()->comment('学生邮箱（用于登录）');
            $table->timestamp('email_verified_at')->nullable()->comment('邮箱验证时间');
            $table->string('password')->comment('密码（加密存储）');
            $table->foreignId('teacher_id')->comment('所属教师ID，外键关联teachers表')->constrained('teachers')->onDelete('cascade');
            $table->rememberToken()->comment('记住我令牌');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');

            // 索引
            $table->index('email', 'idx_students_email');
            $table->index('teacher_id', 'idx_students_teacher_id');
            $table->index('created_at', 'idx_students_created_at');
        });

        // 添加表注释
        DB::statement("COMMENT ON TABLE students IS '学生表，存储学生的基本信息。学生可以使用教务管理系统。一个教师可以管理多个学生（一对多关系）'");
        
        // 添加索引注释
        DB::statement("COMMENT ON INDEX idx_students_email IS '邮箱索引，用于快速查询和登录'");
        DB::statement("COMMENT ON INDEX idx_students_teacher_id IS '教师ID索引，用于快速查询某教师的所有学生'");
        DB::statement("COMMENT ON INDEX idx_students_created_at IS '创建时间索引，用于按时间排序'");
    }

    /**
     * 回滚迁移
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
