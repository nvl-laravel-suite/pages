<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Nvl\Pages\Tests\Fixtures\Consumer\Support\SerializesPageValues;
use Nvl\Support\Ownership\ValueObjects\VerifiedOwnerSiteContext;

/**
 * Validated site and content-locale context for one public page request.
 *
 * @implements Arrayable<string, mixed>
 *
 * @api
 */
final class PageRequestContextData implements Arrayable, JsonSerializable
{
    use SerializesPageValues;

    /**
     * Create trusted public site and locale context.
     */
    public function __construct(
        public readonly string $site,
        public readonly string $locale,
        public readonly ?VerifiedOwnerSiteContext $ownerSite = null,
    ) {}
}
