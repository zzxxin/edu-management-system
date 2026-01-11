<?php

namespace App\Http\Controllers;

use App\Models\Teacher;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * 认证控制器
 * 
 * 支持Teacher和Student的统一登录
 */
class AuthController extends Controller
{
    /**
     * 显示登录页面
     *
     * @return \Illuminate\View\View
     */
    public function showLoginForm()
    {
        return view('auth.login');
    }

    /**
     * 处理登录请求
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $credentials = $request->only('email', 'password');

        // 尝试从teacher表登录
        $teacher = Teacher::where('email', $credentials['email'])->first();
        if ($teacher && Hash::check($credentials['password'], $teacher->password)) {
            Auth::guard('teacher')->login($teacher, $request->boolean('remember'));
            $request->session()->regenerate();
            return redirect()->intended(route('teacher.dashboard'));
        }

        // 尝试从student表登录
        $student = Student::where('email', $credentials['email'])->first();
        if ($student && Hash::check($credentials['password'], $student->password)) {
            Auth::guard('student')->login($student, $request->boolean('remember'));
            $request->session()->regenerate();
            return redirect()->intended(route('student.dashboard'));
        }

        throw ValidationException::withMessages([
            'email' => ['邮箱或密码错误。'],
        ]);
    }

    /**
     * 处理登出请求
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function logout(Request $request)
    {
        // 登出当前用户（无论是teacher还是student）
        if (Auth::guard('teacher')->check()) {
            Auth::guard('teacher')->logout();
        }
        if (Auth::guard('student')->check()) {
            Auth::guard('student')->logout();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
