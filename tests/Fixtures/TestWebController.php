<?php

declare(strict_types=1);

namespace Perfbase\Yii2\Tests\Fixtures;

use yii\web\Controller;

class TestWebController extends Controller
{
    public function actionIndex(): string
    {
        return 'ok';
    }
}
