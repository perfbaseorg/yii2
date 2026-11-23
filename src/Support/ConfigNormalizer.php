<?php

declare(strict_types=1);

namespace Perfbase\Yii2\Support;

/**
 * Normalises loosely typed config values (environment variables, framework
 * config files) into the strict types the Perfbase SDK expects.
 */
final class ConfigNormalizer
{
    /**
     * @param mixed $value
     */
    public static function bool($value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value !== 0;
        }

        if (is_string($value)) {
            $parsed = filter_var(trim($value), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            return $parsed ?? $default;
        }

        return $default;
    }

    /**
     * @param mixed $value
     */
    public static function int($value, int $default): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^\s*\d+\s*$/', $value) === 1) {
            return (int) $value;
        }

        return $default;
    }

    /**
     * @param mixed $value
     */
    public static function stringOrNull($value): ?string
    {
        if (is_int($value) || is_float($value)) {
            $value = (string) $value;
        }

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @param mixed $value
     */
    public static function string($value, string $default = ''): string
    {
        return self::stringOrNull($value) ?? $default;
    }
}
