<?php

declare(strict_types=1);

namespace Perfbase\Yii2\Tests\Unit;

use Perfbase\Yii2\Tests\Fixtures\GetterConsoleResponse;
use Perfbase\Yii2\Tests\Fixtures\RecordingPerfbaseClient;
use Perfbase\Yii2\Tests\Fixtures\TestIdentity;
use Perfbase\Yii2\Tests\Fixtures\TestPerfbaseClientProvider;
use Perfbase\Yii2\Tests\Fixtures\TestPerfbaseComponent;
use Perfbase\Yii2\Tests\Fixtures\TestWebController;
use Perfbase\Yii2\PerfbaseComponent;
use PHPUnit\Framework\TestCase;
use yii\base\Action;
use yii\base\ActionEvent;
use yii\BaseYii;
use yii\console\Application as ConsoleApplication;
use yii\web\Application as WebApplication;

class PerfbaseBootstrapTest extends TestCase
{
    protected function tearDown(): void
    {
        TestPerfbaseClientProvider::$client = null;
        $this->resetYiiApplication();
        parent::tearDown();
    }

    public function test_bootstrap_is_idempotent(): void
    {
        $client = new RecordingPerfbaseClient();
        TestPerfbaseClientProvider::$client = $client;
        $app = $this->createWebApplication();
        $component = $this->getPerfbaseComponent($app);

        $component->bootstrap($app);
        $component->bootstrap($app);

        $controller = new TestWebController('site', $app);
        $action = new Action('index', $controller);

        $app->trigger(WebApplication::EVENT_BEFORE_ACTION, new ActionEvent($action));
        $app->trigger(WebApplication::EVENT_AFTER_REQUEST);

        self::assertSame(['http.GET./site/index'], $client->startedSpans);
        self::assertSame(1, $client->submitCalls);
    }

    public function test_after_request_without_active_lifecycle_noops(): void
    {
        $client = new RecordingPerfbaseClient();
        TestPerfbaseClientProvider::$client = $client;
        $app = $this->createWebApplication();

        $this->getPerfbaseComponent($app)->bootstrap($app);
        $app->trigger(WebApplication::EVENT_AFTER_REQUEST);

        self::assertSame([], $client->startedSpans);
        self::assertSame(0, $client->submitCalls);
    }

    public function test_console_response_getter_exit_code_is_used(): void
    {
        $client = new RecordingPerfbaseClient();
        TestPerfbaseClientProvider::$client = $client;
        $app = $this->createConsoleApplication();

        $controller = new \Perfbase\Yii2\Tests\Fixtures\TestConsoleController('cache', $app);
        $action = new Action('flush', $controller);

        $app->trigger(ConsoleApplication::EVENT_BEFORE_ACTION, new ActionEvent($action));
        $app->trigger(ConsoleApplication::EVENT_AFTER_REQUEST);

        self::assertSame('7', $client->attributes['exit_code']);
        self::assertSame(1, $client->submitCalls);
    }

    public function test_before_action_errors_are_caught_and_do_not_crash(): void
    {
        $client = new RecordingPerfbaseClient();
        TestPerfbaseClientProvider::$client = $client;
        $app = $this->createWebApplication([
            'debug' => true,
            'sample_rate' => 'invalid',
        ]);

        $controller = new TestWebController('site', $app);
        $action = new Action('index', $controller);

        $app->trigger(WebApplication::EVENT_BEFORE_ACTION, new ActionEvent($action));
        $app->trigger(WebApplication::EVENT_AFTER_REQUEST);

        self::assertSame([], $client->startedSpans);
        self::assertSame(0, $client->submitCalls);
    }

    /**
     * @param array<string, mixed> $perfbaseConfig
     */
    private function createWebApplication(array $perfbaseConfig = []): WebApplication
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/site/index';
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['HTTPS'] = 'on';

        return new WebApplication([
            'id' => 'test-bootstrap-web-app',
            'basePath' => dirname(__DIR__, 2),
            'components' => [
                'request' => [
                    'cookieValidationKey' => 'test',
                    'scriptUrl' => '/index.php',
                ],
                'user' => [
                    'identityClass' => TestIdentity::class,
                    'enableSession' => false,
                    'loginUrl' => null,
                ],
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

    private function createConsoleApplication(): ConsoleApplication
    {
        return new ConsoleApplication([
            'id' => 'test-bootstrap-console-app',
            'basePath' => dirname(__DIR__, 2),
            'bootstrap' => ['perfbase'],
            'components' => [
                'response' => new GetterConsoleResponse(),
                'perfbase' => [
                    'class' => TestPerfbaseComponent::class,
                    'enabled' => true,
                    'sample_rate' => 1.0,
                    'api_key' => 'test-key',
                    'app_version' => '1.2.3',
                ],
            ],
        ]);
    }

    private function getPerfbaseComponent(WebApplication $app): PerfbaseComponent
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
