<?php

namespace FlyPHP\LoadTester\Testing;

use FlyPHP\LoadTester\Config\TestProfile;
use FlyPHP\LoadTester\Testing\Results\ClientResult;
use FlyPHP\LoadTester\Testing\Timers\RuntimeLimiter;
use FlyPHP\Runtime\Loop;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tests\Fixtures\DummyOutput;

/**
 * Performs load testing.
 */
class TestRunner
{
    /**
     * The profile this runner is executing.
     *
     * @var TestProfile
     */
    private $profile;

    /**
     * Standard console output.
     *
     * @var OutputInterface
     */
    private $output;

    /**
     * Initializes a new load test runner.
     *
     * @param TestProfile $profile
     */
    public function __construct(TestProfile $profile)
    {
        $this->profile = $profile;
        $this->output = new DummyOutput();
    }

    /**
     * Returns the profile configuration for this runner.
     *
     * @return TestProfile
     */
    public function getProfile()
    {
        return $this->profile;
    }

    /**
     * Sets the logging output stream.
     *
     * @param OutputInterface $output
     */
    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;
    }

    /**
     * Performs load testing as configured in the current profile.
     */
    public function run()
    {
        // Display test info
        $this->output->writeln('');
        $this->output->writeln('Starting new load test:');
        $this->output->writeln(sprintf(' - Target: %s:%s', $this->profile->host, $this->profile->port));
        $this->output->writeln(sprintf(' - Runtime: %s seconds', $this->profile->runtime));
        $this->output->writeln(sprintf(' - Requests: from %s to %s per second', $this->profile->from, $this->profile->to));
        $this->output->writeln('');

        /**
         * @var $clients TestClient[]
         */
        $clients = [];

        // Create clients for the upper bound of our requests range
        // Each client is expected to perform one request per second
        $startDelayPerClient = $this->profile->runtime / $this->profile->to;

        for ($i = 0; $i < $this->profile->to; $i++) {
            $startDelay = $i == 0 ? 0 : round($startDelayPerClient * $i);

            $client = new TestClient($this, $i);
            $client->setStartDelay($startDelay);

            $clients[] = $client;

            $this->output->writeln('Client #' . $i . ' will be launched after ' . $startDelay . ' secs');
        }

        $this->output->writeln('A test client will be added every ' . $startDelayPerClient. ' secs');

        // Initialize the loop, register the timers, and make sure they are all ready to go
        $loop = new Loop();

        $this->output->writeln('Warming up...');

        foreach ($clients as $client) {
            $client->start($loop);
        }

        (new RuntimeLimiter($this->profile->runtime))->start($loop);

        // Warmup - prefetch DNS
        dns_get_record($this->profile->host);

        // Run the event loop
        $this->output->writeln('Starting load test.');
        $loop->run();

        // Print results
        $this->output->writeln('-------------------------------------------------------------------------------------');
        $this->output->writeln('Completed load test.');
        $this->output->writeln('-------------------------------------------------------------------------------------');
    }

    /**
     * Processes a client result.
     */
    public function onResult(TestClient $client, ClientResult $result)
    {
        $this->output->writeln($result->__toString());
    }
}