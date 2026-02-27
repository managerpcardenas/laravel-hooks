<?php

namespace Poslaravel\LaravelHooks\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Facade for the laravel-hooks Pub/Sub system.
 *
 * @method static bool     add_action(string $tag, callable $cb, int $priority = 50, string $path = null)
 * @method static bool     do_action(string $tag, mixed ...$args)
 * @method static bool     remove_action(string $tag, callable $cb, int $priority = 50)
 * @method static bool|int has_action(string $tag, callable $cb = false)
 * @method static int      did_action(string $tag)
 * @method static bool     remove_all_actions(string $tag, int|false $priority = false)
 * @method static bool     add_filter(string $tag, callable $cb, int $priority = 50, string $path = null)
 * @method static mixed    apply_filters(string $tag, mixed $value, mixed ...$extra)
 * @method static bool     remove_filter(string $tag, callable $cb, int $priority = 50)
 * @method static bool|int has_filter(string $tag, callable $cb = false)
 * @method static bool     remove_all_filters(string $tag, int|false $priority = false)
 * @method static string   current_filter()
 *
 * @see \Poslaravel\LaravelHooks\Engine\Hooks
 */
class Hook extends Facade
{
    /**
     * Returns the IoC container key for the Hooks engine singleton.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'hooks';
    }
}
