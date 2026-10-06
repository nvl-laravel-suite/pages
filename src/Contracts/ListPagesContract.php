<?php

declare(strict_types=1);

namespace Nvl\Pages\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Nvl\Filterable\Data\FilterSet;
use Nvl\Pages\Data\PageActorData;
use Nvl\Pages\Data\PageData;

/**
 * Defines the supported list pages workflow.
 *
 * @api
 */
interface ListPagesContract
{
    /**
     * Return one authorized site-scoped management paginator.
     *
     * @return LengthAwarePaginator<int, PageData>
     */
    public function execute(
        FilterSet $filters,
        string $site,
        PageActorData $actor,
        int $perPage = 25,
    ): LengthAwarePaginator;
}
