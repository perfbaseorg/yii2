<?php

declare(strict_types=1);

namespace Perfbase\Yii2\Tests\Fixtures;

use yii\web\IdentityInterface;

class TestIdentity implements IdentityInterface
{
    private string $id;

    public function __construct(string $id)
    {
        $this->id = $id;
    }

    public static function findIdentity($id)
    {
        return new self((string) $id);
    }

    public static function findIdentityByAccessToken($token, $type = null)
    {
        return new self((string) $token);
    }

    public function getId()
    {
        return $this->id;
    }

    public function getAuthKey()
    {
        return 'test-auth-key';
    }

    public function validateAuthKey($authKey)
    {
        return $authKey === 'test-auth-key';
    }
}
