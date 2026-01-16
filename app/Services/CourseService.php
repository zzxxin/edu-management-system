<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * 课程服务类
 *
 * 封装课程相关的业务逻辑
 */
class CourseService
{
    /**
     * 创建课程
     *
     * @param array $data 课程数据
     * @param int $teacherId 教师ID
     * @param array $studentIds 学生ID数组
     * @return Course
     * @throws \InvalidArgumentException
     */
    public function createCourse(array $data, int $teacherId, array $studentIds): Course
    {
        // 参数校验
        if (empty($data['name']) || !is_string($data['name'])) {
            throw new \InvalidArgumentException('课程名称不能为空且必须是字符串。');
        }

        if (empty($data['year_month']) || !preg_match('/^\d{6}$/', $data['year_month'])) {
            throw new \InvalidArgumentException('年月格式不正确，应为6位数字（如：202310）。');
        }

        if (!isset($data['fee']) || !is_numeric($data['fee']) || $data['fee'] < 0) {
            throw new \InvalidArgumentException('课程费用必须是大于等于0的数字。');
        }

        if ($teacherId <= 0) {
            throw new \InvalidArgumentException('教师ID必须是正整数。');
        }

        if (empty($studentIds) || !is_array($studentIds)) {
            throw new \InvalidArgumentException('学生ID数组不能为空。');
        }

        foreach ($studentIds as $id) {
            if (!is_numeric($id) || $id <= 0) {
                throw new \InvalidArgumentException('学生ID必须是正整数。');
            }
        }

        return DB::transaction(function () use ($data, $teacherId, $studentIds) {
            $course = Course::create([
                'name' => $data['name'],
                'year_month' => $data['year_month'],
                'fee' => $data['fee'],
                'teacher_id' => $teacherId,
            ]);

            // 关联学生
            $course->students()->sync($studentIds);

            return $course;
        });
    }

    /**
     * 更新课程
     *
     * @param Course $course 课程对象
     * @param array $data 课程数据
     * @param array $studentIds 学生ID数组
     * @return Course
     * @throws \InvalidArgumentException
     */
    public function updateCourse(Course $course, array $data, array $studentIds): Course
    {
        // 参数校验
        if (empty($data['name']) || !is_string($data['name'])) {
            throw new \InvalidArgumentException('课程名称不能为空且必须是字符串。');
        }

        if (empty($data['year_month']) || !preg_match('/^\d{6}$/', $data['year_month'])) {
            throw new \InvalidArgumentException('年月格式不正确，应为6位数字（如：202310）。');
        }

        if (!isset($data['fee']) || !is_numeric($data['fee']) || $data['fee'] < 0) {
            throw new \InvalidArgumentException('课程费用必须是大于等于0的数字。');
        }

        if (empty($studentIds) || !is_array($studentIds)) {
            throw new \InvalidArgumentException('学生ID数组不能为空。');
        }

        foreach ($studentIds as $id) {
            if (!is_numeric($id) || $id <= 0) {
                throw new \InvalidArgumentException('学生ID必须是正整数。');
            }
        }

        return DB::transaction(function () use ($course, $data, $studentIds) {
            $course->update([
                'name' => $data['name'],
                'year_month' => $data['year_month'],
                'fee' => $data['fee'],
            ]);

            // 更新学生关联
            $course->students()->sync($studentIds);

            return $course;
        });
    }

    /**
     * 验证学生ID是否属于指定教师
     *
     * @param array $studentIds 学生ID数组
     * @param int $teacherId 教师ID
     * @return array 有效的学生ID数组
     * @throws \InvalidArgumentException
     */
    public function validateStudentIds(array $studentIds, int $teacherId): array
    {
        // 参数校验
        if (empty($studentIds) || !is_array($studentIds)) {
            throw new \InvalidArgumentException('学生ID数组不能为空。');
        }

        if ($teacherId <= 0) {
            throw new \InvalidArgumentException('教师ID必须是正整数。');
        }

        foreach ($studentIds as $id) {
            if (!is_numeric($id) || $id <= 0) {
                throw new \InvalidArgumentException('学生ID必须是正整数。');
            }
        }

        // 过滤掉已删除的学生
        return Student::forTeacher($teacherId)
            ->whereIn('id', $studentIds)
            ->pluck('id')
            ->toArray();
    }
}
