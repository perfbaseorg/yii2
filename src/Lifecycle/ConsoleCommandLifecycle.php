<?php

declare(strict_types=1);

namespace Perfbase\Yii2\Lifecycle;

use Perfbase\Yii2\PerfbaseComponent;
use Perfbase\Yii2\Profiling\AbstractProfiler;
use Perfbase\Yii2\Support\FilterMatcher;
use Perfbase\Yii2\Support\SpanNaming;
use yii\base\Action;
use yii\console\Application;

class ConsoleCommandLifecycle extends AbstractProfiler
{
    private Application $app;
    private Action $action;

    public function __construct(Application $app, Action $action, PerfbaseComponent $component)
    {
        parent::__construct(
            SpanNaming::forConsole($app, $action),
            $component->getClientProvider(),
            $component->getErrorHandler(),
            $component->getConfig(),
            $component->getEnvironment(),
            (string) ($component->getConfig()['app_version'] ?? '')
        );

        $this->app = $app;
        $this->action = $action;
    }

    protected function shouldProfile(): bool
    {
        if (!(bool) ($this->config['enabled'] ?? false)) {
            return false;
        }

        return FilterMatcher::passesFilters(
            [$this->resolveCommandName()],
            $this->normalizeFilters($this->config['include']['console'] ?? ['*']),
            $this->normalizeFilters($this->config['exclude']['console'] ?? [])
        );
    }

    protected function setDefaultAttributes(): void
    {
        parent::setDefaultAttributes();

        $this->setAttributes([
            'source' => 'console',
            'action' => $this->resolveCommandName(),
        ]);
    }

    private function resolveCommandName(): string
    {
        $command = $this->action->getUniqueId();
        if (trim($command, '/') === '') {
            $command = $this->app->requestedRoute ?: 'unknown';
        }

        return $command;
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
}
