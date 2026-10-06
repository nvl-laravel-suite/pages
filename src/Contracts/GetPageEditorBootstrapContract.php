<?php

declare(strict_types=1);

namespace Nvl\Pages\Contracts;

use Nvl\Pages\Data\PageActorData;
use Nvl\Pages\Data\PageEditorBootstrapData;
use Nvl\Pages\Models\Page;

/**
 * Defines the supported get page editor bootstrap workflow.
 *
 * @api
 */
interface GetPageEditorBootstrapContract
{
    /**
     * Return the complete editor bootstrap for one Page identity and locale.
     */
    public function execute(
        string $pageId,
        string $locale,
        PageActorData $actor,
    ): PageEditorBootstrapData;
}
