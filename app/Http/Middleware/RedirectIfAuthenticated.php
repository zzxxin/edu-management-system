<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * 如果用户已经登录，根据不同的guard跳转到对应的dashboard
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = empty($guards) ? ['teacher', 'student'] : $guards;

        // 检查教师是否已登录
        if (in_array('teacher', $guards) && Auth::guard('teacher')->check()) {
            return redirect()->route('teacher.dashboard');
        }

        // 检查学生是否已登录
        if (in_array('student', $guards) && Auth::guard('student')->check()) {
            return redirect()->route('student.dashboard');
        }

        return $next($request);
    }
}
