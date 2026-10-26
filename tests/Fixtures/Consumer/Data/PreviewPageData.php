<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Data;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\CamelCaseMapper;

/**
 * Authorized management preview containing page state and rendered composition.
 *
 * @api
 */
#[MapOutputName(CamelCaseMapper::class)]
final class PreviewPageData extends Data
{
    /**
     * @param  array<string,mixed>  $content
     * @param  array<string,mixed>  $seo
     *                                    Create one complete authorized page preview.
     */
    public function __construct(
        public readonly PageData $page,
        public readonly array $content,
        public readonly array $seo,
        public readonly ?PageResourceData $resource,
    ) {}
}
