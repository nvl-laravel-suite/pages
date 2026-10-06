<?php

declare(strict_types=1);

namespace Nvl\Pages\Contracts;

use Nvl\Pages\Data\PageActorData;
use Nvl\Pages\Data\ResolvedPageData;
use Nvl\Pages\Models\Page;

/**
 * Defines the supported get page publication projection workflow.
 *
 * @api
 */
interface GetPagePublicationProjectionContract
{
    /**
     * Return one complete public projection for a visible static Page.
     */
    public function execute(
        string $pageId,
        string $locale,
        PageActorData $actor,
    ): ResolvedPageData;
}
