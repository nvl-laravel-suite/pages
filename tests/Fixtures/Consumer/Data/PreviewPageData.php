<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Nvl\Pages\Tests\Fixtures\Consumer\Support\SerializesPageValues;

/**
 * Authorized management preview containing page state and rendered composition.
 *
 * @implements Arrayable<string, mixed>
 *
 * @api
 */
final class PreviewPageData implements Arrayable, JsonSerializable
{
    use SerializesPageValues;

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
