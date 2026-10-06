<?php

declare(strict_types=1);

namespace Nvl\Pages\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Nvl\Pages\Data\PageActorData;
use Nvl\Pages\Data\PageEditorSummaryData;
use Nvl\Pages\Models\Page;

/**
 * Defines the supported list page editor summaries workflow.
 *
 * @api
 */
interface ListPageEditorSummariesContract
{
    /**
     * Return one stable site-scoped Page editor paginator.
     *
     * @return LengthAwarePaginator<int, PageEditorSummaryData>
     */
    public function execute(
        string $site,
        string $locale,
        PageActorData $actor,
        int $perPage = 25,
    ): LengthAwarePaginator;
}
