<?php

declare(strict_types=1);

namespace Perfbase\Yii2\Tests\Integration;

use Perfbase\Yii2\Tests\Fixtures\RecordingPerfbaseClient;
use Perfbase\Yii2\Tests\Fixtures\TestConsoleController;
use Perfbase\Yii2\Tests\Fixtures\TestPerfbaseClientProvider;
use Perfbase\Yii2\Tests\Fixtures\TestPerfbaseComponent;
use PHPUnit\Framework\TestCase;
use yii\base\Action;
use yii\base\ActionEvent;
use yii\BaseYii;
use yii\console\Application;
use yii\console\Response;

class ConsoleEventFlowTest extends TestCase
{
    protected function tearDown(): void
    {
        TestPerfbaseClientProvider::$client = null;
        $this->resetYiiApplication();
        parent::tearDown();
    }

    public function test_command_start_terminate_submit_flow(): void
    {
        $client = new RecordingPerfbaseClient();
        TestPerfbaseClientProvider::$client = $client;
        $app = $this->createApplication();

        $controller = new TestConsoleController('migrate', $app);
        $action = new Action('up', $controller);
        $app->getResponse()->exitStatus = 0;

        $app->trigger(Application::EVENT_BEFORE_ACTION, new ActionEvent($action));
        $app->trigger(Application::EVENT_AFTER_REQUEST);

        self::assertSame(['console.migrate/up'], $client->startedSpans);
        self::assertSame('migrate/up', $client->attributes['action']);
        self::assertSame(1, $client->submitCalls);
    }

    public function test_command_failure_path_captures_exception(): void
    {
        $client = new RecordingPerfbaseClient();
        TestPerfbaseClientProvider::$client = $client;
        $app = $this->createApplication();

        $controller = new TestConsoleController('migrate', $app);
        $action = new Action('up', $controller);
        $app->getResponse()->exitStatus = 1;

        $app->trigger(Application::EVENT_BEFORE_ACTION, new ActionEvent($action));
        $app->getErrorHandler()->exception = new \RuntimeException('command failed');
        $app->trigger(Application::EVENT_AFTER_REQUEST);

        self::assertSame('command failed', $client->attributes['exception']);
        self::assertSame(1, $client->submitCalls);
    }

    /**
     * @param array<string, mixed> $perfbaseConfig
     */
    private function createApplication(array $perfbaseConfig = []): Application
    {
        return new Application([
            'id' => 'test-console-integration-app',
            'basePath' => dirname(__DIR__, 2),
            'bootstrap' => ['perfbase'],
            'components' => [
                'response' => [
                    'class' => Response::class,
                ],
                'errorHandler' => [
                    'class' => \yii\console\ErrorHandler::class,
                ],
                'perfbase' => array_merge([
                    'class' => TestPerfbaseComponent::class,
                    'enabled' => true,
                    'sample_rate' => 1.0,
                    'api_key' => 'test-key',
                    'app_version' => 'test-suite',
                ], $perfbaseConfig),
            ],
        ]);
    }

    private function resetYiiApplication(): void
    {
        $property = new \ReflectionProperty(BaseYii::class, 'app');
        $property->setValue(null);
    }
}
