<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Reacts;

use Illuminate\Support\ServiceProvider;

final class ReactsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/reacts.php', 'pharaonic.reacts');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/reacts.php' => $this->app->configPath('pharaonic/reacts.php'),
            ], ['pharaonic', 'laravel-reacts', 'pharaonic-config', 'reacts-config']);

            $this->publishes([
                __DIR__.'/../database/migrations' => $this->app->databasePath('migrations'),
            ], ['pharaonic', 'laravel-reacts', 'pharaonic-migrations', 'reacts-migrations']);
        }
    }
}
