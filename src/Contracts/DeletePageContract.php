<?php

declare(strict_types=1);

namespace Nvl\Pages\Contracts;

use Nvl\Pages\Data\Mutations\DeletePageData;
use Nvl\Pages\Data\PageActorData;
use Nvl\Pages\Models\Page;

/**
 * Defines the supported delete page workflow.
 *
 * @api
 */
interface DeletePageContract
{
    /**
     * Soft-delete one childless page after exact revision validation.
     */
    public function execute(
        Page|string $page,
        DeletePageData $data,
        PageActorData $actor,
    ): bool;
}
