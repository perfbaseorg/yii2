<?php

declare(strict_types=1);

namespace Perfbase\Yii2\Tests\Fixtures;

use yii\web\Request;

class PathInfoOnlyRequest extends Request
{
    public function getUrl(): string
    {
        return '';
    }

    public function getPathInfo(): string
    {
        return 'pathinfo-only';
    }
}
