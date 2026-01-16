<?php

namespace App\Http\Controllers;

use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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
        // 如果已经登录，先登出当前用户（防止切换账号时的session冲突）
        if (Auth::guard('teacher')->check() || Auth::guard('student')->check()) {
            $this->authService->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        // 清理和规范化输入数据（去除首尾空格）
        $login = trim($request->input('login', ''));
        $password = $request->input('password', ''); // 密码不trim，因为密码可能包含首尾空格

        // 验证输入
        $validated = $request->validate([
            'login' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9@._-]+$/', // 只允许字母、数字、@、.、_、-
            ],
            'password' => [
                'required',
                'string',
                'min:1',
                'max:255', // 防止超长密码攻击
            ],
        ], [
            'login.required' => '用户名或邮箱不能为空。',
            'login.string' => '用户名或邮箱格式不正确。',
            'login.max' => '用户名或邮箱长度不能超过255个字符。',
            'login.regex' => '用户名或邮箱包含非法字符，只允许字母、数字、@、.、_、-。',
            'password.required' => '密码不能为空。',
            'password.string' => '密码格式不正确。',
            'password.min' => '密码不能为空。',
            'password.max' => '密码长度不能超过255个字符。',
        ]);

        // 使用验证后的数据（已自动trim，除了password）
        $login = trim($validated['login']);

        // 验证清理后的登录字段不为空
        if (empty($login)) {
            throw ValidationException::withMessages([
                'login' => ['用户名或邮箱不能为空。'],
            ]);
        }

        // 安全校验：防止空字符串和只包含空格的字符串
        if (strlen($login) === 0 || ctype_space($login)) {
            throw ValidationException::withMessages([
                'login' => ['用户名或邮箱不能为空。'],
            ]);
        }

        $result = $this->authService->attemptLogin(
            $login,
            $password,
            $request->boolean('remember'),
            $request // 传递 Request 对象用于 session 处理
        );

        if (!$result['success']) {
            // 登录失败，直接返回错误，不继续执行
            throw ValidationException::withMessages([
                'login' => ['用户名/邮箱或密码错误。'],
            ]);
        }

        return redirect()->intended($result['redirect']);
    }

    /**
     * 处理登出请求
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function logout(Request $request)
    {
        try {
            // 尝试登出用户（即使用户已被删除，也不会报错）
            $this->authService->logout();
        } catch (\Exception $e) {
            // 如果登出过程中出现异常（例如用户已被删除），忽略异常继续执行
            // 记录日志但不影响退出流程
            Log::warning('Logout exception (user may be deleted): ' . $e->getMessage());
        }

        try {
            // 使 session 失效
            if ($request->hasSession()) {
                $request->session()->invalidate();
            }
        } catch (\Exception $e) {
            // 如果session失效过程中出现异常，忽略继续执行
            Log::warning('Session invalidate exception: ' . $e->getMessage());
        }

        try {
            // 重新生成 CSRF token（如果session还存在）
            if ($request->hasSession()) {
                $request->session()->regenerateToken();
            }
        } catch (\Exception $e) {
            // 如果token重新生成过程中出现异常，忽略继续执行
            Log::warning('Token regenerate exception: ' . $e->getMessage());
        }

        // 无论是否成功，都重定向到登录页面
        return redirect()->route('login')->with('success', '已成功退出登录。');
    }
}
