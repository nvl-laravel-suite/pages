<?php

declare(strict_types=1);

namespace Nvl\Pages\Support;

use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Stores page lifecycle instants as UTC seconds independently of the host timezone.
 *
 * A local wall clock is ambiguous inside a daylight-saving overlap, so storing one
 * would lose the supplied instant. A string without an offset is read in the host
 * timezone, matching Laravel's own date parsing.
 *
 * @implements CastsAttributes<Carbon, DateTimeInterface|string>
 *
 * @internal
 */
final class PageInstantCast implements CastsAttributes
{
    /** @param array<string, mixed> $attributes */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Carbon
    {
        if ($value === null) {
            return null;
        }
        if (! is_string($value)) {
            throw new InvalidArgumentException('Page lifecycle timestamps require UTC strings.');
        }

        return Carbon::parse($value, 'UTC');
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, string|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$key => null];
        }
        $date = $value instanceof DateTimeInterface ? Carbon::instance($value) : Carbon::parse($value);

        return [$key => $date->utc()->format('Y-m-d H:i:s')];
    }
}
