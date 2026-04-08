<?php

declare(strict_types=1);

namespace Perfbase\Yii2\Tests\Fixtures;

use Perfbase\Yii2\PerfbaseComponent;
use Perfbase\Yii2\Support\PerfbaseClientProvider;

class TestPerfbaseComponent extends PerfbaseComponent
{
    protected function createClientProvider(): PerfbaseClientProvider
    {
        return new TestPerfbaseClientProvider();
    }
}
