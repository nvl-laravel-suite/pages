<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Actions;

use Nvl\Content\Services\ContentRenderer;
use Nvl\Pages\Contracts\PageUrlGenerator;
use Nvl\Pages\Models\Page;
use Nvl\Pages\Services\PageResolver;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\PageActorData;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\PageResourceData;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\PublicPageData;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\ResolvedPageData;
use Nvl\Seo\Services\SeoReader;
use Nvl\Support\Enums\PublicActor;

/** Application-owned composition calls the real complete package services. */
final readonly class ResolvePageAction
{
    public function __construct(private PageResolver $pages, private PageUrlGenerator $urls,
        private ContentRenderer $content, private SeoReader $seo) {}

    public function execute(string $path, string $site, string $locale, PageActorData $actor): ResolvedPageData
    {
        $match = $this->pages->resolve($path, $site, $locale);

        return new ResolvedPageData(page: PublicPageData::fromModel($match['page'], $locale, $this->urls),
            content: $this->content->render($match['page'], ['group' => Page::CONTENT_GROUP, 'locale' => $locale, 'publicOnly' => true, 'actor' => PublicActor::Anonymous]),
            seo: $this->seo->resolve(PublicActor::Anonymous, $match['page'], $locale, $site),
            resource: $match['resource'] === null ? null : new PageResourceData(...$match['resource']));
    }
}
