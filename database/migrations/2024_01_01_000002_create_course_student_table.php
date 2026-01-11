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
        Schema::create('course_student', function (Blueprint $table) {
            $table->id()->comment('关联表ID，主键');
            $table->foreignId('course_id')->comment('课程ID，外键关联courses表')->constrained('courses')->onDelete('cascade');
            $table->foreignId('student_id')->comment('学生ID，外键关联students表')->constrained('students')->onDelete('cascade');
            $table->timestamp('created_at')->nullable()->comment('关联创建时间，即学生加入课程的时间');
            $table->timestamp('updated_at')->nullable()->comment('关联更新时间');

            // 唯一索引：一个学生不能重复加入同一课程
            $table->unique(['course_id', 'student_id'], 'uk_course_student_course_student');
            
            // 索引
            $table->index('course_id', 'idx_course_student_course_id');
            $table->index('student_id', 'idx_course_student_student_id');
        });

        // 添加表注释
        DB::statement("COMMENT ON TABLE course_student IS '课程学生关联表，存储课程与学生之间的多对多关系'");
        
        // 添加索引注释
        DB::statement("COMMENT ON INDEX uk_course_student_course_student IS '课程ID和学生ID唯一索引，确保一个学生不能重复加入同一课程'");
        DB::statement("COMMENT ON INDEX idx_course_student_course_id IS '课程ID索引，用于快速查询某课程的所有学生'");
        DB::statement("COMMENT ON INDEX idx_course_student_student_id IS '学生ID索引，用于快速查询某学生的所有课程'");
    }

    /**
     * 回滚迁移
     */
    public function down(): void
    {
        Schema::dropIfExists('course_student');
    }
};
