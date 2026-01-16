<?php

namespace App\Services;

use App\Models\User;
use App\Models\Student;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
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
     * 教师支持用户名或邮箱登录，学生仅支持邮箱登录
     * 已删除的用户（SoftDeletes）无法登录
     *
     * @param string $login 登录凭证（用户名或邮箱）
     * @param string $password 密码
     * @param bool $remember 是否记住登录
     * @param \Illuminate\Http\Request|null $request 请求对象（用于session处理，可选）
     * @return array{success: bool, guard: string|null, user: User|Student|null, redirect: string|null}
     */
    public function attemptLogin(string $login, string $password, bool $remember = false, $request = null): array
    {
        // 安全校验：清理输入（登录字段去除首尾空格，密码保留原样）
        $login = trim($login);
        // 注意：密码不trim，因为密码可能有意包含首尾空格

        // 验证输入不为空
        if (empty($login) || empty($password)) {
            return [
                'success' => false,
                'guard' => null,
                'user' => null,
                'redirect' => null,
            ];
        }

        // 安全校验：防止只包含空格的登录字段
        if (ctype_space($login) || strlen($login) === 0) {
            return [
                'success' => false,
                'guard' => null,
                'user' => null,
                'redirect' => null,
            ];
        }

        // 安全校验：防止超长输入攻击（Laravel的查询构建器已经处理SQL注入，但额外验证长度）
        if (strlen($login) > 255 || strlen($password) > 255) {
            return [
                'success' => false,
                'guard' => null,
                'user' => null,
                'redirect' => null,
            ];
        }

        // 尝试从 admin_users 表登录（查询 user_type='teacher' 的用户）
        // 支持用户名或邮箱登录
        $teacher = User::teachers()->where(function ($query) use ($login) {
            $query->where('email', $login)
                  ->orWhere('username', $login);
        })->first();

        if ($teacher) {
            // 用户存在，验证密码
            // 由于已移除 'hashed' cast，$teacher->password 直接返回数据库中的哈希值
            if (Hash::check($password, $teacher->password)) {
                // 登录用户（这会自动处理 session）
                Auth::guard('teacher')->login($teacher, $remember);
                
                // 登录成功后立即重新生成 session ID 和 CSRF token，防止会话固定攻击
                // 必须在登录成功后立即调用，确保后续请求使用新的 token
                if ($request) {
                    $request->session()->regenerate();
                    $request->session()->regenerateToken();
                } elseif (request()->hasSession()) {
                    request()->session()->regenerate();
                    request()->session()->regenerateToken();
                }
                
                return [
                    'success' => true,
                    'guard' => 'teacher',
                    'user' => $teacher,
                    'redirect' => route('teacher.dashboard'),
                ];
            }
            // 密码错误，直接返回失败
            return [
                'success' => false,
                'guard' => null,
                'user' => null,
                'redirect' => null,
            ];
        }

        // 尝试从 student 表登录（仅支持邮箱登录）
        // SoftDeletes 会自动过滤已删除的学生
        $student = Student::where('email', $login)->first();
        if ($student) {
            // 学生存在，验证密码
            // 由于已移除 'hashed' cast，$student->password 直接返回数据库中的哈希值
            if (Hash::check($password, $student->password)) {
                // 登录用户（这会自动处理 session）
                Auth::guard('student')->login($student, $remember);
                
                // 登录成功后立即重新生成 session ID 和 CSRF token，防止会话固定攻击
                // 必须在登录成功后立即调用，确保后续请求使用新的 token
                if ($request) {
                    $request->session()->regenerate();
                    $request->session()->regenerateToken();
                } elseif (request()->hasSession()) {
                    request()->session()->regenerate();
                    request()->session()->regenerateToken();
                }
                
                return [
                    'success' => true,
                    'guard' => 'student',
                    'user' => $student,
                    'redirect' => route('student.dashboard'),
                ];
            }
            // 密码错误，直接返回失败
            return [
                'success' => false,
                'guard' => null,
                'user' => null,
                'redirect' => null,
            ];
        }

        // 用户不存在，直接返回失败
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
     * 兼容处理：即使用户已被删除，也能正常退出登录
     *
     * @return void
     */
    public function logout(): void
    {
        try {
            // 登出当前用户（无论是 teacher 还是 student）
            // 即使用户已被删除，check() 可能会返回 false，但不会报错
            if (Auth::guard('teacher')->check()) {
                try {
                    Auth::guard('teacher')->logout();
                } catch (\Exception $e) {
                    // 如果登出过程中出现异常（例如用户已被删除），忽略异常
                    Log::warning('Teacher logout exception (user may be deleted): ' . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            // 如果check()过程中出现异常，忽略继续执行
            Log::warning('Teacher check exception: ' . $e->getMessage());
        }

        try {
            if (Auth::guard('student')->check()) {
                try {
                    Auth::guard('student')->logout();
                } catch (\Exception $e) {
                    // 如果登出过程中出现异常（例如用户已被删除），忽略异常
                    Log::warning('Student logout exception (user may be deleted): ' . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            // 如果check()过程中出现异常，忽略继续执行
            Log::warning('Student check exception: ' . $e->getMessage());
        }
    }

    /**
     * 根据登录凭证查找用户（教师或学生）
     *
     * 教师支持用户名或邮箱，学生仅支持邮箱
     * 已删除的用户（SoftDeletes）不会被返回
     *
     * @param string $login 登录凭证（用户名或邮箱）
     * @return User|Student|null
     */
    public function findUserByLogin(string $login)
    {
        // 尝试查找教师（支持用户名或邮箱）
        $teacher = User::teachers()->where(function ($query) use ($login) {
            $query->where('email', $login)
                  ->orWhere('username', $login);
        })->first();

        if ($teacher) {
            return $teacher;
        }

        // 尝试查找学生（仅支持邮箱，SoftDeletes 会自动过滤已删除的学生）
        return Student::where('email', $login)->first();
    }

    /**
     * 根据邮箱查找用户（教师或学生）
     *
     * @deprecated 使用 findUserByLogin 代替
     * @param string $email 邮箱
     * @return User|Student|null
     */
    public function findUserByEmail(string $email)
    {
        return $this->findUserByLogin($email);
    }
}
