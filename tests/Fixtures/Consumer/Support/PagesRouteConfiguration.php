<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Support;

use InvalidArgumentException;
use Nvl\Pages\Ownership\PagesOwnership;
use Nvl\Pages\Tests\Fixtures\Consumer\Http\Middleware\ResolvePublicOwnerSite;

/**
 * Validates independently configurable public and management route groups.
 */
final class PagesRouteConfiguration
{
    /**
     * Return one validated route-group prefix.
     */
    public static function path(string $group): string
    {
        $path = config("nvl-pages.routes.{$group}.prefix", ($group === 'public' ? 'nvl/api/v1/pages' : 'nvl/api/v1/pages/_manage'));

        if (! is_string($path)) {
            throw new InvalidArgumentException("pages.routes.{$group}.prefix must be a string.");
        }

        $path = trim($path, '/');

        if ($path === ''
            || str_contains($path, '..')
            || str_contains($path, '//')
            || preg_match('#^[A-Za-z0-9][A-Za-z0-9/_-]*$#D', $path) !== 1) {
            throw new InvalidArgumentException(
                "pages.routes.{$group}.prefix must be a safe route prefix.",
            );
        }

        return $path;
    }

    /**
     * Return one validated route-name prefix.
     */
    public static function name(string $group): string
    {
        $name = config("nvl-pages.routes.{$group}.name", "nvl.pages.{$group}.");

        if (! is_string($name)) {
            throw new InvalidArgumentException("pages.routes.{$group}.name must be a string.");
        }

        $name = rtrim(trim($name), '.');

        if ($name === '' || preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]*$/D', $name) !== 1) {
            throw new InvalidArgumentException(
                "pages.routes.{$group}.name must be a safe route-name prefix.",
            );
        }

        return $name.'.';
    }

    /**
     * Return one validated non-empty route middleware list.
     *
     * @return list<string>
     */
    public static function middleware(string $group): array
    {
        $middleware = config("nvl-pages.routes.{$group}.middleware", ['api']);

        if (! is_array($middleware)) {
            throw new InvalidArgumentException(
                "pages.routes.{$group}.middleware must be an array.",
            );
        }

        foreach ($middleware as $item) {
            if (! is_string($item) || trim($item) === '') {
                throw new InvalidArgumentException(
                    "pages.routes.{$group}.middleware must contain only non-empty strings.",
                );
            }
        }

        if ($middleware === []) {
            throw new InvalidArgumentException(
                "pages.routes.{$group}.middleware must contain at least one middleware.",
            );
        }

        if ($group === 'public' && PagesOwnership::declared()) {
            array_unshift($middleware, ResolvePublicOwnerSite::class);
        }

        return array_values($middleware);
    }

    private function __construct() {}
}
