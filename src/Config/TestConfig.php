<?php
/**
 * Created by PhpStorm.
 * User: roy
 * Date: 2-4-16
 * Time: 16:20
 */

namespace FlyPHP\LoadTester\Config;

/**
 * Configuration data.
 */
class TestConfig
{
    /**
     * @var TestProfile[]
     */
    public $profiles;

    /**
     * Parses raw user config data into a TestConfig object.
     *
     * @param mixed $yaml
     * @return TestConfig
     */
    public static function parse($yaml)
    {
        $config = new TestConfig();
        $config->profiles = [];

        foreach ($yaml['profiles'] as $profileData) {
            $config->profiles = TestProfile::parse($profileData);
        }

        return $config;
    }
}