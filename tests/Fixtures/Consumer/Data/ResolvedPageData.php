<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Data;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\CamelCaseMapper;

/**
 * Complete headless page response assembled from package-owned boundaries.
 *
 * @api
 */
#[MapOutputName(CamelCaseMapper::class)]
final class ResolvedPageData extends Data
{
    /**
     * @param  array<string,mixed>  $content
     * @param  array<string,mixed>  $seo
     *                                    Create one complete public headless page response.
     */
    public function __construct(
        public readonly PublicPageData $page,
        public readonly array $content,
        public readonly array $seo,
        public readonly ?PageResourceData $resource,
    ) {}
}
