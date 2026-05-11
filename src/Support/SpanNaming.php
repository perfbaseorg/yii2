<?php

declare(strict_types=1);

namespace Perfbase\Yii2\Support;

use yii\base\Action;
use yii\console\Application as ConsoleApplication;
use yii\web\Application as WebApplication;

class SpanNaming
{
    public static function forHttp(WebApplication $app, Action $action): string
    {
        return 'http';
    }

    public static function forConsole(ConsoleApplication $app, Action $action): string
    {
        return 'artisan';
    }
}
