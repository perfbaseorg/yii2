<?php

declare(strict_types=1);

namespace Perfbase\Yii2\Tests\Unit;

use Perfbase\Yii2\PerfbaseComponent;
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
        self::assertSame([], $config['exclude']['console']);
        self::assertSame('test', $component->getEnvironment());
    }

    public function test_configuration_accepts_custom_values(): void
    {
        $component = new PerfbaseComponent([
            'enabled' => true,
            'api_key' => 'test-key',
            'sample_rate' => 1.0,
            'app_version' => '1.2.3',
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
        self::assertSame(['GET /health'], $config['include']['http']);
        self::assertSame(['migrate/*'], $config['include']['console']);
    }
}
