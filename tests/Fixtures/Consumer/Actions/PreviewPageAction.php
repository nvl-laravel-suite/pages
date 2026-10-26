<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Actions;

use Nvl\Content\Services\ContentRenderer;
use Nvl\Pages\Models\Page;
use Nvl\Pages\Services\PageResolver;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\PageActorData;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\PageData;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\PageResourceData;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\PreviewPageData;
use Nvl\Seo\Services\SeoReader;

/** Application-owned composition calls the real complete package services. */
final readonly class PreviewPageAction
{
    public function __construct(private PageResolver $pages,
        private ContentRenderer $content, private SeoReader $seo) {}

    public function execute(string $path, string $site, string $locale, PageActorData $actor): PreviewPageData
    {
        $match = $this->pages->preview($path, $site, $locale, $actor->previewActor());

        return new PreviewPageData(page: PageData::fromModel($match['page']),
            content: $this->content->render($match['page'], ['group' => Page::CONTENT_GROUP, 'locale' => $locale, 'publicOnly' => false, 'actor' => $actor->native()]),
            seo: $this->seo->resolve($actor->native(), $match['page'], $locale, $site),
            resource: $match['resource'] === null ? null : new PageResourceData(...$match['resource']));
    }
}
