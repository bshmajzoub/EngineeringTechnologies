<?php

namespace App\Providers;

use App\Models\TaskAssignment;
use App\Observers\TaskAssignmentObserver;
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
        TaskAssignment::observe(TaskAssignmentObserver::class);
    }
}
