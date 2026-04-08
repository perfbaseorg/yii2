<?php

declare(strict_types=1);

namespace Perfbase\Yii2;

use Perfbase\Yii2\Lifecycle\ConsoleCommandLifecycle;
use Perfbase\Yii2\Lifecycle\HttpRequestLifecycle;
use Perfbase\Yii2\Profiling\AbstractProfiler;
use yii\base\ActionEvent;
use yii\base\Application as BaseApplication;
use yii\base\Event;
use yii\base\ErrorHandler;
use yii\console\Application as ConsoleApplication;
use yii\web\Application as WebApplication;

class PerfbaseBootstrap
{
    private PerfbaseComponent $component;
    private ?AbstractProfiler $activeLifecycle = null;
    private bool $bootstrapped = false;

    public function __construct(PerfbaseComponent $component)
    {
        $this->component = $component;
    }

    public function bootstrap(BaseApplication $app): void
    {
        if ($this->bootstrapped) {
            return;
        }

        $this->bootstrapped = true;

        $app->on(BaseApplication::EVENT_BEFORE_ACTION, function (ActionEvent $event) use ($app): void {
            $this->handleBeforeAction($app, $event);
        });

        $app->on(BaseApplication::EVENT_AFTER_REQUEST, function () use ($app): void {
            $this->finalizeLifecycle($app);
        });

        if ($app->has('errorHandler')) {
            $errorHandler = $app->get('errorHandler');
            if ($errorHandler instanceof ErrorHandler && defined(ErrorHandler::class . '::EVENT_SHUTDOWN')) {
                $errorHandler->on(ErrorHandler::EVENT_SHUTDOWN, function () use ($app): void {
                    $this->finalizeLifecycle($app);
                });
            }
        }
    }

    private function handleBeforeAction(BaseApplication $app, ActionEvent $event): void
    {
        if ($this->activeLifecycle !== null) {
            return;
        }

        try {
            if ($app instanceof WebApplication) {
                $this->activeLifecycle = new HttpRequestLifecycle($app, $event->action, $this->component);
            } elseif ($app instanceof ConsoleApplication) {
                $this->activeLifecycle = new ConsoleCommandLifecycle($app, $event->action, $this->component);
            } else {
                return;
            }

            $this->activeLifecycle->startProfiling();
        } catch (\Throwable $throwable) {
            $this->activeLifecycle = null;
            $this->component->getErrorHandler()->handle($throwable, 'yii2_before_action');
        }
    }

    private function finalizeLifecycle(BaseApplication $app): void
    {
        if ($this->activeLifecycle === null) {
            return;
        }

        try {
            if ($app->has('errorHandler')) {
                $errorHandler = $app->get('errorHandler');
                if ($errorHandler instanceof ErrorHandler && $errorHandler->exception instanceof \Throwable) {
                    $this->activeLifecycle->setException($errorHandler->exception);
                }
            }

            if ($this->activeLifecycle instanceof HttpRequestLifecycle && $app instanceof WebApplication) {
                $this->activeLifecycle->setResponseStatusCode($app->getResponse()->getStatusCode());
            }

            if ($this->activeLifecycle instanceof ConsoleCommandLifecycle && $app instanceof ConsoleApplication) {
                $exitCode = $this->resolveConsoleExitCode($app);
                if ($exitCode !== null) {
                    $this->activeLifecycle->setExitCode($exitCode);
                }
            }

            $this->activeLifecycle->stopProfiling();
        } catch (\Throwable $throwable) {
            $this->component->getErrorHandler()->handle($throwable, 'yii2_finalize');
        } finally {
            $this->activeLifecycle = null;
        }
    }

    private function resolveConsoleExitCode(ConsoleApplication $app): ?int
    {
        $response = $app->getResponse();

        foreach (['getExitStatus', 'getExitCode'] as $method) {
            if (method_exists($response, $method)) {
                $value = $response->$method();
                if (is_int($value)) {
                    return $value;
                }
            }
        }

        foreach (['exitStatus', 'exitCode'] as $property) {
            if (isset($response->$property) && is_int($response->$property)) {
                return $response->$property;
            }
        }

        return null;
    }
}
