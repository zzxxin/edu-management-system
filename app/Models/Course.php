<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * 课程模型
 * 
 * 课程(Course): 课程名、年月、费用、学生
 */
class Course extends Model
{
    use HasFactory;

    /**
     * 可批量赋值的属性
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',           // 课程名
        'year_month',     // 年月 (格式: 202310)
        'fee',            // 费用
        'teacher_id',     // 教师ID
    ];

    /**
     * 属性类型转换
     *
     * @var array<string, string>
     */
    protected $casts = [
        'fee' => 'decimal:2',
        'year_month' => 'string',
    ];

    /**
     * 课程所属的教师（从 admin_users 表中 user_type='teacher' 的用户）
     *
     * @return BelongsTo
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * 课程的学生（多对多关系）
     * 注意：由于 Student 模型使用了 SoftDeletes，已删除的学生会自动被过滤
     *
     * @return BelongsToMany
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'course_student')
            ->withTimestamps();
    }

    /**
     * 课程的账单
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * 查询指定教师的课程
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
     * 查询有学生的课程
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithStudents($query)
    {
        return $query->has('students');
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
     * 预加载学生关联
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithStudentsRelation($query)
    {
        return $query->with('students');
    }

    /**
     * 验证课程是否属于指定教师
     *
     * @param int $teacherId 教师ID
     * @return bool
     */
    public function belongsToTeacher(int $teacherId): bool
    {
        return $this->teacher_id === $teacherId;
    }
}
