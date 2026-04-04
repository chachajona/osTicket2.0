<?php

namespace App\Providers;

use App\Services\LegacyHasher;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Hashing\HashManager;
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
            'T' => \App\Models\Ticket::class,
            // 'A' => \App\Models\Task::class, // TODO: uncomment when Task model is created
        ]);

        $this->app->make(HashManager::class)->extend('legacy', function () {
            return new LegacyHasher();
        });
    }
}
