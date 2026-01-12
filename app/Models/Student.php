<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 学生模型
 * 
 * 学生可以使用教务管理系统
 * 一个教师可以管理多个学生（一对多关系）
 */
class Student extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * 表名
     *
     * @var string
     */
    protected $table = 'students';

    /**
     * 可批量赋值的属性
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'teacher_id',     // 所属教师ID
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
        'password' => 'hashed',
    ];

    /**
     * 学生所属的教师（一对多关系）
     *
     * @return BelongsTo
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    /**
     * 学生的课程（多对多关系）
     *
     * @return BelongsToMany
     */
    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_student')
            ->withTimestamps();
    }

    /**
     * 学生的账单
     *
     * @return HasMany
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * 查询指定教师的学生
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $teacherId 教师ID
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForTeacher($query, int $teacherId)
    {
        return $query->where('teacher_id', $teacherId);
    }

    /**
     * 按姓名排序
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOrderByName($query)
    {
        return $query->orderBy('name');
    }

    /**
     * 获取指定状态的账单数量
     *
     * @param string $status 状态
     * @return int
     */
    public function getInvoiceCountByStatus(string $status): int
    {
        return $this->invoices()
            ->where('status', $status)
            ->count();
    }

    /**
     * 验证账单是否属于该学生
     *
     * @param Invoice $invoice 账单对象
     * @return bool
     */
    public function ownsInvoice(Invoice $invoice): bool
    {
        return $invoice->student_id === $this->id;
    }
}
