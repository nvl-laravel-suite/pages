<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Data;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\CamelCaseMapper;

/**
 * One localized public navigation node and its visible children.
 *
 * @api
 */
#[MapOutputName(CamelCaseMapper::class)]
final class NavigationItemData extends Data
{
    /**
     * Create one localized navigation node.
     *
     * @param  list<NavigationItemData>  $children
     */
    public function __construct(
        public readonly string $id,
        public readonly string $key,
        public readonly string $path,
        public readonly string $url,
        public readonly string $label,
        public readonly array $children,
    ) {}
}
