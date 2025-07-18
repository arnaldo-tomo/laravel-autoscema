<?php

namespace ArnaldoTomo\LaravelAutoSchema;

use ArnaldoTomo\LaravelAutoSchema\Commands\GenerateTypesCommand;
use ArnaldoTomo\LaravelAutoSchema\Commands\WatchTypesCommand;
use ArnaldoTomo\LaravelAutoSchema\Commands\InitCommand;
use Illuminate\Support\ServiceProvider;

class AutoSchemaServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register commands only if running in console
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateTypesCommand::class,
                WatchTypesCommand::class,
                InitCommand::class,
            ]);
        }

        // Publish configuration
        $this->publishes([
            __DIR__.'/../config/autoscema.php' => config_path('autoscema.php'),
        ], 'autoscema-config');

        // Publish stubs
        $this->publishes([
            __DIR__.'/../stubs' => resource_path('stubs/autoscema'),
        ], 'autoscema-stubs');
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Merge configuration
        $this->mergeConfigFrom(__DIR__.'/../config/autoscema.php', 'autoscema');

        // Don't register classes in register() method
        // They will be auto-resolved when needed
    }
}