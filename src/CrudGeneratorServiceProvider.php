<?php

namespace Shakib\CrudGenerator;

use Illuminate\Support\ServiceProvider;
use Shakib\CrudGenerator\Commands\MakeCrudCommand;

class CrudGeneratorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeCrudCommand::class,
            ]);

            // Publish stubs so user can customize
            $this->publishes([
                __DIR__ . '/stubs' => base_path('stubs/crud-generator'),
            ], 'crud-stubs');
        }
    }
}
