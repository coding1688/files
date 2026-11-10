<?php

namespace Coding1688\Files;

use Coding1688\Files\Console\Commands\DisksCommand;
use Coding1688\Files\Console\Commands\FailoverCommand;
use Coding1688\Files\Console\Commands\SmokeCommand;
use Coding1688\Files\Console\Commands\SyncCommand;
use Illuminate\Support\ServiceProvider;

class FilesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeFilesConfig();

        $this->app->scoped(StorageRegistry::class);
        $this->app->scoped(ImageLinks::class);
    }

    public function boot(): void
    {
        $migrations = __DIR__.'/../database/migrations';

        if (is_dir($migrations)) {
            $this->loadMigrationsFrom($migrations);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([DisksCommand::class, SyncCommand::class, FailoverCommand::class, SmokeCommand::class]);

            $this->publishes([
                __DIR__.'/../config/files.php' => config_path('files.php'),
            ], 'files-config');

            $this->publishes([
                $migrations => database_path('migrations'),
            ], 'files-migrations');
        }
    }

    /**
     * Package defaults are merged under the host configuration. Lists replace,
     * maps merge, so hosts can override a single upload limit without copying
     * the whole defaults array.
     */
    private function mergeFilesConfig(): void
    {
        $defaults = require __DIR__.'/../config/files.php';
        $current = (array) config('files', []);

        config(['files' => $this->merge($defaults, $current)]);
    }

    /** @param array<string, mixed> $defaults @param array<string, mixed> $current */
    private function merge(array $defaults, array $current): array
    {
        foreach ($current as $key => $value) {
            if (is_array($value) && isset($defaults[$key]) && is_array($defaults[$key]) && ! array_is_list($value)) {
                $defaults[$key] = $this->merge($defaults[$key], $value);
            } else {
                $defaults[$key] = $value;
            }
        }

        return $defaults;
    }
}
