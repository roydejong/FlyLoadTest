<?php

namespace FlyPHP\LoadTester\Config;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Parser;

/**
 * Utility for loading, validating and processing load test profiles / config.
 */
class ConfigLoader
{
    /**
     * Load and parse a specified configuration file and returns it as a TestConfig object.
     *
     * @param string $path
     * @throws ConfigException
     * @return TestConfig
     */
    public static function loadConfig(string $path = 'profiles.yml')
    {
        // Check if the config file is readable
        if (!file_exists($path) || !is_readable($path)) {
            throw new ConfigException("Specified configuration file does not exist or cannot be read: {$path}");
        }

        // Attempt to parse the config file as YAML
        $contents = file_get_contents($path);
        $parsedYaml = null;

        try {
            $parser = new Parser();
            $parsedYaml = $parser->parse($contents);
        } catch (ParseException $ex) {
            throw new ConfigException("Could not parse configuration file {$path} as YAML: {$ex->getMessage()}");
        }

        // Verify file contents
        if (empty($parsedYaml)) {
            throw new ConfigException("Configuration file is empty: {$path}");
        }

        if (!isset($parsedYaml['profiles']) || !is_array($parsedYaml['profiles']) || empty($parsedYaml['profiles'])) {
            throw new ConfigException("Configuration profile does not contain any profiles: {$path}");
        }

        // Parse data extracted from the config file, and return a TestConfig object
        return TestConfig::parse($parsedYaml);
    }
}