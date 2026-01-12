<?php

namespace App\Http\Controllers;

use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * 认证控制器
 * 
 * 支持Teacher和Student的统一登录
 */
class AuthController extends Controller
{
    /**
     * @var AuthService
     */
    protected $authService;

    /**
     * 构造函数
     *
     * @param AuthService $authService
     */
    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

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
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:1',
        ], [
            'email.required' => '邮箱不能为空。',
            'email.email' => '邮箱格式不正确。',
            'email.max' => '邮箱长度不能超过255个字符。',
            'password.required' => '密码不能为空。',
            'password.string' => '密码必须是字符串。',
            'password.min' => '密码不能为空。',
        ]);

        $result = $this->authService->attemptLogin(
            $request->input('email'),
            $request->input('password'),
            $request->boolean('remember')
        );

        if ($result['success']) {
            $request->session()->regenerate();
            return redirect()->intended($result['redirect']);
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
        $this->authService->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
