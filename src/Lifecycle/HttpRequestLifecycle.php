<?php

declare(strict_types=1);

namespace Perfbase\Yii2\Lifecycle;

use Perfbase\SDK\Utils\EnvironmentUtils;
use Perfbase\Yii2\PerfbaseComponent;
use Perfbase\Yii2\Profiling\AbstractProfiler;
use Perfbase\Yii2\Support\FilterMatcher;
use Perfbase\Yii2\Support\SpanNaming;
use yii\base\Action;
use yii\web\Application;
use yii\web\Request;

class HttpRequestLifecycle extends AbstractProfiler
{
    private Application $app;
    private Action $action;

    public function __construct(Application $app, Action $action, PerfbaseComponent $component)
    {
        parent::__construct(
            SpanNaming::forHttp($app, $action),
            $component->getClientProvider(),
            $component->getErrorHandler(),
            $component->getConfig(),
            $component->getEnvironment(),
            (string) ($component->getConfig()['app_version'] ?? '')
        );

        $this->app = $app;
        $this->action = $action;
    }

    public function setResponseStatusCode(int $statusCode): void
    {
        $this->setAttribute('http_status_code', (string) $statusCode);
    }

    protected function shouldProfile(): bool
    {
        if (!(bool) ($this->config['enabled'] ?? false)) {
            return false;
        }

        return FilterMatcher::passesFilters(
            $this->getRequestComponents(),
            $this->normalizeFilters($this->config['include']['http'] ?? ['*']),
            $this->normalizeFilters($this->config['exclude']['http'] ?? [])
        );
    }

    protected function setDefaultAttributes(): void
    {
        parent::setDefaultAttributes();

        $request = $this->app->getRequest();
        $path = $this->requestPath($request);
        $routeId = $this->action->getUniqueId();
        $route = $this->normalizePath($this->hasStableRoute($routeId) ? $routeId : $path);

        $this->setAttributes([
            'source' => 'http',
            'action' => sprintf('%s %s', $request->getMethod(), $route),
            'http_method' => $request->getMethod(),
            'http_url' => $request->getHostInfo() . $path,
            'user_ip' => (string) (EnvironmentUtils::getUserIp() ?? ''),
            'user_agent' => (string) (EnvironmentUtils::getUserUserAgent() ?? ''),
        ]);

        if ($this->app->has('user')) {
            $user = $this->app->get('user');
            if ($user !== null && method_exists($user, 'getIsGuest') && !$user->getIsGuest() && method_exists($user, 'getId')) {
                $identifier = $user->getId();
                if ($identifier !== null && $identifier !== '') {
                    $this->setAttribute('user_id', (string) $identifier);
                }
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function getRequestComponents(): array
    {
        $request = $this->app->getRequest();
        $path = $this->requestPath($request);
        $routeId = $this->action->getUniqueId();
        $route = $this->hasStableRoute($routeId) ? $this->normalizePath($routeId) : $path;
        $controller = get_class($this->action->controller) . '::' . $this->action->id;

        return array_values(array_unique([
            $path,
            $route,
            $controller,
            $request->getMethod() . ' ' . $path,
            $request->getMethod() . ' ' . $route,
            (string) $this->app->requestedRoute,
        ]));
    }

    /**
     * @param mixed $filters
     * @return array<int, string>
     */
    private function normalizeFilters($filters): array
    {
        if (!is_array($filters)) {
            return [];
        }

        return array_values(array_filter($filters, static function ($filter): bool {
            return is_string($filter) && $filter !== '';
        }));
    }

    private function normalizePath(string $path): string
    {
        $trimmed = ltrim($path, '/');

        return '/' . $trimmed;
    }

    private function hasStableRoute(string $route): bool
    {
        return trim($route, '/') !== '';
    }

    private function requestPath(Request $request): string
    {
        $urlPath = parse_url($request->getUrl(), PHP_URL_PATH);
        if (is_string($urlPath) && $urlPath !== '') {
            return $this->normalizePath($urlPath);
        }

        $serverPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        if (is_string($serverPath) && $serverPath !== '') {
            return $this->normalizePath($serverPath);
        }

        return $this->normalizePath($request->getPathInfo());
    }
}
