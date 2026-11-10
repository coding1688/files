<?php

namespace Coding1688\Files;

use Illuminate\Support\ServiceProvider;

class FilesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/files.php', 'files');
    }

    public function boot(): void
    {
        $migrations = __DIR__.'/../database/migrations';

        if (is_dir($migrations)) {
            $this->loadMigrationsFrom($migrations);
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/files.php' => config_path('files.php'),
            ], 'files-config');

            $this->publishes([
                $migrations => database_path('migrations'),
            ], 'files-migrations');
        }
    }
}
