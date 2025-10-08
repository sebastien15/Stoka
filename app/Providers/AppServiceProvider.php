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
        // Enable query timing for performance debugging
        \DB::listen(function ($query) {
            if ($query->time > 50) { // Log queries taking more than 50ms
                \Log::info("SLOW QUERY: {$query->time}ms | SQL: {$query->sql}", [
                    'bindings' => $query->bindings,
                    'time' => $query->time
                ]);
            }
        });
    }
}
