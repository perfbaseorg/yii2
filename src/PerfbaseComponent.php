<?php

declare(strict_types=1);

namespace Perfbase\Yii2;

use Perfbase\SDK\FeatureFlags;
use Perfbase\Yii2\Support\PerfbaseClientProvider;
use Perfbase\Yii2\Support\PerfbaseErrorHandler;
use yii\base\Application;
use yii\base\BootstrapInterface;
use yii\base\Component;

class PerfbaseComponent extends Component implements BootstrapInterface
{
    public bool $enabled = false;
    public bool $debug = false;
    public bool $log_errors = true;
    public string $api_key = '';
    public string $api_url = 'https://ingress.perfbase.cloud';
    /** @var float|int|string */
    public $sample_rate = 0.1;
    public int $timeout = 10;
    public ?string $proxy = null;
    public int $flags = FeatureFlags::DefaultFlags;
    public string $app_version = '';

    /** @var array<string, array<int, string>> */
    public array $include = [
        'http' => ['*'],
        'console' => ['*'],
    ];

    /** @var array<string, array<int, string>> */
    public array $exclude = [
        'http' => [],
        'console' => [],
    ];

    private ?PerfbaseClientProvider $clientProvider = null;
    private ?PerfbaseErrorHandler $errorHandler = null;
    private ?PerfbaseBootstrap $bootstrapper = null;

    public function bootstrap($app): void
    {
        $this->getBootstrapper()->bootstrap($app);
    }

    public function getClientProvider(): PerfbaseClientProvider
    {
        if ($this->clientProvider === null) {
            $this->clientProvider = $this->createClientProvider();
        }

        return $this->clientProvider;
    }

    public function getErrorHandler(): PerfbaseErrorHandler
    {
        if ($this->errorHandler === null) {
            $this->errorHandler = $this->createErrorHandler();
        }

        return $this->errorHandler;
    }

    public function getEnvironment(): string
    {
        return defined('YII_ENV') ? YII_ENV : 'production';
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return [
            'enabled' => $this->enabled,
            'debug' => $this->debug,
            'log_errors' => $this->log_errors,
            'api_key' => $this->api_key,
            'api_url' => $this->api_url,
            'sample_rate' => $this->sample_rate,
            'timeout' => $this->timeout,
            'proxy' => $this->proxy,
            'flags' => $this->flags,
            'app_version' => $this->app_version,
            'include' => [
                'http' => $this->normalizeFilters($this->include['http'] ?? ['*']),
                'console' => $this->normalizeFilters($this->include['console'] ?? ['*']),
            ],
            'exclude' => [
                'http' => $this->normalizeFilters($this->exclude['http'] ?? []),
                'console' => $this->normalizeFilters($this->exclude['console'] ?? []),
            ],
        ];
    }

    protected function createClientProvider(): PerfbaseClientProvider
    {
        return new PerfbaseClientProvider($this->getConfig(), $this->getErrorHandler());
    }

    protected function createErrorHandler(): PerfbaseErrorHandler
    {
        return new PerfbaseErrorHandler($this->debug, $this->log_errors);
    }

    private function getBootstrapper(): PerfbaseBootstrap
    {
        if ($this->bootstrapper === null) {
            $this->bootstrapper = new PerfbaseBootstrap($this);
        }

        return $this->bootstrapper;
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
