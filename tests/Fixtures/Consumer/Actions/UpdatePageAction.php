<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Actions;

use Nvl\Pages\Models\Page;
use Nvl\Pages\Services\PageLifecycle;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\Mutations\UpdatePageData;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\PageActorData;

/** Consumer mapping into the complete native Page mutation. */
final readonly class UpdatePageAction
{
    public function __construct(private PageLifecycle $pages) {}

    public function execute(Page|string $page, UpdatePageData $data, PageActorData $actor): Page
    {
        return $this->pages->update($page, array_diff_key(get_object_vars($data), ['expectedRevision' => true]), $data->expectedRevision, $actor->native());
    }
}
