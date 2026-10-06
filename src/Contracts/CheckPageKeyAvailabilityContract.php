<?php

declare(strict_types=1);

namespace Nvl\Pages\Contracts;

use Nvl\Pages\Data\PageActorData;
use Nvl\Pages\Data\PageKeyAvailabilityData;

/**
 * Defines the supported check page key availability workflow.
 *
 * @api
 */
interface CheckPageKeyAvailabilityContract
{
    /**
     * Return key availability and only disclose same-site conflict identity.
     */
    public function execute(
        string $site,
        string $key,
        PageActorData $actor,
        ?string $exceptId = null,
    ): PageKeyAvailabilityData;
}
