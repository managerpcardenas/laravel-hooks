# 🪝 laravel-hooks

[![PHP](https://img.shields.io/badge/PHP-8.2%2B-blue)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-11%20%7C%2012-red)](https://laravel.com)
[![License](https://img.shields.io/badge/License-GPL--3.0-green)](LICENSE)

**WordPress-style Hooks & Filters (Pub/Sub pattern) for Laravel 12.**

Fork of [`voku/php-hooks`](https://github.com/voku/php-hooks) with a complete Laravel 12 integration layer:

- ✅ `Hook` Facade — `Hook::do_action()`, `Hook::apply_filters()`
- ✅ `LaravelHooksServiceProvider` with IoC singleton
- ✅ `AbstractSubscriber` base class for domain Subscribers
- ✅ Package auto-discovery (no manual provider registration needed)
- ✅ PHP 8.2+ with strict types

---

## Why Use This?

Laravel's built-in Events are powerful, but they **cannot transform a value** through multiple listeners.
This package adds the **Filter** pattern — the most powerful concept from WordPress plugins:

```php
// Multiple subscribers transform the value in priority order
$price = Hook::apply_filters('product.price', 100.00);
// → TaxSubscriber applies 16% tax:      116.00
// → DiscountSubscriber applies 10% off: 104.40  ← final returned value
```

---

## Installation

```bash
composer require managerpcardenas/laravel-hooks
```

Auto-discovery handles the rest. The `Hook` facade and `LaravelHooksServiceProvider` are registered automatically in Laravel 11/12.

Optionally publish the config:

```bash
php artisan vendor:publish --tag=laravel-hooks-config
```

---

## Core Concepts

### Actions (fire-and-forget)

Execute code when something happens. Callbacks receive the arguments but return nothing.

```php
// Publish (in a Controller or Service)
Hook::do_action('order.created', $order);

// Subscribe
$hooks->add_action('order.created', function ($order) {
    Log::info("New order #{$order->id}");
});
```

### Filters (transform a value)

Modify a value through a chain of callbacks. Each receives the output of the previous.

```php
// Publish and receive the final value
$finalTotal = Hook::apply_filters('order.total', $rawTotal, $order);

// Subscribe
$hooks->add_filter('order.total', function (float $total, $order): float {
    return $total > 100 ? $total * 0.90 : $total; // 10% discount
}, priority: 10);
```

---

## Quick Start

### 1. Create a Subscriber

```php
// app/Hooks/Subscribers/OrderSubscriber.php
namespace App\Hooks\Subscribers;

use Poslaravel\LaravelHooks\AbstractSubscriber;
use App\Models\Order;

class OrderSubscriber extends AbstractSubscriber
{
    public function subscribe(): void
    {
        // Actions — fire-and-forget
        $this->hooks->add_action('order.created',   [$this, 'onCreated'],  10);
        $this->hooks->add_action('order.completed', [$this, 'reduce'],      5); // reduce stock FIRST
        $this->hooks->add_action('order.completed', [$this, 'notify'],     20); // notify AFTER

        // Filters — transform values
        $this->hooks->add_filter('order.total', [$this, 'applyDiscount'], 10);
    }

    public function onCreated(Order $order): void
    {
        \Log::info("[Hook] Order #{$order->id} created");
    }

    public function reduce(Order $order): void  { /* reduce inventory */ }
    public function notify(Order $order): void   { /* send email */ }

    public function applyDiscount(float $total, Order $order): float
    {
        return $total > 100 ? round($total * 0.90, 2) : $total;
    }
}
```

### 2. Register in your AppServiceProvider

```php
use Poslaravel\LaravelHooks\Engine\Hooks;
use App\Hooks\Subscribers\OrderSubscriber;

public function boot(): void
{
    $hooks = app('hooks'); // or app(Hooks::class)
    (new OrderSubscriber($hooks))->subscribe();
}
```

### 3. Use the Facade anywhere

```php
// Fire an action
Hook::do_action('order.created', $order);

// Apply filters and get the result
$total = Hook::apply_filters('order.total', $rawTotal, $order);

// Check if action exists
if (Hook::has_action('order.created')) { ... }

// Check how many times an action fired
$count = Hook::did_action('order.created');
```

---

## Priority System

Lower number = executes first. Same priority = registration order.

| Priority | Convention |
|---|---|
| 1–9 | Critical data ops (inventory, ledger, DB writes) |
| 10 | Default (logging, audit) |
| 20–50 | Notifications, side effects |
| 100+ | Post-processing, cleanup |

```php
// Guaranteed order: reduce(5) → audit(10) → notify(20)
$hooks->add_action('order.completed', [$this, 'reduce'],  5);
$hooks->add_action('order.completed', [$this, 'audit'],  10);
$hooks->add_action('order.completed', [$this, 'notify'], 20);
```

---

## API Reference

### Actions

| Method | Description |
|---|---|
| `Hook::add_action($tag, $cb, $priority)` | Register a callback on an action hook |
| `Hook::do_action($tag, ...$args)` | Fire all callbacks for an action hook |
| `Hook::remove_action($tag, $cb, $priority)` | Remove a registered action callback |
| `Hook::remove_all_actions($tag)` | Remove all callbacks from an action hook |
| `Hook::has_action($tag, $cb?)` | Check if an action hook has registered callbacks |
| `Hook::did_action($tag)` | Get how many times an action has fired |

### Filters

| Method | Description |
|---|---|
| `Hook::add_filter($tag, $cb, $priority)` | Register a callback on a filter hook |
| `Hook::apply_filters($tag, $value, ...$extra)` | Run all callbacks and return the filtered value |
| `Hook::remove_filter($tag, $cb, $priority)` | Remove a registered filter callback |
| `Hook::remove_all_filters($tag)` | Remove all callbacks from a filter hook |
| `Hook::has_filter($tag, $cb?)` | Check if a filter hook has registered callbacks |
| `Hook::current_filter()` | Get the name of the currently executing hook |

---

## vs Laravel Events

| Feature | laravel-hooks | Laravel Events |
|---|---|---|
| Actions (fire & forget) | `do_action()` | `Event::dispatch()` |
| Filters (transform value) | `apply_filters()` | Not available natively |
| Priority ordering | Yes (integer) | No |
| Queue support | No | Yes (ShouldQueue) |
| Broadcasting | No | Yes |
| Auto-discovery | Yes | Yes |

---

## Credits

- Original WordPress Plugin API — [WordPress Foundation](https://wordpress.org)
- PHP port — [Ohad Raz](https://github.com/bainternet) & [Lars Moelleken](https://github.com/voku)
- Laravel 12 integration — [managerpcardenas](https://github.com/managerpcardenas)

## License

GPL-3.0 — see [LICENSE](LICENSE)
