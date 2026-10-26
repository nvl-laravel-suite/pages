<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Actions;

use Nvl\Pages\Models\Page;
use Nvl\Pages\Services\PageLifecycle;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\Mutations\MovePageData;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\PageActorData;

/** Consumer mapping into the complete native Page mutation. */
final readonly class MovePageAction
{
    public function __construct(private PageLifecycle $pages) {}

    public function execute(Page|string $page, MovePageData $data, PageActorData $actor): Page
    {
        return $this->pages->move($page, $data->parentId, $data->position, $data->expectedRevision, $actor->native());
    }
}
