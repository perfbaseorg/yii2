<?php

declare(strict_types=1);

namespace Perfbase\Yii2\Tests\Fixtures;

use Perfbase\Yii2\Profiling\AbstractProfiler;
use Perfbase\Yii2\Support\PerfbaseClientProvider;
use Perfbase\Yii2\Support\PerfbaseErrorHandler;

class TestProfiler extends AbstractProfiler
{
    private bool $shouldProfile = true;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        PerfbaseClientProvider $clientProvider,
        PerfbaseErrorHandler $errorHandler,
        array $config
    ) {
        parent::__construct('test.span', $clientProvider, $errorHandler, $config, 'test', '1.2.3');
    }

    public function setShouldProfile(bool $shouldProfile): void
    {
        $this->shouldProfile = $shouldProfile;
    }

    protected function shouldProfile(): bool
    {
        return $this->shouldProfile;
    }
}
