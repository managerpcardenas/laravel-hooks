<?php

declare(strict_types=1);

namespace Poslaravel\LaravelHooks\Engine;

/**
 * PHP Hooks Engine
 *
 * Fork of the WordPress filters hook system, ported to standalone PHP.
 * Core engine powering the Actions & Filters pub-sub system.
 *
 * Original authors:
 *   - Ohad Raz <admin@bainternet.info>
 *   - Lars Moelleken <lars@moelleken.org>
 *
 * Laravel 12 integration:
 *   - managerpcardenas <https://github.com/managerpcardenas>
 *
 * @license GPL-3.0
 */
class Hooks
{
    /** @var array Holds all registered filter/action hooks */
    protected array $filters = [];

    /** @var array Tracks which hook tags have been sorted */
    protected array $merged_filters = [];

    /** @var array Counts how many times each action has fired */
    protected array $actions = [];

    /** @var array Stack of currently executing filters */
    protected array $current_filter = [];

    /** Default priority when none is specified */
    public const PRIORITY_NEUTRAL = 50;

    protected function __construct() {}
    protected function __clone() {}
    public function __wakeup(): void {}

    /**
     * Returns the singleton instance of the Hooks engine.
     */
    public static function getInstance(): static
    {
        static $instance;
        if (null === $instance) {
            $instance = new static();
        }
        return $instance;
    }

    // =========================================================================
    // FILTERS
    // =========================================================================

    /**
     * Register a callback on a filter hook.
     *
     * @param string         $tag          The filter hook name.
     * @param callable|array $callback     The callback to register.
     * @param int            $priority     Execution order (lower = earlier). Default: 50.
     * @param string|null    $include_path Optional file to include before executing.
     */
    public function add_filter(
        string $tag,
        $callback,
        int $priority = self::PRIORITY_NEUTRAL,
        ?string $include_path = null
    ): bool {
        $idx = $this->buildUniqueId($callback);

        $this->filters[$tag][$priority][$idx] = [
            'function'     => $callback,
            'include_path' => $include_path,
        ];

        unset($this->merged_filters[$tag]);
        return true;
    }

    /**
     * Remove a previously registered filter callback.
     */
    public function remove_filter(
        string $tag,
        $callback,
        int $priority = self::PRIORITY_NEUTRAL
    ): bool {
        $idx = $this->buildUniqueId($callback);

        if (!isset($this->filters[$tag][$priority][$idx])) {
            return false;
        }

        unset($this->filters[$tag][$priority][$idx]);

        if (empty($this->filters[$tag][$priority])) {
            unset($this->filters[$tag][$priority]);
        }

        unset($this->merged_filters[$tag]);
        return true;
    }

    /**
     * Remove all callbacks from a filter hook.
     *
     * @param string    $tag      The filter hook name.
     * @param int|false $priority If provided, only removes at this priority.
     */
    public function remove_all_filters(string $tag, $priority = false): bool
    {
        unset($this->merged_filters[$tag]);

        if (!isset($this->filters[$tag])) {
            return true;
        }

        if (false !== $priority && isset($this->filters[$tag][$priority])) {
            unset($this->filters[$tag][$priority]);
        } else {
            unset($this->filters[$tag]);
        }

        return true;
    }

    /**
     * Check whether any callback is registered for a filter hook.
     *
     * @return bool|int True/false if no callback given; priority int if callback found.
     */
    public function has_filter(string $tag, $callback = false): bool|int
    {
        $has = isset($this->filters[$tag]);

        if (false === $callback || !$has) {
            return $has;
        }

        $idx = $this->buildUniqueId($callback);
        if (!$idx) {
            return false;
        }

        foreach (array_keys($this->filters[$tag]) as $priority) {
            if (isset($this->filters[$tag][$priority][$idx])) {
                return $priority;
            }
        }

        return false;
    }

