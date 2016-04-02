<?php

namespace FlyPHP\LoadTester\Testing;

use FlyPHP\Http\HttpMessage;
use FlyPHP\Http\Request;
use FlyPHP\Http\StatusCode;
use FlyPHP\IO\ReadBuffer;
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
     * The size of the read buffer in bytes.
     * Represents how much is read when incoming data is received.
     */
    static $READ_BUFFER_SIZE = 1024;

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
     * The result being sent.
     *
     * @var ClientResult
     */
    private $result;

    /**
     * @var ReadBuffer
     */
    protected $readBuffer;

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
        $this->readBuffer = new ReadBuffer();
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

        // Do we already have a result that was pending send? Time it out and return it.
        if ($this->result != null) {
            $this->result->timedOut = true;
            $this->runner->onResult($this, $this->result);
            $this->killConnection();
        }

        // Begin constructing a new result
        $this->result = new ClientResult();
        $this->result->clientId = $this->id;
        $this->result->startTime = microtime(true);

        if ($this->checkConnection()) {
            $this->result->connected = true;

            $req = $this->createRequest();
            $buf = $req->serialize();
            $writeResult = fwrite($this->socket, $buf);

            if ($writeResult) {
                $this->result->sent = true;
                $this->result->resultCode = null;
            } else {
                $this->killConnection();

                if ($this->checkConnection()) {
                    // We were able to reconnect, so re-attempt
                    return $this->__invoke();
                } else {
                    // Reconnect failed, treat as a dead server
                    $this->result->connected = false;
                }
            }
        }
    }

    /**
     * Makes the connection dead and resets our state.
     */
    private function killConnection()
    {
        if ($this->socket != null) {
            $this->loop->removeStream($this->socket);
            @stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
            @socket_close($this->socket);
            $this->socket = null;
        }

        $this->open = false;
        $this->result = null;

        $this->readBuffer->clear();
    }

    /**
     * Reads data from the connection.
     *
     * @return string|null
     */
    private function readIntoBuffer()
    {
        $data = stream_socket_recvfrom($this->socket, self::$READ_BUFFER_SIZE);

        if ($this->result == null) {
            throw new TestException('Received data without expecting a result');
        }


        if ($data === '' || $data === false || !is_resource($this->socket) || feof($this->socket)) {
            // ... nope ...
        } else {
            $this->readBuffer->feed($data);

            $bufContents = $this->readBuffer->contents();

            $eolPos = strpos($bufContents, HttpMessage::HTTP_EOL);

            if ($eolPos === false) {
                // We don't have a a full status line yet, wait for it!
                return;
            }

            $statusLine = substr($bufContents, 0, $eolPos);
            $statusParts = explode(' ', $statusLine, 3);

            if (count($statusParts) != 3) {
                // This doesn't look like a valid HTTP response, let it time out
                return;
            }

            $statusCode = intval($statusParts[1]);

            if (!StatusCode::isValid($statusCode)) {
                // This doesn't look like a valid HTTP response, let it time out
                return;
            }

            $this->result->timedOut = false;
            $this->result->resultCode = $statusCode;
            $this->result->endTime = microtime(true);

            $this->runner->onResult($this, $this->result);

            $this->result = null;
        }

        $this->killConnection();
    }

    /**
     * @return ReadBuffer
     */
    public function getReadBuffer()
    {
        return $this->readBuffer;
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
        $request->setHeader('Connection', 'close');
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

            stream_set_blocking($this->socket, false);

            $this->loop->awaitReadable($this->socket, function () {
                $this->readIntoBuffer();
            });

            $this->open = true;
        }

        return $this->open;
    }
}