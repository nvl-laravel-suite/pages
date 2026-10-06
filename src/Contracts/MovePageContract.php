<?php

declare(strict_types=1);

namespace Nvl\Pages\Contracts;

use Nvl\Pages\Data\Mutations\MovePageData;
use Nvl\Pages\Data\PageActorData;
use Nvl\Pages\Models\Page;

/**
 * Defines the supported move page workflow.
 *
 * @api
 */
interface MovePageContract
{
    /**
     * Reparent and reorder one page while serializing its complete site tree.
     */
    public function execute(
        Page|string $page,
        MovePageData $data,
        PageActorData $actor,
    ): Page;
}
