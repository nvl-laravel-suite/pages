<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Actions;

use InvalidArgumentException;
use Nvl\Pages\Enums\PageKind;
use Nvl\Pages\Services\PageReader;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\PageActorData;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\ResolvedPageData;

final readonly class GetPagePublicationProjectionAction
{
    public function __construct(private PageReader $pages, private ResolvePageAction $resolve) {}

    public function execute(string $pageId, string $locale, PageActorData $actor): ResolvedPageData
    {
        $page = $this->pages->get($pageId, $actor->native());
        $page->newQuery()->publiclyVisible()->findOrFail($page->id);
        if ($page->kind !== PageKind::Static) {
            throw new InvalidArgumentException('ID-based publication projections support static Pages only.');
        }

        return $this->resolve->execute($page->path, $page->site, $locale, $actor);
    }
}
