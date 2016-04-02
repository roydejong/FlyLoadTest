<?php

namespace FlyPHP\LoadTester\Testing\Timers;

use FlyPHP\Runtime\Timer;

/**
 * A timer that stops The Loop after a specified amount of time.
 */
class RuntimeLimiter extends Timer
{
    /**
     * Initializes a new RuntimeLimiter for a given $interval.
     *
     * @param float $interval
     */
    public function __construct(float $interval)
    {
        parent::__construct($interval, false, $this);
    }

    /**
     * Timer callback.
     * Stops The Loop.
     */
    public function __invoke()
    {
        $this->loop->stop();
    }
}