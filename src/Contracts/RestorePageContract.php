<?php

declare(strict_types=1);

namespace Nvl\Pages\Contracts;

use Nvl\Pages\Data\Mutations\RestorePageData;
use Nvl\Pages\Data\PageActorData;
use Nvl\Pages\Models\Page;

/**
 * Defines the supported restore page workflow.
 *
 * @api
 */
interface RestorePageContract
{
    /**
     * Restore one deleted page while serializing its site tree.
     */
    public function execute(
        Page|string $page,
        RestorePageData $data,
        PageActorData $actor,
    ): Page;
}
