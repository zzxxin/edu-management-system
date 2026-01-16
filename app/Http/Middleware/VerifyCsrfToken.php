<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;
use Illuminate\Support\Facades\Auth;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        //
    ];

    /**
     * 验证CSRF token
     *
     * 兼容处理：对于logout请求，即使CSRF验证失败（例如用户已被删除），也允许通过
     * 这样删除用户后退出登录不会报419错误
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, \Closure $next)
    {
        // 如果是logout请求，尝试正常验证CSRF token
        // 如果验证失败（例如用户已被删除导致session异常），允许通过
        if ($request->routeIs('logout')) {
            try {
                return parent::handle($request, $next);
            } catch (\Exception $e) {
                // 如果CSRF验证失败（可能是用户已被删除导致session异常），允许logout请求通过
                // 这样删除用户后退出登录不会报419错误
                return $next($request);
            }
        }

        return parent::handle($request, $next);
    }
}
