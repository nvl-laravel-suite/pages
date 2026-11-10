<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Nvl\Pages\Tests\Fixtures\Consumer\Support\SerializesPageValues;

/**
 * Validated context supplied to a registered dynamic resource handler.
 *
 * @implements Arrayable<string, mixed>
 *
 * @api
 */
final class PageResourceRequestData implements Arrayable, JsonSerializable
{
    use SerializesPageValues;

    /**
     * Create validated dynamic resource request context.
     *
     * @param  array<string, string>  $parameters
     */
    public function __construct(
        public readonly string $pageId,
        public readonly string $site,
        public readonly string $locale,
        public readonly array $parameters,
    ) {}
}
