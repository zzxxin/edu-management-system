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
     * 为 admin_users 表添加 user_type 字段，用于区分教师和管理员
     */
    public function up(): void
    {
        // 如果 admin_users 表不存在，创建它
        if (!Schema::hasTable('admin_users')) {
            Schema::create('admin_users', function (Blueprint $table) {
                $table->id()->comment('管理员用户ID，主键');
                $table->string('name')->comment('用户姓名');
                $table->string('email')->unique()->comment('用户邮箱（用于登录）');
                $table->string('user_type', 20)->default('teacher')->comment('用户类型：teacher=教师，admin=管理员');
                $table->timestamp('email_verified_at')->nullable()->comment('邮箱验证时间');
                $table->string('password')->comment('密码（加密存储）');
                $table->rememberToken()->comment('记住我令牌');
                $table->timestamp('created_at')->nullable()->comment('创建时间');
                $table->timestamp('updated_at')->nullable()->comment('更新时间');

                // 索引
                $table->index('email', 'idx_admin_users_email');
                $table->index('user_type', 'idx_admin_users_user_type');
                $table->index('created_at', 'idx_admin_users_created_at');
            });

            // 添加表注释
            DB::statement("COMMENT ON TABLE admin_users IS '管理员用户表，存储教师和管理员信息，通过 user_type 字段区分用户类型'");
            DB::statement("COMMENT ON INDEX idx_admin_users_email IS '邮箱索引，用于快速查询和登录'");
            DB::statement("COMMENT ON INDEX idx_admin_users_user_type IS '用户类型索引，用于快速查询特定类型的用户'");
            DB::statement("COMMENT ON INDEX idx_admin_users_created_at IS '创建时间索引，用于按时间排序'");
        } else {
            // 如果表已存在，只添加 user_type 字段（如果不存在）
            if (!Schema::hasColumn('admin_users', 'user_type')) {
                Schema::table('admin_users', function (Blueprint $table) {
                    $table->string('user_type', 20)->default('teacher')->after('email')->comment('用户类型：teacher=教师，admin=管理员');
                    $table->index('user_type', 'idx_admin_users_user_type');
                });

                DB::statement("COMMENT ON COLUMN admin_users.user_type IS '用户类型：teacher=教师，admin=管理员'");
                DB::statement("COMMENT ON INDEX idx_admin_users_user_type IS '用户类型索引，用于快速查询特定类型的用户'");
            }
        }
    }

    /**
     * 回滚迁移
     */
    public function down(): void
    {
        // 只删除 user_type 字段，不删除表
        if (Schema::hasColumn('admin_users', 'user_type')) {
            Schema::table('admin_users', function (Blueprint $table) {
                $table->dropIndex('idx_admin_users_user_type');
                $table->dropColumn('user_type');
            });
        }
    }
};
