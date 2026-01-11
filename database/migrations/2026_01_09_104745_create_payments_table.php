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
        Schema::create('payments', function (Blueprint $table) {
            $table->id()->comment('支付ID，主键');
            $table->foreignId('invoice_id')->comment('账单ID，外键关联invoices表')->constrained('invoices')->onDelete('cascade');
            $table->string('omise_charge_id')->nullable()->comment('Omise支付平台的支付ID，用于查询支付详情和退款');
            $table->decimal('amount', 10, 2)->comment('支付金额（单位：元）');
            $table->string('currency', 10)->default('jpy')->comment('货币代码：thb, jpy, sgd, usd 等');
            $table->string('status', 20)->default('pending')->comment('支付状态：pending(处理中), successful(成功), failed(失败)');
            $table->string('payment_method', 50)->nullable()->comment('支付方式：card, internet_banking 等');
            $table->text('omise_response')->nullable()->comment('Omise API 完整响应（JSON格式），用于调试和审计');
            $table->text('error_message')->nullable()->comment('错误信息（如果支付失败）');
            $table->timestamp('paid_at')->nullable()->comment('支付完成时间');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');

            // 索引
            $table->index('invoice_id', 'idx_payments_invoice_id');
            $table->index('omise_charge_id', 'idx_payments_omise_charge_id');
            $table->index('status', 'idx_payments_status');
            $table->index(['invoice_id', 'status'], 'idx_payments_invoice_status');
            $table->index('paid_at', 'idx_payments_paid_at');
            $table->index('created_at', 'idx_payments_created_at');
        });

        // 添加表注释
        DB::statement("COMMENT ON TABLE payments IS '支付记录表，存储所有支付尝试记录。一个账单可以有多次支付尝试，只有成功的支付才会更新账单状态'");
        
        // 添加字段注释
        DB::statement("COMMENT ON COLUMN payments.invoice_id IS '账单ID，外键关联invoices表'");
        DB::statement("COMMENT ON COLUMN payments.omise_charge_id IS 'Omise支付平台的支付ID，用于查询支付详情和退款'");
        DB::statement("COMMENT ON COLUMN payments.amount IS '支付金额（单位：元）'");
        DB::statement("COMMENT ON COLUMN payments.currency IS '货币代码：thb, jpy, sgd, usd 等'");
        DB::statement("COMMENT ON COLUMN payments.status IS '支付状态：pending(处理中), successful(成功), failed(失败)'");
        DB::statement("COMMENT ON COLUMN payments.payment_method IS '支付方式：card, internet_banking 等'");
        DB::statement("COMMENT ON COLUMN payments.omise_response IS 'Omise API 完整响应（JSON格式），用于调试和审计'");
        DB::statement("COMMENT ON COLUMN payments.error_message IS '错误信息（如果支付失败）'");
        DB::statement("COMMENT ON COLUMN payments.paid_at IS '支付完成时间'");
        
        // 添加索引注释
        DB::statement("COMMENT ON INDEX idx_payments_invoice_id IS '账单ID索引，用于快速查询某账单的所有支付记录'");
        DB::statement("COMMENT ON INDEX idx_payments_omise_charge_id IS 'Omise支付ID索引，用于查询支付记录'");
        DB::statement("COMMENT ON INDEX idx_payments_status IS '状态索引，用于按状态筛选支付记录'");
        DB::statement("COMMENT ON INDEX idx_payments_invoice_status IS '账单ID和状态联合索引，用于查询某账单的特定状态支付记录'");
        DB::statement("COMMENT ON INDEX idx_payments_paid_at IS '支付完成时间索引，用于按时间排序'");
        DB::statement("COMMENT ON INDEX idx_payments_created_at IS '创建时间索引，用于按创建时间排序'");
    }

    /**
     * 回滚迁移
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
