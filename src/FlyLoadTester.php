<?php

namespace FlyPHP\LoadTester;

use FlyPHP\LoadTester\Commands\StartCommand;
use Symfony\Component\Console\Application;

/**
 * The Fly Load Tester console application wrapper.
 */
class FlyLoadTester
{
    const FLY_VERSION = '0.1';
    const FLY_CODENAME = 'Fox';

    /**
     * @var Application
     */
    private $application;

    /**
     * Fly, to the sky!
     */
    public function init()
    {
        // Set the working directory to the fly install directory
        if (!defined('FLY_DIR')) {
            define('FLY_DIR', realpath(__DIR__ . '/../'));
        }

        chdir(FLY_DIR);

        // Try to configure a better process title
        if (function_exists('cli_set_process_title')) {
            // this doesn't really seem to work without running as superuser nowadays
            cli_set_process_title('flyloadtester');
        }

        if (function_exists('setproctitle')) {
            // setproctitle is considered dangerous, but only because it breaks out of its memory bounds
            // luckily "fly" is equally long as the default "php", so we should be OK
            setproctitle('flyloadtester');
        }

        // Configure the console application
        $this->application = new Application();
        $this->application->setName('Fly Load Tester');
        $this->application->setVersion(FlyLoadTester::getVersionString());
        $this->application->setAutoExit(true);
        $this->application->setCatchExceptions(true);
        $this->application->setDefaultCommand('start');

        // Register commands
        $this->application->addCommands([
            new StartCommand()
        ]);

        // Start the console application
        $this->application->run();
    }

    /**
     * Returns the verbose version string for the FlyPHP application.
     *
     * @return string
     */
    public static function getVersionString()
    {
        return sprintf('%s ("Flying %s")', self::FLY_VERSION, self::FLY_CODENAME);
    }
}