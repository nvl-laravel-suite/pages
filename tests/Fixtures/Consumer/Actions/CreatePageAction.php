<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Actions;

use Nvl\Pages\Models\Page;
use Nvl\Pages\Services\PageLifecycle;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\Mutations\CreatePageData;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\PageActorData;

/** Consumer mapping into the complete native Page mutation. */
final readonly class CreatePageAction
{
    public function __construct(private PageLifecycle $pages) {}

    public function execute(CreatePageData $data, PageActorData $actor): Page
    {
        return $this->pages->create(get_object_vars($data), $actor->native());
    }
}
