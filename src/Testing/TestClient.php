<?php

namespace FlyPHP\LoadTester\Testing;

use FlyPHP\Runtime\Loop;
use FlyPHP\Runtime\Timer;

/**
 * A test client that performs HTTP requests.
 */
class TestClient extends Timer
{
    /**
     * The test client ID, relative to the runner host.
     *
     * @var int
     */
    private $id;

    /**
     * The interval, in exact seconds, until the client begins dropping requests after starting.
     *
     * @var int
     */
    private $startDelay;

    /**
     * The current delay until the next request is issued.
     * This effectively controls how many ticks are ignored until a new request is sent.
     *
     * @var int
     */
    private $delay;

    /**
     * Initializes a new TestRunner.
     *
     * @param TestRunner $runner
     * @param int $id
     */
    public function __construct(TestRunner $runner, int $id)
    {
        parent::__construct(1.0, true, $this);

        $this->runner = $runner;
        $this->id = $id;
        $this->startDelay = 0;
        $this->delay = 0;
    }

    /**
     * Sets the interval, in exact seconds, until the client begins dropping requests after starting.
     *
     * @param int $seconds
     * @return $this
     */
    public function setStartDelay(int $seconds)
    {
        $this->startDelay = $seconds;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function start(Loop $loop)
    {
        $this->delay = $this->startDelay;

        parent::start($loop);

        // Reset start time so we are triggered instantly when the loop starts
        $this->startTime -= 1;
    }

    /**
     * Triggers every second.
     */
    public function __invoke()
    {
        // Tick down the delay
        if ($this->delay > 0) {
            $this->delay--;
            return;
        }

        // Time to issue an request
        echo $this->id . 'boom' . PHP_EOL;
    }
}