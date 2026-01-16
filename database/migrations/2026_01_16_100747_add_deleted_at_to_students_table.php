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
     * 为学生表添加软删除支持
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->softDeletes()->after('updated_at')->comment('删除时间（软删除）');
            $table->index('deleted_at', 'idx_students_deleted_at');
        });

        DB::statement("COMMENT ON COLUMN students.deleted_at IS '删除时间（软删除），NULL表示未删除'");
        DB::statement("COMMENT ON INDEX idx_students_deleted_at IS '删除时间索引，用于软删除查询'");
    }

    /**
     * 回滚迁移
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex('idx_students_deleted_at');
            $table->dropColumn('deleted_at');
        });
    }
};
