<?php

declare(strict_types=1);

namespace Perfbase\Yii2\Tests\Unit\Support;

use Perfbase\Yii2\Tests\Fixtures\PathInfoOnlyRequest;
use Perfbase\Yii2\Tests\Fixtures\ServerFallbackRequest;
use Perfbase\Yii2\Support\SpanNaming;
use Perfbase\Yii2\Tests\Fixtures\TestConsoleController;
use Perfbase\Yii2\Tests\Fixtures\TestWebController;
use PHPUnit\Framework\TestCase;
use yii\base\Action;
use yii\BaseYii;
use yii\console\Application as ConsoleApplication;
use yii\web\Application as WebApplication;

class SpanNamingTest extends TestCase
{
    public function test_http_prefers_action_route(): void
    {
        $app = $this->createWebApplication();
        $controller = new TestWebController('articles', $app);
        $action = new Action('view', $controller);

        self::assertSame('http.GET./articles/view', SpanNaming::forHttp($app, $action));
    }

    public function test_http_falls_back_to_request_path(): void
    {
        $app = $this->createWebApplication('/raw/path');
        $controller = new TestWebController('', $app);
        $action = new Action('', $controller);

        self::assertSame('http.GET./raw/path', SpanNaming::forHttp($app, $action));
    }

    public function test_console_span_name_is_stable(): void
    {
        $app = $this->createConsoleApplication();
        $controller = new TestConsoleController('migrate', $app);
        $action = new Action('up', $controller);

        self::assertSame('console.migrate/up', SpanNaming::forConsole($app, $action));
    }

    public function test_http_falls_back_to_server_request_uri_when_request_url_is_empty(): void
    {
        $app = $this->createWebApplication('/server/fallback', ServerFallbackRequest::class);
        $controller = new TestWebController('', $app);
        $action = new Action('', $controller);

        self::assertSame('http.GET./server/fallback', SpanNaming::forHttp($app, $action));
    }

    public function test_console_falls_back_to_unknown_when_requested_route_is_empty(): void
    {
        $app = $this->createConsoleApplication();
        $app->requestedRoute = '';
        $controller = new TestConsoleController('', $app);
        $action = new Action('', $controller);

        self::assertSame('console.unknown', SpanNaming::forConsole($app, $action));
    }

    public function test_http_falls_back_to_path_info_when_other_request_sources_are_empty(): void
    {
        $previousUri = $_SERVER['REQUEST_URI'] ?? null;
        $_SERVER['REQUEST_URI'] = '';

        try {
            $app = $this->createWebApplication('', PathInfoOnlyRequest::class);
            $controller = new TestWebController('', $app);
            $action = new Action('', $controller);

            self::assertSame('http.GET./pathinfo-only', SpanNaming::forHttp($app, $action));
        } finally {
            if ($previousUri === null) {
                unset($_SERVER['REQUEST_URI']);
            } else {
                $_SERVER['REQUEST_URI'] = $previousUri;
            }
        }
    }

    private function createWebApplication(string $uri = '/articles/42', string $requestClass = \yii\web\Request::class): WebApplication
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['HTTPS'] = 'on';

        return new WebApplication([
            'id' => 'test-web-app-span',
            'basePath' => dirname(__DIR__, 2),
            'components' => [
                'request' => [
                    'class' => $requestClass,
                    'cookieValidationKey' => 'test',
                    'scriptUrl' => '/index.php',
                ],
            ],
        ]);
    }

    private function createConsoleApplication(): ConsoleApplication
    {
        return new ConsoleApplication([
            'id' => 'test-console-app-span',
            'basePath' => dirname(__DIR__, 2),
        ]);
    }

    protected function tearDown(): void
    {
        $this->resetYiiApplication();
    }

    private function resetYiiApplication(): void
    {
        $property = new \ReflectionProperty(BaseYii::class, 'app');
        $property->setValue(null);
    }
}
