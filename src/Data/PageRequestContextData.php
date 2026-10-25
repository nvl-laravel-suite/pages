<?php

declare(strict_types=1);

namespace Nvl\Pages\Data;

use Nvl\Support\Ownership\ValueObjects\VerifiedOwnerSiteContext;
use Nvl\Support\Tenancy\ValueObjects\TenantSiteContext;
use Spatie\LaravelData\Data;

/**
 * Validated site and content-locale context for one public page request.
 *
 * @api
 */
final class PageRequestContextData extends Data
{
    /**
     * Create trusted public site and locale context.
     */
    public function __construct(
        public readonly string $site,
        public readonly string $locale,
        public readonly ?TenantSiteContext $tenantSite = null,
        public readonly ?VerifiedOwnerSiteContext $ownerSite = null,
    ) {}
}
