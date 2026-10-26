<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Actions;

use Nvl\Pages\Models\Page;
use Nvl\Pages\Services\PageReader;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\PageActorData;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\PageData;

final readonly class GetPageAction
{
    public function __construct(private PageReader $pages) {}

    public function execute(Page|string $page, PageActorData $actor): PageData
    {
        return PageData::fromModel($this->pages->get($page, $actor->native()));
    }
}
