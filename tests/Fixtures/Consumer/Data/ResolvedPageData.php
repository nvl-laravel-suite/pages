<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Nvl\Pages\Tests\Fixtures\Consumer\Support\SerializesPageValues;

/**
 * Complete headless page response assembled from package-owned boundaries.
 *
 * @implements Arrayable<string, mixed>
 *
 * @api
 */
final class ResolvedPageData implements Arrayable, JsonSerializable
{
    use SerializesPageValues;

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
