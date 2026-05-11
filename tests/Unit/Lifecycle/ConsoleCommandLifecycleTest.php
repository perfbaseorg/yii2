<?php

declare(strict_types=1);

namespace Perfbase\Yii2\Tests\Unit\Lifecycle;

use Perfbase\Yii2\Lifecycle\ConsoleCommandLifecycle;
use Perfbase\Yii2\PerfbaseComponent;
use Perfbase\Yii2\Tests\Fixtures\RecordingPerfbaseClient;
use Perfbase\Yii2\Tests\Fixtures\TestConsoleController;
use Perfbase\Yii2\Tests\Fixtures\TestPerfbaseClientProvider;
use Perfbase\Yii2\Tests\Fixtures\TestPerfbaseComponent;
use PHPUnit\Framework\TestCase;
use yii\BaseYii;
use yii\base\Action;
use yii\console\Application;

class ConsoleCommandLifecycleTest extends TestCase
{
    protected function tearDown(): void
    {
        TestPerfbaseClientProvider::$client = null;
        $this->resetYiiApplication();
        parent::tearDown();
    }

    public function test_console_command_profiles_and_sets_exit_code(): void
    {
        $client = new RecordingPerfbaseClient();
        TestPerfbaseClientProvider::$client = $client;

        $app = $this->createConsoleApplication();
        $controller = new TestConsoleController('migrate', $app);
        $action = new Action('up', $controller);

        $lifecycle = new ConsoleCommandLifecycle($app, $action, $this->getPerfbaseComponent($app));
        $lifecycle->startProfiling();
        $lifecycle->setExitCode(2);
        $lifecycle->setException(new \RuntimeException('failed'));
        $lifecycle->stopProfiling();

        self::assertSame(['artisan'], $client->startedSpans);
        self::assertSame('console', $client->attributes['source']);
        self::assertSame('migrate/up', $client->attributes['action']);
        self::assertSame('2', $client->attributes['exit_code']);
        self::assertSame('failed', $client->attributes['exception']);
        self::assertSame(1, $client->submitCalls);
    }

    public function test_excluded_console_command_is_not_profiled(): void
    {
        $client = new RecordingPerfbaseClient();
        TestPerfbaseClientProvider::$client = $client;

        $app = $this->createConsoleApplication([
            'exclude' => ['http' => [], 'console' => ['migrate/*']],
        ]);
        $controller = new TestConsoleController('migrate', $app);
        $action = new Action('up', $controller);

        $lifecycle = new ConsoleCommandLifecycle($app, $action, $this->getPerfbaseComponent($app));
        $lifecycle->startProfiling();
        $lifecycle->stopProfiling();

        self::assertSame([], $client->startedSpans);
        self::assertSame(0, $client->submitCalls);
    }

    public function test_disabled_console_profiling_is_not_started(): void
    {
        $client = new RecordingPerfbaseClient();
        TestPerfbaseClientProvider::$client = $client;

        $app = $this->createConsoleApplication(['enabled' => false]);
        $controller = new TestConsoleController('migrate', $app);
        $action = new Action('up', $controller);
        $lifecycle = new ConsoleCommandLifecycle($app, $action, $this->getPerfbaseComponent($app));

        $lifecycle->startProfiling();

        self::assertSame([], $client->startedSpans);
    }

    public function test_blank_console_action_falls_back_to_requested_route(): void
    {
        $client = new RecordingPerfbaseClient();
        TestPerfbaseClientProvider::$client = $client;

        $app = $this->createConsoleApplication();
        $app->requestedRoute = 'cache/flush';
        $controller = new TestConsoleController('', $app);
        $action = new Action('', $controller);

        $lifecycle = new ConsoleCommandLifecycle($app, $action, $this->getPerfbaseComponent($app));
        $lifecycle->startProfiling();
        $lifecycle->stopProfiling();

        self::assertSame(['artisan'], $client->startedSpans);
        self::assertSame('cache/flush', $client->attributes['action']);
    }

    /**
     * @param array<string, mixed> $perfbaseConfig
     */
    private function createConsoleApplication(array $perfbaseConfig = []): Application
    {
        return new Application([
            'id' => 'test-console-app',
            'basePath' => dirname(__DIR__, 2),
            'components' => [
                'perfbase' => array_merge([
                    'class' => TestPerfbaseComponent::class,
                    'enabled' => true,
                    'sample_rate' => 1.0,
                    'api_key' => 'test-key',
                    'app_version' => '1.2.3',
                ], $perfbaseConfig),
            ],
        ]);
    }

    private function getPerfbaseComponent(Application $app): PerfbaseComponent
    {
        /** @var PerfbaseComponent */
        return $app->get('perfbase');
    }

    private function resetYiiApplication(): void
    {
        $property = new \ReflectionProperty(BaseYii::class, 'app');
        $property->setValue(null);
    }
}
