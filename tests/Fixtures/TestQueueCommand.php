<?php

declare(strict_types=1);

namespace Perfbase\Yii2\Tests\Fixtures;

if (!class_exists(\yii\queue\cli\Command::class)) {
    require_once dirname(__DIR__) . '/Stubs/QueueCommand.php';
}

class TestQueueCommand extends \yii\queue\cli\Command
{
    public function actionListen(): int
    {
        return 0;
    }
}
