<?php

declare(strict_types=1);

namespace Perfbase\Yii2\Tests\Fixtures;

use yii\console\Response;

class GetterConsoleResponse extends Response
{
    public function getExitCode(): int
    {
        return 7;
    }
}
