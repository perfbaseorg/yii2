<?php

declare(strict_types=1);

namespace Perfbase\Yii2\Tests\Integration;

use Perfbase\Yii2\Tests\Fixtures\RecordingPerfbaseClient;
use Perfbase\Yii2\Tests\Fixtures\TestIdentity;
use Perfbase\Yii2\Tests\Fixtures\TestPerfbaseClientProvider;
use Perfbase\Yii2\Tests\Fixtures\TestPerfbaseComponent;
use Perfbase\Yii2\Tests\Fixtures\TestWebController;
use PHPUnit\Framework\TestCase;
use yii\base\Action;
use yii\base\ActionEvent;
use yii\BaseYii;
use yii\web\Application;

class WebEventFlowTest extends TestCase
{
    protected function tearDown(): void
    {
        TestPerfbaseClientProvider::$client = null;
        $this->resetYiiApplication();
        parent::tearDown();
    }

    public function test_request_start_stop_submit_flow(): void
    {
        $client = new RecordingPerfbaseClient();
        TestPerfbaseClientProvider::$client = $client;
        $app = $this->createApplication();

        $controller = new TestWebController('site', $app);
        $action = new Action('index', $controller);

        $app->getResponse()->setStatusCode(202);
        $app->trigger(Application::EVENT_BEFORE_ACTION, new ActionEvent($action));
        $app->trigger(Application::EVENT_AFTER_REQUEST);

        self::assertSame(['http.GET./site/index'], $client->startedSpans);
        self::assertSame('202', $client->attributes['http_status_code']);
        self::assertSame(1, $client->submitCalls);
    }

    public function test_exception_path_still_cleans_up(): void
    {
        $client = new RecordingPerfbaseClient();
        TestPerfbaseClientProvider::$client = $client;
        $app = $this->createApplication();

        $controller = new TestWebController('site', $app);
        $action = new Action('index', $controller);

        $app->trigger(Application::EVENT_BEFORE_ACTION, new ActionEvent($action));
        $app->getErrorHandler()->exception = new \RuntimeException('boom');
        $app->trigger(Application::EVENT_AFTER_REQUEST);

        self::assertSame('boom', $client->attributes['exception']);
        self::assertSame(1, $client->submitCalls);
    }

    public function test_disabled_state_results_in_no_profiling(): void
    {
        $client = new RecordingPerfbaseClient();
        TestPerfbaseClientProvider::$client = $client;
        $app = $this->createApplication(['enabled' => false]);

        $controller = new TestWebController('site', $app);
        $action = new Action('index', $controller);

        $app->trigger(Application::EVENT_BEFORE_ACTION, new ActionEvent($action));
        $app->trigger(Application::EVENT_AFTER_REQUEST);

        self::assertSame([], $client->startedSpans);
        self::assertSame(0, $client->submitCalls);
    }

    /**
     * @param array<string, mixed> $perfbaseConfig
     */
    private function createApplication(array $perfbaseConfig = []): Application
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/site/index?token=secret';
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';

        return new Application([
            'id' => 'test-web-integration-app',
            'basePath' => dirname(__DIR__, 2),
            'bootstrap' => ['perfbase'],
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
