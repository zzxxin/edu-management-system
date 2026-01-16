<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * 管理员用户模型
 * 
 * 用于存储教师和管理员信息，通过 user_type 字段区分用户类型
 * user_type: 'teacher' = 教师, 'admin' = 管理员
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * 表名
     *
     * @var string
     */
    protected $table = 'admin_users';

    /**
     * 用户类型：教师
     */
    const USER_TYPE_TEACHER = 'teacher';

    /**
     * 用户类型：管理员
     */
    const USER_TYPE_ADMIN = 'admin';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'user_type',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        // 注意：password 不使用 'hashed' cast，因为数据库中存储的已经是哈希值
        // 如果使用 'hashed' cast，会在读取时再次哈希，导致验证失败
    ];

    /**
     * 检查是否为教师
     *
     * @return bool
     */
    public function isTeacher(): bool
    {
        return $this->user_type === self::USER_TYPE_TEACHER;
    }

    /**
     * 检查是否为管理员
     *
     * @return bool
     */
    public function isAdmin(): bool
    {
        return $this->user_type === self::USER_TYPE_ADMIN;
    }

    /**
     * 查询教师类型的用户
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeTeachers($query)
    {
        return $query->where('user_type', self::USER_TYPE_TEACHER);
    }

    /**
     * 查询管理员类型的用户
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAdmins($query)
    {
        return $query->where('user_type', self::USER_TYPE_ADMIN);
    }

    /**
     * 教师创建的课程
     *
     * @return HasMany
     */
    public function courses(): HasMany
    {
        return $this->hasMany(Course::class, 'teacher_id');
    }

    /**
     * 教师管理的学生
     *
     * @return HasMany
     */
    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'teacher_id');
    }

    /**
     * 获取教师的所有账单（通过课程）
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough
     */
    public function invoices()
    {
        return $this->hasManyThrough(Invoice::class, Course::class, 'teacher_id');
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
