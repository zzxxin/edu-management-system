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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id()->comment('账单ID，主键');
            $table->foreignId('course_id')->comment('课程ID，外键关联courses表')->constrained('courses')->onDelete('cascade');
            $table->foreignId('student_id')->comment('学生ID，外键关联students表')->constrained('students')->onDelete('cascade');
            $table->string('year_month', 6)->comment('年月（格式：YYYYMM，例如：202310），从课程中获取');
            $table->decimal('amount', 10, 2)->comment('账单金额（单位：元）');
            $table->string('status', 20)->default('pending')->comment('账单状态：pending(待发送), sent(已发送待支付), paid(已支付), failed(支付失败)');
            $table->timestamp('sent_at')->nullable()->comment('账单发送时间，当状态变为sent时记录');
            $table->timestamp('paid_at')->nullable()->comment('账单支付时间，当状态变为paid时记录');
            $table->string('omise_charge_id')->nullable()->comment('Omise支付平台的支付ID，用于查询支付详情和退款');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');

            // 索引
            $table->index('course_id', 'idx_invoices_course_id');
            $table->index('student_id', 'idx_invoices_student_id');
            $table->index('year_month', 'idx_invoices_year_month');
            $table->index('status', 'idx_invoices_status');
            $table->index(['student_id', 'status'], 'idx_invoices_student_status');
            $table->index(['course_id', 'status'], 'idx_invoices_course_status');
            $table->index(['year_month', 'status'], 'idx_invoices_year_month_status');
            $table->index('omise_charge_id', 'idx_invoices_omise_charge_id');
            $table->index('sent_at', 'idx_invoices_sent_at');
            $table->index('paid_at', 'idx_invoices_paid_at');
            $table->index('created_at', 'idx_invoices_created_at');
        });

        // 添加表注释
        DB::statement("COMMENT ON TABLE invoices IS '账单表，存储课程费用的账单信息，包括账单金额、状态和支付记录。一个账单对应一个课程的一个学生的费用'");
        
        // 添加索引注释
        DB::statement("COMMENT ON INDEX idx_invoices_course_id IS '课程ID索引，用于快速查询某课程的所有账单'");
        DB::statement("COMMENT ON INDEX idx_invoices_student_id IS '学生ID索引，用于快速查询某学生的所有账单'");
        DB::statement("COMMENT ON INDEX idx_invoices_year_month IS '年月索引，用于按年月筛选账单'");
        DB::statement("COMMENT ON INDEX idx_invoices_status IS '状态索引，用于按状态筛选账单（如查询待支付账单）'");
        DB::statement("COMMENT ON INDEX idx_invoices_student_status IS '学生ID和状态联合索引，用于查询某学生的特定状态账单'");
        DB::statement("COMMENT ON INDEX idx_invoices_course_status IS '课程ID和状态联合索引，用于查询某课程的特定状态账单'");
        DB::statement("COMMENT ON INDEX idx_invoices_year_month_status IS '年月和状态联合索引，用于查询某年月的特定状态账单'");
        DB::statement("COMMENT ON INDEX idx_invoices_omise_charge_id IS 'Omise支付ID索引，用于查询支付记录'");
        DB::statement("COMMENT ON INDEX idx_invoices_sent_at IS '发送时间索引，用于按时间排序和筛选'");
        DB::statement("COMMENT ON INDEX idx_invoices_paid_at IS '支付时间索引，用于按支付时间排序'");
        DB::statement("COMMENT ON INDEX idx_invoices_created_at IS '创建时间索引，用于按创建时间排序'");
    }

    /**
     * 回滚迁移
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
