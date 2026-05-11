<?php

declare(strict_types=1);

namespace Perfbase\Yii2\Tests\Unit\Lifecycle;

use Perfbase\Yii2\Lifecycle\HttpRequestLifecycle;
use Perfbase\Yii2\PerfbaseComponent;
use Perfbase\Yii2\Tests\Fixtures\PathInfoOnlyRequest;
use Perfbase\Yii2\Tests\Fixtures\RecordingPerfbaseClient;
use Perfbase\Yii2\Tests\Fixtures\TestIdentity;
use Perfbase\Yii2\Tests\Fixtures\TestPerfbaseClientProvider;
use Perfbase\Yii2\Tests\Fixtures\TestPerfbaseComponent;
use Perfbase\Yii2\Tests\Fixtures\TestWebController;
use PHPUnit\Framework\TestCase;
use yii\base\Action;
use yii\BaseYii;
use yii\web\Application;
use yii\web\Request;

class HttpRequestLifecycleTest extends TestCase
{
    protected function tearDown(): void
    {
        TestPerfbaseClientProvider::$client = null;
        $this->resetYiiApplication();
        parent::tearDown();
    }

    public function test_start_and_stop_profile_http_request(): void
    {
        $client = new RecordingPerfbaseClient();
        TestPerfbaseClientProvider::$client = $client;

        $app = $this->createWebApplication();
        $app->getUser()->switchIdentity(new TestIdentity('user-123'));
        $controller = new TestWebController('articles', $app);
        $action = new Action('view', $controller);

        $lifecycle = new HttpRequestLifecycle($app, $action, $this->getPerfbaseComponent($app));
        $lifecycle->startProfiling();
        $lifecycle->setResponseStatusCode(201);
        $lifecycle->stopProfiling();

        self::assertSame(['http'], $client->startedSpans);
        self::assertSame(['http'], $client->stoppedSpans);
        self::assertSame(1, $client->submitCalls);
        self::assertSame('GET /articles/view', $client->attributes['action']);
        self::assertSame('https://example.com/articles/42', $client->attributes['http_url']);
        self::assertSame('201', $client->attributes['http_status_code']);
        self::assertSame('user-123', $client->attributes['user_id']);
        self::assertSame('http', $client->attributes['source']);
    }

    public function test_disallowed_http_status_code_is_not_submitted_by_default(): void
    {
        $client = new RecordingPerfbaseClient();
        TestPerfbaseClientProvider::$client = $client;

        $app = $this->createWebApplication();
        $controller = new TestWebController('articles', $app);
        $action = new Action('view', $controller);

        $lifecycle = new HttpRequestLifecycle($app, $action, $this->getPerfbaseComponent($app));
        $lifecycle->startProfiling();
        $lifecycle->setResponseStatusCode(404);
        $lifecycle->stopProfiling();

        self::assertSame(0, $client->submitCalls);
        self::assertSame(1, $client->resetCalls);
        self::assertSame('404', $client->attributes['http_status_code']);
    }

    public function test_server_error_status_code_is_submitted_by_default(): void
    {
        $client = new RecordingPerfbaseClient();
        TestPerfbaseClientProvider::$client = $client;

        $app = $this->createWebApplication();
        $controller = new TestWebController('articles', $app);
        $action = new Action('view', $controller);

        $lifecycle = new HttpRequestLifecycle($app, $action, $this->getPerfbaseComponent($app));
        $lifecycle->startProfiling();
        $lifecycle->setResponseStatusCode(503);
        $lifecycle->stopProfiling();

        self::assertSame(1, $client->submitCalls);
        self::assertSame(0, $client->resetCalls);
        self::assertSame('503', $client->attributes['http_status_code']);
    }

    public function test_custom_allowed_http_status_code_is_submitted(): void
    {
        $client = new RecordingPerfbaseClient();
        TestPerfbaseClientProvider::$client = $client;

        $app = $this->createWebApplication([
            'profile_http_status_codes' => [200, 404],
        ]);
        $controller = new TestWebController('articles', $app);
        $action = new Action('view', $controller);

        $lifecycle = new HttpRequestLifecycle($app, $action, $this->getPerfbaseComponent($app));
        $lifecycle->startProfiling();
        $lifecycle->setResponseStatusCode(404);
        $lifecycle->stopProfiling();

        self::assertSame(1, $client->submitCalls);
        self::assertSame(0, $client->resetCalls);
    }

