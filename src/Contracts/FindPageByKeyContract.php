<?php

declare(strict_types=1);

namespace Nvl\Pages\Contracts;

use Nvl\Pages\Data\PageActorData;
use Nvl\Pages\Data\PageData;
use Nvl\Pages\Models\Page;

/**
 * Defines the supported find page by key workflow.
 *
 * @api
 */
interface FindPageByKeyContract
{
    /**
     * Return one authorized management Page projection by exact key.
     */
    public function execute(
        string $site,
        string $key,
        PageActorData $actor,
    ): PageData;
}
