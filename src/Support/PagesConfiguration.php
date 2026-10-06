<?php

declare(strict_types=1);

namespace Nvl\Pages\Support;

use InvalidArgumentException;
use Nvl\Pages\Definitions\Tables\PagesTables;
use Nvl\Support\Config\PackageStorage;

/**
 * Normalizes values crossing Laravel's untyped configuration boundary.
 */
final class PagesConfiguration
{
    /**
     * Return one validated configured package table name.
     */
    public static function table(string $key, string $default): string
    {
        return PagesTables::get($key);
    }

    /**
     * Return the configured package connection or the application default.
     */
    public static function connection(): ?string
    {
        return PackageStorage::connection('pages');
    }

    /**
     * Return the validated maximum page-tree depth.
     */
    public static function maximumDepth(): int
    {
        $value = config('pages.hierarchy.maximum_depth', 4);

        if (! is_int($value) || $value < 1 || $value > 4) {
            throw new InvalidArgumentException(
                'pages.hierarchy.maximum_depth must be between 1 and 4.',
            );
        }

        return $value;
    }

    /**
     * Return the configured deadlock retry count for page write transactions.
     *
     * @return int<1, 10>
     */
    public static function transactionAttempts(): int
    {
        $value = config('pages.transactions.attempts', 3);

        if (! is_int($value) || $value < 1 || $value > 10) {
            throw new InvalidArgumentException(
                'pages.transactions.attempts must be between 1 and 10.',
            );
        }

        return $value;
    }

    /**
     * Return one positive configured package limit.
     */
    public static function limit(string $key, int $default): int
    {
        $value = config("pages.limits.{$key}", $default);

        return is_int($value) && $value > 0 ? $value : $default;
    }

    /**
     * Return one validated package integration alias.
     */
    public static function alias(string $integration, string $default): string
    {
        $value = config("pages.integrations.{$integration}", $default);

        if (! is_string($value)
            || preg_match('/^[a-z][a-z0-9_.-]{0,99}$/D', $value) !== 1) {
            throw new InvalidArgumentException(
                "Pages integration alias [{$integration}] is invalid.",
            );
        }

        return $value;
    }

    private function __construct() {}
}
