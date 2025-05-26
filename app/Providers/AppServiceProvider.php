<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\ToolRegistry::class);
        $this->app->singleton(\App\Services\HandlerDiscoveryService::class);

        // Register BashToolExecutor with dependency injection
        $this->app->bind(\App\Services\BashToolExecutor::class, function ($app, $parameters) {
            return new \App\Services\BashToolExecutor($parameters['handler']);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
