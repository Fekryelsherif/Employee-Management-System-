<?php

namespace App\Providers;

use Illuminate\Support\Facades\Log;
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
    public function boot()
{
    if (app()->environment('production') && !\App\Models\User::exists()) {
        \Illuminate\Support\Facades\Artisan::call('migrate:fresh --seed --force');
        Log::info('✅ Database seeded automatically on first production run.');
    }
}

}
