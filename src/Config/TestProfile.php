<?php

namespace FlyPHP\LoadTester\Config;

/**
 * A load test profile.
 */
class TestProfile
{
    /**
     * The IP address or hostname.
     *
     * @see $port
     * @var string
     */
    public $host;

    /**
     * The port to test on.
     *
     * @see $hostname
     * @var int
     */
    public $port;

    /**
     * The load test runtime in seconds.
     * During the runtime, the requests per second are ramped.
     *
     * @see $from, $to
     * @var int
     */
    public $runtime = 60;

    /**
     * The amount of requests to start from.
     *
     * @var int
     */
    public $from = 1;

    /**
     * The amount of requests per second to ramp up to.
     *
     * @var int
     */
    public $to = 100;

    /**
     * A list of behaviors to apply randomly to test clients.
     *
     * @var string[]
     */
    public $behaviors = [];

    /**
     * Parses raw user config data into a TestProfile object.
     *
     * @param mixed $yaml
     * @return TestProfile
     */
    public static function parse($yaml)
    {
        $profile = new TestProfile();
        $profile->host = $yaml['host'];
        $profile->port = $yaml['port'];
        $profile->runtime = $yaml['runtime'];
        $profile->from = $yaml['from'];
        $profile->to = $yaml['to'];
        $profile->behaviors = [];
        return $profile;
    }
}