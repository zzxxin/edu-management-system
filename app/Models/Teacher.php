<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * 教师模型
 * 
 * 教师可以使用后台管理系统和教务管理系统
 * 现在使用 admin_users 表，通过 user_type='teacher' 来标识
 */
class Teacher extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * 表名
     *
     * @var string
     */
    protected $table = 'admin_users';

    /**
     * 可批量赋值的属性
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'user_type',
    ];

    /**
     * 应该隐藏的属性（序列化时）
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * 属性类型转换
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        // 注意：password 不使用 'hashed' cast，因为数据库中存储的已经是哈希值
    ];

    /**
     * Boot 方法：设置全局作用域，只查询 user_type='teacher' 的记录
     */
    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope('teachers', function ($builder) {
            $builder->where('user_type', User::USER_TYPE_TEACHER);
        });
    }

    /**
     * 教师创建的课程
     *
     * @return HasMany
     */
    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    /**
     * 教师管理的学生
     *
     * @return HasMany
     */
    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    /**
     * 获取教师的所有账单（通过课程）
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough
     */
    public function invoices()
    {
        return $this->hasManyThrough(Invoice::class, Course::class);
    }

    /**
     * 获取指定状态的账单数量
     *
     * @param string $status 状态
     * @return int
     */
    public function getInvoiceCountByStatus(string $status): int
    {
        return Invoice::forTeacher($this->id)
            ->byStatus($status)
            ->count();
    }
}
