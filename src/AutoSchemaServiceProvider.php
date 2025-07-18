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
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateTypesCommand::class,
                WatchTypesCommand::class,
                InitCommand::class,
            ]);
        }

        $this->publishes([
            __DIR__.'/../config/autoscema.php' => config_path('autoscema.php'),
        ], 'autoscema-config');
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/autoscema.php', 'autoscema');

        // Register services lazily to avoid early resolution
        $this->app->singleton(ModelAnalyzer::class);
        $this->app->singleton(ValidationAnalyzer::class);
        $this->app->singleton(SchemaBuilder::class);
        $this->app->singleton(TypeGenerator::class);
    }
}