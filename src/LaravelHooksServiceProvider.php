<?php

namespace Poslaravel\LaravelHooks;

use Poslaravel\LaravelHooks\Engine\Hooks;
use Illuminate\Support\ServiceProvider;

/**
 * Service Provider for laravel-hooks.
 *
 * Registers the Hooks engine as a singleton under the key 'hooks'.
 * This binding is consumed by the Hook Facade.
 *
 * Auto-discovery is configured in composer.json (extra.laravel),
 * so no manual registration is needed in Laravel 11/12 projects.
 *
 * To register your domain Subscribers, extend this provider
 * or add them in your own AppServiceProvider:
 *
 *   public function boot(): void
 *   {
 *       $hooks = $this->app->make('hooks');
 *       (new OrderSubscriber($hooks))->subscribe();
 *   }
 */
class LaravelHooksServiceProvider extends ServiceProvider
{
    /**
     * Register the Hooks singleton in the IoC container.
     */
    public function register(): void
    {
        $this->app->singleton('hooks', function (): Hooks {
            return Hooks::getInstance();
        });
    }

    /**
     * Publish the package config file.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/hooks.php' => config_path('hooks.php'),
            ], 'laravel-hooks-config');
        }
    }
}
