<?php

declare(strict_types=1);

namespace Perfbase\Yii2\Tests\Fixtures;

use Perfbase\SDK\Perfbase;
use Perfbase\Yii2\Support\PerfbaseClientProvider;

class TestPerfbaseClientProvider extends PerfbaseClientProvider
{
    public static ?Perfbase $client = null;

    public function __construct()
    {
    }

    public function getClient(): ?Perfbase
    {
        return self::$client;
    }
}
