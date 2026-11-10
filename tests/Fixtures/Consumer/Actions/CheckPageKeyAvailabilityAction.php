<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Actions;

use Nvl\Pages\Services\PageReader;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\PageActorData;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\PageKeyAvailabilityData;

final readonly class CheckPageKeyAvailabilityAction
{
    public function __construct(private PageReader $pages) {}

    public function execute(
        string $site,
        string $key,
        PageActorData $actor,
        ?string $exceptId = null,
    ): PageKeyAvailabilityData {
        return PageKeyAvailabilityData::fromRow($this->pages->keyAvailable($site, $key, $actor->native(), $exceptId));
    }
}
