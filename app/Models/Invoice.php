<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 账单模型
 * 
 * 账单(Invoice): 针对指定年月的课程费用发起请款, 由学生支付课程费用
 * 账单可设定各种状态, 一个账单仅对应于一个课程的费用
 */
class Invoice extends Model
{
    use HasFactory;

    /**
     * 账单状态常量
     */
    const STATUS_PENDING = 'pending';      // 待发送
    const STATUS_SENT = 'sent';            // 已发送（待支付）
    const STATUS_PAID = 'paid';            // 已支付
    const STATUS_FAILED = 'failed';        // 支付失败
    const STATUS_REJECTED = 'rejected';    // 已拒绝（学生拒绝支付）

    /**
     * 可批量赋值的属性
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'course_id',      // 课程ID
        'student_id',     // 学生ID
        'year_month',     // 年月（格式：202310），从课程中获取
        'amount',         // 金额
        'status',         // 状态: pending, sent, paid, failed, rejected
        'sent_at',        // 发送时间
        'paid_at',        // 支付时间
        'rejected_at',    // 拒绝时间
        'omise_charge_id', // Omise支付ID
    ];

    /**
     * 属性类型转换
     *
     * @var array<string, string>
     */
    protected $casts = [
        'year_month' => 'string',
        'amount' => 'decimal:2',
        'sent_at' => 'datetime',
        'paid_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    /**
     * 账单所属的课程
     *
     * @return BelongsTo
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * 账单所属的学生
     * 
     * 注意：使用 withTrashed() 确保即使学生被删除，账单仍然可以显示学生信息
     *
     * @return BelongsTo
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    /**
     * 账单的支付记录（一个账单可以有多次支付尝试）
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * 账单的成功支付记录
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function successfulPayments()
    {
        return $this->hasMany(Payment::class)->where('status', Payment::STATUS_SUCCESSFUL);
    }

    /**
     * 是否为待发送状态
     *
     * @return bool
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * 是否为已发送状态（待支付）
     *
     * @return bool
     */
    public function isSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    /**
     * 是否为已支付状态
     *
     * @return bool
     */
    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /**
     * 是否为已拒绝状态
     *
     * @return bool
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * 查询指定教师的所有账单
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $teacherId 教师ID
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForTeacher($query, int $teacherId)
    {
        return $query->whereHas('course', function ($q) use ($teacherId) {
            $q->where('teacher_id', $teacherId);
        });
    }

    /**
     * 查询指定学生的账单
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $studentId 学生ID
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForStudent($query, int $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    /**
     * 查询指定状态的账单
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $status 状态
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * 查询多个状态的账单
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $statuses 状态数组
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByStatuses($query, array $statuses)
    {
        return $query->whereIn('status', $statuses);
    }

    /**
     * 排除待发送状态的账单
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeExcludePending($query)
    {
        return $query->where('status', '!=', self::STATUS_PENDING);
    }

    /**
     * 查询指定课程和学生的账单
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $courseId 课程ID
     * @param int $studentId 学生ID
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForCourseAndStudent($query, int $courseId, int $studentId)
    {
        return $query->where('course_id', $courseId)
            ->where('student_id', $studentId);
    }

    /**
     * 按创建时间倒序排列
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * 预加载关联数据（用于列表）
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithRelations($query)
    {
        return $query->with(['course', 'student']);
    }

    /**
     * 预加载关联数据（用于详情）
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithDetailRelations($query)
    {
        return $query->with([
            'course.teacher',
            'student',
            'payments' => function ($q) {
                $q->orderBy('created_at', 'desc');
            }
        ]);
    }
}
