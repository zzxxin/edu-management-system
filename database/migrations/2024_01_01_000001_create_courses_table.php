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
        Schema::create('courses', function (Blueprint $table) {
            $table->id()->comment('课程ID，主键');
            $table->string('name')->comment('课程名');
            $table->string('year_month', 6)->comment('年月（格式：YYYYMM，例如：202310）');
            $table->decimal('fee', 10, 2)->comment('课程费用（单位：元）');
            $table->foreignId('teacher_id')->comment('教师ID，外键关联teachers表')->constrained('teachers')->onDelete('cascade');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');

            // 索引
            $table->index('teacher_id', 'idx_courses_teacher_id');
            $table->index('year_month', 'idx_courses_year_month');
            $table->index(['teacher_id', 'year_month'], 'idx_courses_teacher_year_month');
            $table->index('created_at', 'idx_courses_created_at');
        });

        // 添加表注释
        DB::statement("COMMENT ON TABLE courses IS '课程表，存储课程的基本信息，包括课程名、年月、费用和所属教师'");
        
        // 添加索引注释
        DB::statement("COMMENT ON INDEX idx_courses_teacher_id IS '教师ID索引，用于快速查询某教师的课程'");
        DB::statement("COMMENT ON INDEX idx_courses_year_month IS '年月索引，用于按年月筛选课程'");
        DB::statement("COMMENT ON INDEX idx_courses_teacher_year_month IS '教师ID和年月联合索引，用于查询某教师在特定年月的课程'");
        DB::statement("COMMENT ON INDEX idx_courses_created_at IS '创建时间索引，用于按时间排序'");
    }

    /**
     * 回滚迁移
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
