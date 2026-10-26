<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Actions;

use Illuminate\Support\Collection;
use Nvl\Pages\Contracts\PageUrlGenerator;
use Nvl\Pages\Enums\PageKind;
use Nvl\Pages\Enums\PublicChildPageOrder;
use Nvl\Pages\Models\Page;
use Nvl\Pages\Services\PageReader;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\PageRequestContextData;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\PublicPageData;

final readonly class ListPublicChildPagesAction
{
    public function __construct(private PageReader $pages, private PageUrlGenerator $urls) {}

    /** @return Collection<int,PublicPageData> */
    public function execute(
        string $parentId,
        PageRequestContextData $context,
        int $limit = 50,
        ?PageKind $kind = null,
        PublicChildPageOrder $order = PublicChildPageOrder::Sibling,
    ): Collection {
        return $this->pages->children($parentId, $context->site, $context->locale, $limit, $kind, $order)->map(fn (Page $page): PublicPageData => PublicPageData::fromModel($page, $context->locale, $this->urls));
    }
}
