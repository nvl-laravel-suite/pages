<?php

declare(strict_types=1);

namespace Nvl\Pages\Data;

use Nvl\Data\Traits\DataTransform;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\CamelCaseMapper;

/**
 * Public localized navigation tree for one site.
 *
 * @api
 */
#[MapOutputName(CamelCaseMapper::class)]
final class NavigationData extends Data
{
    use DataTransform;

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
}
