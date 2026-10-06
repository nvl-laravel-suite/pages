<?php

declare(strict_types=1);

namespace Nvl\Pages\Contracts;

use Nvl\Pages\Data\PageActorData;
use Nvl\Pages\Data\PreviewPageData;

/**
 * Defines the supported preview page workflow.
 *
 * @api
 */
interface PreviewPageContract
{
    /**
     * Resolve one preview path without applying public page or content visibility.
     */
    public function execute(
        string $path,
        string $site,
        string $locale,
        PageActorData $actor,
    ): PreviewPageData;
}
