<?php

declare(strict_types=1);

namespace Nvl\Pages\Contracts;

use Nvl\Pages\Data\Mutations\CreatePageData;
use Nvl\Pages\Data\PageActorData;
use Nvl\Pages\Models\Page;

/**
 * Defines the supported create page workflow.
 *
 * @api
 */
interface CreatePageContract
{
    /**
     * Create one page and its localized copy in a serialized site-tree transaction.
     */
    public function execute(CreatePageData $data, PageActorData $actor): Page;
}
