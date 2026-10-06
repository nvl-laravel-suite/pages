<?php

declare(strict_types=1);

namespace Nvl\Pages\Contracts;

use Nvl\Pages\Data\Mutations\UpdatePageData;
use Nvl\Pages\Data\PageActorData;
use Nvl\Pages\Models\Page;

/**
 * Defines the supported update page workflow.
 *
 * @api
 */
interface UpdatePageContract
{
    /**
     * Replace editable page state after exact revision and lifecycle authorization checks.
     */
    public function execute(
        Page|string $page,
        UpdatePageData $data,
        PageActorData $actor,
    ): Page;
}
