<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Actions;

use Nvl\Pages\Services\PageReader;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\NavigationData;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\PageActorData;

final readonly class GetNavigationAction
{
    public function __construct(private PageReader $pages) {}

    public function execute(
        string $site,
        string $locale,
        PageActorData $actor,
    ): NavigationData {
        $result = $this->pages->navigation($site, $locale);

        return NavigationData::fromRow($result);
    }
}
