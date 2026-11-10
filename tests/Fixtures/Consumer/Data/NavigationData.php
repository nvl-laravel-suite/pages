<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Nvl\Pages\Tests\Fixtures\Consumer\Support\SerializesPageValues;

/**
 * Public localized navigation tree for one site.
 *
 * @implements Arrayable<string, mixed>
 *
 * @api
 */
final class NavigationData implements Arrayable, JsonSerializable
{
    use SerializesPageValues;

    /**
     * Create one localized navigation tree.
     *
     * @param  list<NavigationItemData>  $items
     */
    public function __construct(
        public readonly string $site,
        public readonly string $locale,
        public readonly array $items,
    ) {}

    /** @param array{site: string, locale: string, items: list<array<string, mixed>>} $row */
    public static function fromRow(array $row): self
    {
        return new self($row['site'], $row['locale'], array_map(NavigationItemData::fromRow(...), $row['items']));
    }
}
