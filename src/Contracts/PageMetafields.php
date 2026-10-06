<?php

declare(strict_types=1);

namespace Nvl\Pages\Contracts;

use Nvl\Pages\Data\PageMetafieldFieldData;
use Nvl\Pages\Models\Page;

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
    public function fields(Page $page, string $locale): array;
}
