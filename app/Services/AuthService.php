<?php

namespace App\Services;

use App\Models\Teacher;
use App\Models\Student;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * 认证服务类
 *
 * 封装认证相关的业务逻辑
 */
class AuthService
{
    /**
     * 尝试登录用户（教师或学生）
     *
     * @param string $email 邮箱
     * @param string $password 密码
     * @param bool $remember 是否记住登录
     * @return array{success: bool, guard: string|null, user: Teacher|Student|null, redirect: string|null}
     */
    public function attemptLogin(string $email, string $password, bool $remember = false): array
    {
        // 尝试从 teacher 表登录
        $teacher = Teacher::where('email', $email)->first();
        if ($teacher && Hash::check($password, $teacher->password)) {
            Auth::guard('teacher')->login($teacher, $remember);
            return [
                'success' => true,
                'guard' => 'teacher',
                'user' => $teacher,
                'redirect' => route('teacher.dashboard'),
            ];
        }

        // 尝试从 student 表登录
        $student = Student::where('email', $email)->first();
        if ($student && Hash::check($password, $student->password)) {
            Auth::guard('student')->login($student, $remember);
            return [
                'success' => true,
                'guard' => 'student',
                'user' => $student,
                'redirect' => route('student.dashboard'),
            ];
        }

        return [
            'success' => false,
            'guard' => null,
            'user' => null,
            'redirect' => null,
        ];
    }

    /**
     * 登出当前用户
     *
     * @return void
     */
    public function logout(): void
    {
        // 登出当前用户（无论是 teacher 还是 student）
        if (Auth::guard('teacher')->check()) {
            Auth::guard('teacher')->logout();
        }
        if (Auth::guard('student')->check()) {
            Auth::guard('student')->logout();
        }
    }

    /**
     * 根据邮箱查找用户（教师或学生）
     *
     * @param string $email 邮箱
     * @return Teacher|Student|null
     */
    public function findUserByEmail(string $email)
    {
        $teacher = Teacher::where('email', $email)->first();
        if ($teacher) {
            return $teacher;
        }

        return Student::where('email', $email)->first();
    }
}
