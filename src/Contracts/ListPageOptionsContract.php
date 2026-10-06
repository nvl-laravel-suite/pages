<?php

declare(strict_types=1);

namespace Nvl\Pages\Contracts;

use Illuminate\Support\Collection;
use Nvl\Pages\Data\PageActorData;
use Nvl\Pages\Data\PageOptionData;
use Nvl\Pages\Models\Page;

/**
 * Defines the supported list page options workflow.
 *
 * @api
 */
interface ListPageOptionsContract
{
    /**
     * Return deterministic minimal Page projections for one site.
     *
     * @return Collection<int, PageOptionData>
     */
    public function execute(
        string $site,
        string $locale,
        PageActorData $actor,
        ?string $search = null,
        int $limit = 50,
    ): Collection;
}
