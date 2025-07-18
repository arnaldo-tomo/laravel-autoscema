<?php

namespace ArnaldoTomo\LaravelAutoSchema;

use ArnaldoTomo\LaravelAutoSchema\Commands\GenerateTypesCommand;
use ArnaldoTomo\LaravelAutoSchema\Commands\WatchTypesCommand;
use ArnaldoTomo\LaravelAutoSchema\Commands\InitCommand;
use Illuminate\Support\ServiceProvider;

class AutoSchemaServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->bootCommands();
        $this->bootPublishing();
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/autoscema.php', 'autoscema');

        $this->app->singleton(TypeGenerator::class);
        $this->app->singleton(ModelAnalyzer::class);
        $this->app->singleton(ValidationAnalyzer::class);
        $this->app->singleton(SchemaBuilder::class);
    }

    private function bootCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateTypesCommand::class,
                WatchTypesCommand::class,
                InitCommand::class,
            ]);
        }
    }

    private function bootPublishing(): void
    {
        $this->publishes([
            __DIR__.'/../config/autoscema.php' => config_path('autoscema.php'),
        ], 'autoscema-config');

        $this->publishes([
            __DIR__.'/../stubs' => resource_path('stubs/autoscema'),
        ], 'autoscema-stubs');
    }
}