    /**
     * Execute all callbacks on a filter hook and return the final transformed value.
     * Each callback receives the output of the previous one.
     *
     * @param string $tag    The filter hook name.
     * @param mixed  $value  The initial value to be filtered.
     * @param mixed  ...$extra Additional arguments passed to each callback.
     */
    public function apply_filters(string $tag, $value, mixed ...$extra): mixed
    {
        if (isset($this->filters['all'])) {
            $this->current_filter[] = $tag;
            $this->callAllHook([$tag, $value, ...$extra]);
        }

        if (!isset($this->filters[$tag])) {
            if (isset($this->filters['all'])) {
                array_pop($this->current_filter);
            }
            return $value;
        }

        if (!isset($this->filters['all'])) {
            $this->current_filter[] = $tag;
        }

        if (!isset($this->merged_filters[$tag])) {
            ksort($this->filters[$tag]);
            $this->merged_filters[$tag] = true;
        }

        reset($this->filters[$tag]);
        $args = [$value, ...$extra];

        do {
            foreach ((array) current($this->filters[$tag]) as $hook) {
                if (null !== $hook['function']) {
                    if (null !== $hook['include_path']) {
                        include_once $hook['include_path'];
                    }
                    $args[0] = $value;
                    $value   = call_user_func_array($hook['function'], $args);
                }
            }
        } while (next($this->filters[$tag]) !== false);

        array_pop($this->current_filter);
        return $value;
    }

    // =========================================================================
    // ACTIONS
    // =========================================================================

    /**
     * Register a callback on an action hook (fire-and-forget, no return value).
     */
    public function add_action(
        string $tag,
        $callback,
        int $priority = self::PRIORITY_NEUTRAL,
        ?string $include_path = null
    ): bool {
        return $this->add_filter($tag, $callback, $priority, $include_path);
    }

    /**
     * Check whether any callback is registered for an action hook.
     */
    public function has_action(string $tag, $callback = false): bool|int
    {
        return $this->has_filter($tag, $callback);
    }

    /**
     * Remove a previously registered action callback.
     */
    public function remove_action(
        string $tag,
        $callback,
        int $priority = self::PRIORITY_NEUTRAL
    ): bool {
        return $this->remove_filter($tag, $callback, $priority);
    }

    /**
     * Remove all callbacks from an action hook.
     */
    public function remove_all_actions(string $tag, $priority = false): bool
    {
        return $this->remove_all_filters($tag, $priority);
    }

    /**
     * Fire all callbacks registered on an action hook in priority order.
     *
     * @param string $tag     The action hook name.
     * @param mixed  ...$args Arguments passed to all registered callbacks.
     * @return bool False if no callbacks registered, true if any executed.
     */
    public function do_action(string $tag, mixed ...$args): bool
    {
        $this->actions[$tag] = ($this->actions[$tag] ?? 0) + 1;

        if (isset($this->filters['all'])) {
            $this->current_filter[] = $tag;
            $this->callAllHook([$tag, ...$args]);
        }

        if (!isset($this->filters[$tag])) {
            if (isset($this->filters['all'])) {
                array_pop($this->current_filter);
            }
            return false;
        }

        if (!isset($this->filters['all'])) {
            $this->current_filter[] = $tag;
        }

        if (!isset($this->merged_filters[$tag])) {
            ksort($this->filters[$tag]);
            $this->merged_filters[$tag] = true;
        }

        reset($this->filters[$tag]);

        do {
            foreach ((array) current($this->filters[$tag]) as $hook) {
                if (null !== $hook['function']) {
                    if (null !== $hook['include_path']) {
                        include_once $hook['include_path'];
                    }
                    call_user_func_array($hook['function'], $args);
                }
            }
        } while (next($this->filters[$tag]) !== false);

        array_pop($this->current_filter);
        return true;
    }

    /**
     * Get the number of times a specific action has been fired.
     */
    public function did_action(string $tag): int
    {
        return $this->actions[$tag] ?? 0;
    }

    /**
     * Get the name of the currently executing filter/action hook.
     */
    public function current_filter(): string
    {
        return (string) end($this->current_filter);
    }

    // =========================================================================
    // INTERNALS
    // =========================================================================

    /**
     * Build a unique string key for a callback (used as array index).
     */
    private function buildUniqueId($callback): string|false
    {
        if (is_string($callback)) {
            return $callback;
        }

        if (is_object($callback)) {
            $callback = [$callback, ''];
        } else {
            $callback = (array) $callback;
        }

        if (is_object($callback[0])) {
            return spl_object_hash($callback[0]) . $callback[1];
        }

        if (is_string($callback[0])) {
            return $callback[0] . '::' . $callback[1];
        }

        return false;
    }

    /**
     * Execute all callbacks registered under the special 'all' hook.
     * The 'all' hook fires for every action/filter dispatched.
     */
    protected function callAllHook(array $args): void
    {
        reset($this->filters['all']);

        do {
            foreach ((array) current($this->filters['all']) as $hook) {
                if (null !== $hook['function']) {
                    if (null !== $hook['include_path']) {
                        include_once $hook['include_path'];
                    }
                    call_user_func_array($hook['function'], $args);
                }
            }
        } while (next($this->filters['all']) !== false);
    }
}
