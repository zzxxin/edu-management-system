<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 检查字段是否已存在
        if (!Schema::hasColumn('invoices', 'omise_charge_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->string('omise_charge_id')->nullable()->after('paid_at')->comment('Omise支付平台的支付ID，用于查询支付详情和退款');
            });
            
            // 添加索引
            Schema::table('invoices', function (Blueprint $table) {
                $table->index('omise_charge_id', 'idx_invoices_omise_charge_id');
            });

            // 添加字段注释
            DB::statement("COMMENT ON COLUMN invoices.omise_charge_id IS 'Omise支付平台的支付ID，用于查询支付详情和退款'");
            
            // 添加索引注释
            DB::statement("COMMENT ON INDEX idx_invoices_omise_charge_id IS 'Omise支付ID索引，用于查询支付记录'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('invoices', 'omise_charge_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropIndex('idx_invoices_omise_charge_id');
                $table->dropColumn('omise_charge_id');
            });
        }
    }
};
