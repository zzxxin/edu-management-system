<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 在 Heroku 上自动处理 HTTPS
        // Heroku 的 *.herokuapp.com 域名会自动提供 SSL 证书
        if (config('app.env') === 'production' && !empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            \URL::forceScheme('https');
        }
    }
}