    public function test_excluded_http_request_is_not_profiled(): void
    {
        $client = new RecordingPerfbaseClient();
        TestPerfbaseClientProvider::$client = $client;

        $app = $this->createWebApplication([
            'exclude' => ['http' => ['/articles/*'], 'console' => []],
        ]);

        $controller = new TestWebController('articles', $app);
        $action = new Action('view', $controller);
        $lifecycle = new HttpRequestLifecycle($app, $action, $this->getPerfbaseComponent($app));
        $lifecycle->startProfiling();
        $lifecycle->stopProfiling();

        self::assertSame([], $client->startedSpans);
        self::assertSame(0, $client->submitCalls);
    }

    public function test_disabled_http_profiling_is_not_started(): void
    {
        $client = new RecordingPerfbaseClient();
        TestPerfbaseClientProvider::$client = $client;

        $app = $this->createWebApplication(['enabled' => false]);
        $controller = new TestWebController('articles', $app);
        $action = new Action('view', $controller);
        $lifecycle = new HttpRequestLifecycle($app, $action, $this->getPerfbaseComponent($app));

        $lifecycle->startProfiling();

        self::assertFalse($lifecycle->hasStarted());
        self::assertSame([], $client->startedSpans);
    }

    public function test_exception_attribute_is_submitted(): void
    {
        $client = new RecordingPerfbaseClient();
        TestPerfbaseClientProvider::$client = $client;

        $app = $this->createWebApplication();
        $controller = new TestWebController('articles', $app);
        $action = new Action('view', $controller);
        $lifecycle = new HttpRequestLifecycle($app, $action, $this->getPerfbaseComponent($app));

        $lifecycle->startProfiling();
        $lifecycle->setException(new \RuntimeException('boom'));
        $lifecycle->stopProfiling();

        self::assertSame('boom', $client->attributes['exception']);
    }

    public function test_blank_route_uses_request_path_fallback(): void
    {
        $client = new RecordingPerfbaseClient();
        TestPerfbaseClientProvider::$client = $client;

        $app = $this->createWebApplication();
        $controller = new TestWebController('', $app);
        $action = new Action('', $controller);
        $lifecycle = new HttpRequestLifecycle($app, $action, $this->getPerfbaseComponent($app));

        $lifecycle->startProfiling();
        $lifecycle->stopProfiling();

        self::assertSame(['http'], $client->startedSpans);
        self::assertSame('GET /articles/42', $client->attributes['action']);
    }

    public function test_blank_route_falls_back_to_path_info_when_url_sources_are_empty(): void
    {
        $previousUri = $_SERVER['REQUEST_URI'] ?? null;
        $_SERVER['REQUEST_URI'] = '';

        try {
            $client = new RecordingPerfbaseClient();
            TestPerfbaseClientProvider::$client = $client;

            $app = $this->createWebApplication([], PathInfoOnlyRequest::class, '');
            $controller = new TestWebController('', $app);
            $action = new Action('', $controller);
            $lifecycle = new HttpRequestLifecycle($app, $action, $this->getPerfbaseComponent($app));

            $lifecycle->startProfiling();
            $lifecycle->stopProfiling();

            self::assertSame(['http'], $client->startedSpans);
            self::assertSame('GET /pathinfo-only', $client->attributes['action']);
            self::assertSame('https://example.com/pathinfo-only', $client->attributes['http_url']);
        } finally {
            if ($previousUri === null) {
                unset($_SERVER['REQUEST_URI']);
            } else {
                $_SERVER['REQUEST_URI'] = $previousUri;
            }
        }
    }

    /**
     * @param array<string, mixed> $perfbaseConfig
     */
    private function createWebApplication(array $perfbaseConfig = [], string $requestClass = Request::class, string $requestUri = '/articles/42?token=secret'): Application
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $requestUri;
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';

        $request = new $requestClass([
            'cookieValidationKey' => 'test',
            'scriptUrl' => '/index.php',
        ]);

        return new Application([
            'id' => 'test-web-app',
            'basePath' => dirname(__DIR__, 2),
            'components' => [
                'request' => $request,
                'user' => [
                    'identityClass' => TestIdentity::class,
                    'enableSession' => false,
                    'loginUrl' => null,
                ],
                'perfbase' => array_merge([
                    'class' => TestPerfbaseComponent::class,
                    'enabled' => true,
                    'sample_rate' => 1.0,
                    'profile_http_status_codes' => [...range(200, 299), ...range(500, 599)],
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
