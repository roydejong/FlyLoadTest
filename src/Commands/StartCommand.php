<?php

namespace FlyPHP\LoadTester\Commands;

use FlyPHP\LoadTester\Config\ConfigLoader;
use FlyPHP\LoadTester\FlyLoadTester;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Main command for the FlyPHP server.
 * Starts listening and handling connections.
 */
class StartCommand extends Command
{
    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this->setName('start')
            ->setDescription('Start the Fly load tester application')
            ->addOption('config', null, InputOption::VALUE_OPTIONAL, 'Configuration file path (defaults to `profiles.yml`)');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Print banner
        $version = FlyLoadTester::getVersionString();
        $output->writeln("<info>Fly Load Tester</info> <comment>v{$version}</comment>");

        // Load configuration file
        $configName = $input->getOption('config');

        if (empty($configName)) {
            $configName = 'profiles.yml';
        }

        $configLoader = new ConfigLoader();
        $config = $configLoader->loadConfig($configName);

        $profileCount = count($config->profiles);
        $output->writeln("Loaded configuration file from {$configName}.");
        $output->writeln("Discovered {$profileCount} load test profiles.");

        // Run the load test
        $output->writeln('<error>TODO: Load tests</error>');
        $output->writeln('<comment>Load tester process complete.</comment>');
    }
}