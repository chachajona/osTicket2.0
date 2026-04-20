<?php

namespace App\Providers;

use App\Auth\StaffUserProvider;
use App\Models\Staff;
use App\Models\Task;
use App\Models\Ticket;
use App\Services\LegacyHasher;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Hashing\HashManager;
use Illuminate\Support\Facades\Auth;
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
        Relation::enforceMorphMap([
            'T' => Ticket::class,
            'staff' => Staff::class,
            'A' => Task::class,
        ]);

        $this->app->make(HashManager::class)->extend('legacy', function () {
            return new LegacyHasher;
        });

        Auth::provider('staff', function ($app, array $config) {
            return new StaffUserProvider(
                $app['hash'],
                $config['model'],
                $app['cache']->store(),
            );
        });
    }
}
