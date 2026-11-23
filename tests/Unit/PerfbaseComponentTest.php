<?php

declare(strict_types=1);

namespace Perfbase\Yii2\Tests\Unit;

use Perfbase\Yii2\PerfbaseComponent;
use Perfbase\SDK\Config;
use Perfbase\Yii2\Support\PerfbaseClientProvider;
use Perfbase\Yii2\Support\PerfbaseErrorHandler;
use Perfbase\Yii2\Tests\Fixtures\RecordingPerfbaseClient;
use PHPUnit\Framework\TestCase;

class PerfbaseComponentTest extends TestCase
{
    public function test_configuration_defaults_are_normalized(): void
    {
        $component = new PerfbaseComponent();
        $config = $component->getConfig();

        self::assertFalse($config['enabled']);
        self::assertSame(0.1, $config['sample_rate']);
        self::assertSame(['*'], $config['include']['http']);
        self::assertSame(['queue/listen', 'queue/run', 'serve', 'serve/*'], $config['exclude']['console']);
        self::assertSame([...range(200, 299), ...range(500, 599)], $config['profile_http_status_codes']);
        self::assertSame('test', $component->getEnvironment());
    }

    public function test_string_config_values_from_environment_are_accepted_and_normalised(): void
    {
        $component = new PerfbaseComponent([
            'enabled' => 'true',
            'debug' => 'false',
            'log_errors' => '0',
            'api_key' => 'test-key',
            'api_url' => '',
            'sample_rate' => '1.0',
            'timeout' => '',
            'flags' => '12',
            'proxy' => '',
            'app_version' => null,
        ]);
        $config = $component->getConfig();

        self::assertTrue($config['enabled']);
        self::assertFalse($config['debug']);
        self::assertFalse($config['log_errors']);
        self::assertSame('https://ingress.perfbase.cloud', $config['api_url']);
        self::assertSame(10, $config['timeout']);
        self::assertSame(12, $config['flags']);
        self::assertNull($config['proxy']);
        self::assertSame('', $config['app_version']);

        $sdkConfig = null;
        $provider = new PerfbaseClientProvider(
            $config,
            new PerfbaseErrorHandler(true, false),
            static function (Config $config) use (&$sdkConfig): RecordingPerfbaseClient {
                $sdkConfig = $config;

                return new RecordingPerfbaseClient();
            }
        );

        self::assertInstanceOf(RecordingPerfbaseClient::class, $provider->getClient());
        self::assertInstanceOf(Config::class, $sdkConfig);
        self::assertSame(12, $sdkConfig->getFlags());
        self::assertSame(10, $sdkConfig->getTimeout());
    }

    public function test_default_console_excludes_survive_partial_exclude_config(): void
    {
        $component = new PerfbaseComponent([
            'exclude' => ['http' => ['/health']],
        ]);

        self::assertSame(['queue/listen', 'queue/run', 'serve', 'serve/*'], $component->getConfig()['exclude']['console']);
    }

    public function test_configuration_accepts_custom_values(): void
    {
        $component = new PerfbaseComponent([
            'enabled' => true,
            'api_key' => 'test-key',
            'sample_rate' => 1.0,
            'app_version' => '1.2.3',
            'profile_http_status_codes' => [200, 404],
            'include' => [
                'http' => ['GET /health'],
                'console' => ['migrate/*'],
            ],
        ]);

        $config = $component->getConfig();

        self::assertTrue($config['enabled']);
        self::assertSame('test-key', $config['api_key']);
        self::assertSame(1.0, $config['sample_rate']);
        self::assertSame('1.2.3', $config['app_version']);
        self::assertSame([200, 404], $config['profile_http_status_codes']);
        self::assertSame(['GET /health'], $config['include']['http']);
        self::assertSame(['migrate/*'], $config['include']['console']);
    }
}
