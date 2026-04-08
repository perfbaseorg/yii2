<?php

declare(strict_types=1);

namespace Perfbase\Yii2\Support;

use yii\base\Action;
use yii\console\Application as ConsoleApplication;
use yii\web\Request;
use yii\web\Application as WebApplication;

class SpanNaming
{
    public static function forHttp(WebApplication $app, Action $action): string
    {
        $route = $action->getUniqueId();
        if (!self::hasStableRoute($route)) {
            $route = self::requestPath($app->getRequest());
        } else {
            $route = self::normalizePath($route);
        }

        return sprintf('http.%s.%s', $app->getRequest()->getMethod(), $route);
    }

    public static function forConsole(ConsoleApplication $app, Action $action): string
    {
        $command = $action->getUniqueId();
        if (!self::hasStableRoute($command)) {
            $command = $app->requestedRoute ?: 'unknown';
        }

        return sprintf('console.%s', $command);
    }

    private static function normalizePath(string $path): string
    {
        $trimmed = ltrim($path, '/');

        return '/' . $trimmed;
    }

    private static function hasStableRoute(string $route): bool
    {
        return trim($route, '/') !== '';
    }

    private static function requestPath(Request $request): string
    {
        $urlPath = parse_url($request->getUrl(), PHP_URL_PATH);
        if (is_string($urlPath) && $urlPath !== '') {
            return self::normalizePath($urlPath);
        }

        $serverPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        if (is_string($serverPath) && $serverPath !== '') {
            return self::normalizePath($serverPath);
        }

        return self::normalizePath($request->getPathInfo());
    }
}
