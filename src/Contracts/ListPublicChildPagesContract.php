<?php

declare(strict_types=1);

namespace Nvl\Pages\Contracts;

use Illuminate\Support\Collection;
use Nvl\Pages\Data\PageRequestContextData;
use Nvl\Pages\Data\PublicPageData;
use Nvl\Pages\Enums\PageKind;
use Nvl\Pages\Enums\PublicChildPageOrder;

/**
 * Defines the supported list public child pages workflow.
 *
 * @api
 */
interface ListPublicChildPagesContract
{
    /**
     * Return public children in the requested deterministic order.
     *
     * @return Collection<int, PublicPageData>
     */
    public function execute(
        string $parentId,
        PageRequestContextData $context,
        int $limit = 50,
        ?PageKind $kind = null,
        PublicChildPageOrder $order = PublicChildPageOrder::Sibling,
    ): Collection;
}
