<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Nvl\Pages\Models\Page;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\PageMetafieldFieldData;
use Nvl\Support\Enums\SystemActor;

/**
 * Provides optional metafield display projections through a Pages-owned boundary.
 *
 * @api
 */
interface PageMetafields
{
    /**
     * Return authorized metafields for the requested Page and content locale.
     *
     * @return list<PageMetafieldFieldData>
     */
    public function fields(Page $page, string $locale, Authenticatable|SystemActor $actor): array;
}
