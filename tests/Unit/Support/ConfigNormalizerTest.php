<?php

declare(strict_types=1);

namespace Perfbase\Yii2\Tests\Unit\Support;

use Perfbase\Yii2\Support\ConfigNormalizer;
use PHPUnit\Framework\TestCase;

class ConfigNormalizerTest extends TestCase
{
    public function test_bool_parses_environment_strings(): void
    {
        self::assertTrue(ConfigNormalizer::bool('true', false));
        self::assertTrue(ConfigNormalizer::bool(' 1 ', false));
        self::assertTrue(ConfigNormalizer::bool('on', false));
        self::assertFalse(ConfigNormalizer::bool('false', true));
        self::assertFalse(ConfigNormalizer::bool('0', true));
        self::assertFalse(ConfigNormalizer::bool('', true));
        self::assertFalse(ConfigNormalizer::bool(0, true));
        self::assertTrue(ConfigNormalizer::bool('maybe', true));
        self::assertFalse(ConfigNormalizer::bool(null, false));
    }

    public function test_int_accepts_digit_strings_and_falls_back_otherwise(): void
    {
        self::assertSame(12, ConfigNormalizer::int('12', 10));
        self::assertSame(12, ConfigNormalizer::int(' 12 ', 10));
        self::assertSame(10, ConfigNormalizer::int('', 10));
        self::assertSame(10, ConfigNormalizer::int('abc', 10));
        self::assertSame(10, ConfigNormalizer::int(null, 10));
        self::assertSame(0, ConfigNormalizer::int(0, 10));
    }

    public function test_string_helpers_trim_and_treat_blank_as_missing(): void
    {
        self::assertNull(ConfigNormalizer::stringOrNull(''));
        self::assertNull(ConfigNormalizer::stringOrNull('   '));
        self::assertNull(ConfigNormalizer::stringOrNull(false));
        self::assertSame('http://proxy:8080', ConfigNormalizer::stringOrNull(' http://proxy:8080 '));
        self::assertSame('2', ConfigNormalizer::string(2));
        self::assertSame('fallback', ConfigNormalizer::string(null, 'fallback'));
    }
}
