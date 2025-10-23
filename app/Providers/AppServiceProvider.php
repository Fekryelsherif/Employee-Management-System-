<?php

namespace App\Providers;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
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
    // تأكد إننا مش بنعمل Config Cache أو Optimize أثناء Build
    // if (app()->runningInConsole()) {
    //     return;
    // }

    // // لو في production والداتا لسه فاضية
    // if (app()->environment('production') && Schema::hasTable('users') && !\App\Models\User::exists()) {
    //     Artisan::call('migrate:fresh --seed --force');
    //     Log::info('✅ Database seeded automatically on first production run.');
    // }
}

}