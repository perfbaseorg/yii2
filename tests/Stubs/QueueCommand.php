<?php

declare(strict_types=1);

namespace yii\queue\cli;

// Minimal stand-in for yiisoft/yii2-queue's console command base class, which
// is not a dependency of this package. Only loaded when the real one is absent.
abstract class Command extends \yii\console\Controller
{
}
