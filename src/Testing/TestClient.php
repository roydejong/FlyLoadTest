<?php

namespace FlyPHP\LoadTester\Testing;

use FlyPHP\Http\Request;
use FlyPHP\LoadTester\Config\TestProfile;
use FlyPHP\LoadTester\FlyLoadTester;
use FlyPHP\LoadTester\Testing\Results\ClientResult;
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
     * The config profile.
     *
     * @var TestProfile
     */
    private $profile;

    /**
     * The connection socket.
     *
     * @var resource
     */
    private $socket;

    /**
     * Tracks whether a connection is alive.
     *
     * @var bool
     */
    private $open;

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
        $this->profile = $runner->getProfile();
        $this->open = false;
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

        $result = new ClientResult();
        $result->clientId = $this->id;

        // We are GO - (re)open the connection
        if ($this->checkConnection()) {
            $result->connected = true;

            // Fire off a request to the connection
            $req = $this->createRequest();
            $buf = $req->serialize();
            $writeResult = fwrite($this->socket, $buf);

            if ($writeResult) {
                $result->sent = true;
                $result->resultCode = null;
            } else {
                $this->socket = null;
                $this->open = false;

                if ($this->checkConnection()) {
                    // We were able to reconnect, so re-attempt
                    return $this->__invoke();
                } else {
                    // Reconnect failed, treat as a dead server
                    $result->connected = false;
                }
            }
        }

        // Process results
        $this->runner->onResult($this, $result);
    }

    /**
     * Creates a HTTP request.
     */
    public function createRequest()
    {
        $request = new Request();

        $request->httpVersion = 'HTTP/1.1';
        $request->path = '/' . uniqid();
        $request->method = 'GET';

        $request->setHeader('Host', $this->profile->host);
        $request->setHeader('Connection', 'keep-alive');
        $request->setHeader('User-Agent', sprintf('FlyLoadTester/', FlyLoadTester::getVersionString()));
        $request->setHeader('X-Load-Test-Unique', uniqid());
        $request->setHeader('X-Load-Test-Client', $this->id);

        return $request;
    }

    /**
     * Checks the connection, (re)establishing it as needed.
     */
    public function checkConnection()
    {
        if ($this->socket == null) {
            $remoteAddress = "{$this->profile->host}:{$this->profile->port}";
            $this->socket = stream_socket_client($remoteAddress, $errno, $errmsg);

            if (!$this->socket || $errno > 0) {
                throw new TestException("Could not create socket: $errno - $errmsg");
            }

            $this->open = true;
        }

        return $this->open;
    }
}