<?php

declare(strict_types=1);

namespace Nvl\Pages\Contracts;

use Nvl\Content\Content;
use Nvl\Pages\Data\PageActorData;
use Nvl\Pages\Data\ResolvedPageData;

/**
 * Defines the supported resolve page workflow.
 *
 * @api
 */
interface ResolvePageContract
{
    /**
     * Resolve one public page and its canonical Content group.
     */
    public function execute(
        string $path,
        string $site,
        string $locale,
        PageActorData $actor,
    ): ResolvedPageData;
}
