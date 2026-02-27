<?php

namespace LaravelHooks;

use LaravelHooks\Engine\Hooks;

/**
 * Base class for domain-specific Hook Subscribers.
 *
 * A Subscriber groups all action and filter registrations
 * for a specific domain (Orders, Products, Inventory, etc.).
 *
 * Example:
 *
 *   class OrderSubscriber extends AbstractSubscriber
 *   {
 *       public function subscribe(): void
 *       {
 *           $this->hooks->add_action('order.created', [$this, 'onOrderCreated']);
 *           $this->hooks->add_filter('order.total',   [$this, 'applyDiscount'], 10, 2);
 *       }
 *
 *       public function onOrderCreated($order): void { ... }
 *       public function applyDiscount(float $total, $order): float { ... }
 *   }
 */
abstract class AbstractSubscriber
{
    /**
     * @param Hooks $hooks The singleton Hooks engine (injected by HooksServiceProvider).
     */
    public function __construct(
        protected readonly Hooks $hooks
    ) {}

    /**
     * Register all actions and filters for this subscriber's domain.
     */
    abstract public function subscribe(): void;
}
