<?php

namespace App\Providers;

use App\Policies\ModulePolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        foreach (config('home_modules', []) as $module) {
            Gate::policy($module['model'], ModulePolicy::class);
        }
        RateLimiter::for('ai', fn (Request $request) => Limit::perMinute((int) config('ai.rate_limit', 30))->by((string) $request->user()?->id));
    }
}
