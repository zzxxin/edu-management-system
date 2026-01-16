<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 运行迁移
     * 
     * 为学生表添加 username 字段，支持用户名登录
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('username', 50)->nullable()->unique()->after('name')->comment('用户名（用于登录，可选）');
            $table->index('username', 'idx_students_username');
        });

        DB::statement("COMMENT ON COLUMN students.username IS '用户名（用于登录），可选，如果设置则可以使用用户名或邮箱登录'");
        DB::statement("COMMENT ON INDEX idx_students_username IS '用户名索引，用于快速查询和登录'");
    }

    /**
     * 回滚迁移
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex('idx_students_username');
            $table->dropColumn('username');
        });
    }
};
