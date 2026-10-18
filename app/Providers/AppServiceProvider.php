<?php

namespace App\Providers;

use App\Tools\ToolRegistry;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ToolRegistry::class, fn ($app) => new ToolRegistry(
            array_map(fn (string $tool) => $app->make($tool), config('llm.tools'))
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
