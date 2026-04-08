<?php

declare(strict_types=1);

namespace Perfbase\Yii2\Tests\Fixtures;

use yii\console\Controller;

class TestConsoleController extends Controller
{
    public function actionIndex(): int
    {
        return 0;
    }
}
