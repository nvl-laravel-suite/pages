<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Actions;

use Nvl\Content\Services\ContentReader;
use Nvl\Pages\Contracts\PageAuthorization;
use Nvl\Pages\Enums\PageAbility;
use Nvl\Pages\Enums\PageKind;
use Nvl\Pages\Enums\PageStatus;
use Nvl\Pages\Models\Page;
use Nvl\Pages\Services\PageIdentityGuard;
use Nvl\Pages\Services\PageModelRegistry;
use Nvl\Pages\Services\PageResourceRegistry;
use Nvl\Pages\Support\PagesConfiguration;
use Nvl\Pages\Tests\Fixtures\Consumer\Contracts\PageMetafields;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\PageActorData;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\PageData;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\PageEditorBootstrapData;
use Nvl\Seo\Services\SeoReader;
use Nvl\Support\Contracts\LocaleCatalog;

/**
 * Composes one authorized Page editor from package-owned read boundaries.
 *
 * Delegation to Content, SEO, and Metafields services is deliberate orchestration
 * so their authorization and projection contracts remain package-owned.
 *
 * @api
 */
final readonly class GetPageEditorBootstrapAction
{
    /**
     * Create the complete Page editor bootstrap reader.
     */
    public function __construct(
        private PageAuthorization $authorization,
        private PageIdentityGuard $identities,
        private LocaleCatalog $locales,
        private ContentReader $content,
        private SeoReader $seo,
        private PageMetafields $metafields,
        private PageResourceRegistry $resources,
    ) {}

    /**
     * Return the complete editor bootstrap for one Page identity and locale.
     */
    public function execute(
        string $pageId,
        string $locale,
        PageActorData $actor,
    ): PageEditorBootstrapData {
        $pageId = $this->identities->id($pageId);
        $locale = $this->locales->assertSupported($locale);
        $page = PageModelRegistry::query()->findOrFail($pageId);
        $this->authorization->authorize(
            PageAbility::View,
            $actor->native(),
            $page,
            ['site' => $page->site, 'path' => $page->path, 'locale' => $locale],
        );
        $page->load('translations');

        return new PageEditorBootstrapData(
            page: PageData::fromModel($page),
            content: array_values($this->content->placements($page, Page::CONTENT_GROUP, $actor->contentActor(), includeBlocks: true)->all()),
            seo: $this->seo->forOwner($actor->native(), $page, $page->site),
            metafields: $this->metafields->fields($page, $locale, $actor->native()),
            pageKinds: array_column(PageKind::cases(), 'value'),
            pageStatuses: array_column(PageStatus::cases(), 'value'),
            resourceAliases: $this->resources->aliases(),
            maximumDepth: PagesConfiguration::maximumDepth(),
        );
    }
}
