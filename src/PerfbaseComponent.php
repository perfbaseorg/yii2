<?php

declare(strict_types=1);

namespace Perfbase\Yii2;

use Perfbase\SDK\FeatureFlags;
use Perfbase\Yii2\Support\ConfigNormalizer;
use Perfbase\Yii2\Support\PerfbaseClientProvider;
use Perfbase\Yii2\Support\PerfbaseErrorHandler;
use yii\base\Application;
use yii\base\BootstrapInterface;
use yii\base\Component;

class PerfbaseComponent extends Component implements BootstrapInterface
{
    public const DEFAULT_API_URL = 'https://ingress.perfbase.cloud';

    /**
     * Long-running console commands. Profiling them would keep a single trace
     * open for the life of the worker process. yii2-queue commands are matched
     * as `queue/<action>` whatever ID the queue component is registered under.
     */
    public const DEFAULT_CONSOLE_EXCLUDES = ['queue/listen', 'queue/run', 'serve', 'serve/*'];

    // Config properties are intentionally untyped: Yii assigns component config
    // verbatim, so values read from environment variables arrive as strings (or
    // null / false), which would be a TypeError on a typed property. They are
    // normalised in getConfig().

    /** @var bool|int|string|null */
    public $enabled = false;
    /** @var bool|int|string|null */
    public $debug = false;
    /** @var bool|int|string|null */
    public $log_errors = true;
    /** @var string|null */
    public $api_key = '';
    /** @var string|null */
    public $api_url = self::DEFAULT_API_URL;
    /** @var float|int|string */
    public $sample_rate = 0.1;
    /** @var array<int, int|string>|null */
    public $profile_http_status_codes = null;
    /** @var int|string|null */
    public $timeout = 10;
    /** @var string|null */
    public $proxy = null;
    /** @var int|string|null */
    public $flags = FeatureFlags::DefaultFlags;
    /** @var int|string|null */
    public $app_version = '';

    /** @var array<string, mixed>|null */
    public $include = [
        'http' => ['*'],
        'console' => ['*'],
    ];

    /**
     * When a context key is missing, its defaults apply, so overriding only
     * `exclude.http` keeps the long-running console commands excluded.
     *
     * @var array<string, mixed>|null
     */
    public $exclude = [
        'http' => [],
        'console' => self::DEFAULT_CONSOLE_EXCLUDES,
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
        $profileHttpStatusCodes = $this->profile_http_status_codes === null
            ? [...range(200, 299), ...range(500, 599)]
            : $this->normalizeStatusCodes($this->profile_http_status_codes);

        $include = is_array($this->include) ? $this->include : [];
        $exclude = is_array($this->exclude) ? $this->exclude : [];

        return [
            'enabled' => ConfigNormalizer::bool($this->enabled, false),
            'debug' => ConfigNormalizer::bool($this->debug, false),
            'log_errors' => ConfigNormalizer::bool($this->log_errors, true),
            'api_key' => ConfigNormalizer::string($this->api_key),
            'api_url' => ConfigNormalizer::string($this->api_url, self::DEFAULT_API_URL),
            'sample_rate' => $this->sample_rate,
            'profile_http_status_codes' => $profileHttpStatusCodes,
            'timeout' => ConfigNormalizer::int($this->timeout, 10),
            'proxy' => ConfigNormalizer::stringOrNull($this->proxy),
            'flags' => ConfigNormalizer::int($this->flags, FeatureFlags::DefaultFlags),
            'app_version' => ConfigNormalizer::string($this->app_version),
            'include' => [
                'http' => $this->normalizeFilters($include['http'] ?? ['*']),
                'console' => $this->normalizeFilters($include['console'] ?? ['*']),
            ],
            'exclude' => [
                'http' => $this->normalizeFilters($exclude['http'] ?? []),
                'console' => $this->normalizeFilters($exclude['console'] ?? self::DEFAULT_CONSOLE_EXCLUDES),
            ],
        ];
    }

    protected function createClientProvider(): PerfbaseClientProvider
    {
        return new PerfbaseClientProvider($this->getConfig(), $this->getErrorHandler());
    }

    protected function createErrorHandler(): PerfbaseErrorHandler
    {
        return new PerfbaseErrorHandler(
            ConfigNormalizer::bool($this->debug, false),
            ConfigNormalizer::bool($this->log_errors, true)
        );
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

    /**
     * @param mixed $statusCodes
     * @return array<int, int>
     */
    private function normalizeStatusCodes($statusCodes): array
    {
        if (!is_array($statusCodes)) {
            return [];
        }

        $normalized = [];

        foreach ($statusCodes as $statusCode) {
            if (is_int($statusCode)) {
                $normalized[] = $statusCode;
                continue;
            }

            if (is_string($statusCode) && ctype_digit($statusCode)) {
                $normalized[] = (int) $statusCode;
            }
        }

        return array_values(array_unique($normalized));
    }
}
