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
        Schema::table('invoices', function (Blueprint $table) {
            $table->timestamp('rejected_at')->nullable()->after('paid_at')->comment('账单拒绝时间，当状态变为rejected时记录');
            $table->index('rejected_at', 'idx_invoices_rejected_at');
        });

        DB::statement("COMMENT ON COLUMN invoices.rejected_at IS '账单拒绝时间，当状态变为rejected时记录'");
        DB::statement("COMMENT ON INDEX idx_invoices_rejected_at IS '拒绝时间索引，用于按拒绝时间排序和筛选'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('idx_invoices_rejected_at');
            $table->dropColumn('rejected_at');
        });
    }
};
