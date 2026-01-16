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
        // 更新 courses 表的外键：从 teachers 改为 admin_users
        Schema::table('courses', function (Blueprint $table) {
            // 删除旧的外键约束
            $table->dropForeign(['teacher_id']);
        });

        // 重新添加外键，指向 admin_users 表
        Schema::table('courses', function (Blueprint $table) {
            $table->foreign('teacher_id')
                ->references('id')
                ->on('admin_users')
                ->onDelete('cascade');
        });

        // 更新 students 表的外键：从 teachers 改为 admin_users
        Schema::table('students', function (Blueprint $table) {
            // 删除旧的外键约束
            $table->dropForeign(['teacher_id']);
        });

        // 重新添加外键，指向 admin_users 表
        Schema::table('students', function (Blueprint $table) {
            $table->foreign('teacher_id')
                ->references('id')
                ->on('admin_users')
                ->onDelete('cascade');
        });

        // 更新索引注释
        DB::statement("COMMENT ON INDEX idx_courses_teacher_id IS '教师ID索引，外键关联 admin_users 表，用于快速查询某教师的课程'");
        DB::statement("COMMENT ON INDEX idx_students_teacher_id IS '教师ID索引，外键关联 admin_users 表，用于快速查询某教师的所有学生'");
    }

    /**
     * 回滚迁移
     * 
     * 注意：teachers 表已删除，回滚时只删除外键约束，不重新创建指向 teachers 的外键
     */
    public function down(): void
    {
        // 回滚 students 表的外键（只删除，不重新创建指向 teachers，因为 teachers 表已不存在）
        Schema::table('students', function (Blueprint $table) {
            $table->dropForeign(['teacher_id']);
        });

        // 回滚 courses 表的外键（只删除，不重新创建指向 teachers，因为 teachers 表已不存在）
        Schema::table('courses', function (Blueprint $table) {
            $table->dropForeign(['teacher_id']);
        });
    }
};
