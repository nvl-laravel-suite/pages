<?php

declare(strict_types=1);

namespace Nvl\Pages\Contracts;

use Nvl\Pages\Data\PageActorData;
use Nvl\Pages\Data\PageData;
use Nvl\Pages\Models\Page;

/**
 * Defines the supported get page workflow.
 *
 * @api
 */
interface GetPageContract
{
    /**
     * Return one complete authorized management page.
     */
    public function execute(Page|string $page, PageActorData $actor): PageData;
}
