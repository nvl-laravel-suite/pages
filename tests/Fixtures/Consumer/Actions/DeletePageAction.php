<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Actions;

use Nvl\Pages\Models\Page;
use Nvl\Pages\Services\PageLifecycle;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\Mutations\DeletePageData;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\PageActorData;

/** Consumer mapping into the complete native Page mutation. */
final readonly class DeletePageAction
{
    public function __construct(private PageLifecycle $pages) {}

    public function execute(Page|string $page, DeletePageData $data, PageActorData $actor): bool
    {
        return $this->pages->delete($page, $data->expectedRevision, $actor->native());
    }
}
