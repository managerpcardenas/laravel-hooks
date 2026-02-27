<?php

namespace Poslaravel\LaravelHooks\Tests;

use Poslaravel\LaravelHooks\Engine\Hooks;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Hooks engine.
 */
class HooksTest extends TestCase
{
    private Hooks $hooks;

    protected function setUp(): void
    {
        $this->hooks = Hooks::getInstance();

        // Reset the singleton state between tests via reflection
        $ref = new \ReflectionClass($this->hooks);
        foreach (['filters', 'merged_filters', 'actions', 'current_filter'] as $prop) {
            $p = $ref->getProperty($prop);
            $p->setAccessible(true);
            $p->setValue($this->hooks, []);
        }
    }

    /** @test */
    public function it_fires_a_registered_action(): void
    {
        $fired = false;
        $this->hooks->add_action('test.action', function () use (&$fired) {
            $fired = true;
        });
        $this->hooks->do_action('test.action');
        $this->assertTrue($fired);
    }

    /** @test */
    public function it_applies_a_filter_and_returns_modified_value(): void
    {
        $this->hooks->add_filter('test.filter', fn(int $v): int => $v * 2);
        $result = $this->hooks->apply_filters('test.filter', 5);
        $this->assertSame(10, $result);
    }

    /** @test */
    public function it_executes_callbacks_in_priority_order(): void
    {
        $order = [];
        $this->hooks->add_action('test.priority', function () use (&$order) { $order[] = 'second'; }, 20);
        $this->hooks->add_action('test.priority', function () use (&$order) { $order[] = 'first';  }, 5);
        $this->hooks->add_action('test.priority', function () use (&$order) { $order[] = 'third';  }, 50);
        $this->hooks->do_action('test.priority');
        $this->assertSame(['first', 'second', 'third'], $order);
    }

    /** @test */
    public function it_chains_multiple_filters_on_same_tag(): void
    {
        $this->hooks->add_filter('price', fn(float $v): float => $v * 1.16, 10); // +16% tax
        $this->hooks->add_filter('price', fn(float $v): float => $v * 0.90, 20); // -10% discount
        $result = $this->hooks->apply_filters('price', 100.0);
        $this->assertEqualsWithDelta(104.40, $result, 0.01);
    }

    /** @test */
    public function it_counts_action_fires(): void
    {
        $this->hooks->add_action('test.count', fn() => null);
        $this->hooks->do_action('test.count');
        $this->hooks->do_action('test.count');
        $this->hooks->do_action('test.count');
        $this->assertSame(3, $this->hooks->did_action('test.count'));
    }

    /** @test */
    public function it_returns_false_when_no_action_registered(): void
    {
        $this->assertFalse($this->hooks->do_action('nonexistent.action'));
    }

    /** @test */
    public function it_removes_a_specific_action(): void
    {
        $fired = false;
        $cb = function () use (&$fired) { $fired = true; };
        $this->hooks->add_action('test.remove', $cb);
        $this->hooks->remove_action('test.remove', $cb);
        $this->hooks->do_action('test.remove');
        $this->assertFalse($fired);
    }

    /** @test */
    public function it_passes_extra_args_to_filter(): void
    {
        $this->hooks->add_filter('order.total', function (float $total, string $type): float {
            return $type === 'vip' ? $total * 0.80 : $total;
        }, 10);
        $result = $this->hooks->apply_filters('order.total', 200.0, 'vip');
        $this->assertEqualsWithDelta(160.0, $result, 0.01);
    }
}
