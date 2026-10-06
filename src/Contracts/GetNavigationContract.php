<?php

declare(strict_types=1);

namespace Nvl\Pages\Contracts;

use Nvl\Pages\Data\NavigationData;
use Nvl\Pages\Data\PageActorData;

/**
 * Defines the supported get navigation workflow.
 *
 * @api
 */
interface GetNavigationContract
{
    /**
     * Return visible static navigation for one site and content locale.
     */
    public function execute(
        string $site,
        string $locale,
        PageActorData $actor,
    ): NavigationData;
}
