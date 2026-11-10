<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Events\MigrationStarted;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Nvl\Content\Models\ContentBlock;
use Nvl\Content\Schema\ContentDefinitionSource;
use Nvl\Content\Services\ContentBlocks;
use Nvl\Content\Services\ContentDefinitionRegistry;
use Nvl\Content\Services\ContentDefinitions;
use Nvl\Content\Services\ContentPlacements as PlaceContentBlockAction;
use Nvl\Metafields\Contracts\MetafieldAuthorization;
use Nvl\Metafields\Enums\MetafieldAbility;
use Nvl\Metafields\Models\MetafieldDefinition;
use Nvl\Pages\Contracts\PageAuthorization;
use Nvl\Pages\Enums\PageAbility;
use Nvl\Pages\Enums\PageKind;
use Nvl\Pages\Enums\PageStatus;
use Nvl\Pages\Enums\PublicChildPageOrder;
use Nvl\Pages\Exceptions\PageConflictException;
use Nvl\Pages\Exceptions\PageHierarchyException;
use Nvl\Pages\Exceptions\StalePageException;
use Nvl\Pages\Models\Page;
use Nvl\Pages\Seo\PageSitemapSource;
use Nvl\Pages\Services\ConfiguredPageAuthorization;
use Nvl\Pages\Services\PageHierarchy;
use Nvl\Pages\Services\PageLifecycle;
use Nvl\Pages\Services\PageReader;
use Nvl\Pages\Services\PageResolver;
use Nvl\Pages\Services\PageTreeLock;
use Nvl\Pages\Tests\Fixtures\Consumer\Actions\CheckPageKeyAvailabilityAction;
use Nvl\Pages\Tests\Fixtures\Consumer\Actions\CreatePageAction;
use Nvl\Pages\Tests\Fixtures\Consumer\Actions\DeletePageAction;
use Nvl\Pages\Tests\Fixtures\Consumer\Actions\FindPageByKeyAction;
use Nvl\Pages\Tests\Fixtures\Consumer\Actions\GetNavigationAction;
use Nvl\Pages\Tests\Fixtures\Consumer\Actions\GetPageAction;
use Nvl\Pages\Tests\Fixtures\Consumer\Actions\GetPagePublicationProjectionAction;
use Nvl\Pages\Tests\Fixtures\Consumer\Actions\ListPageOptionsAction;
use Nvl\Pages\Tests\Fixtures\Consumer\Actions\ListPagesAction;
use Nvl\Pages\Tests\Fixtures\Consumer\Actions\ListPublicChildPagesAction;
use Nvl\Pages\Tests\Fixtures\Consumer\Actions\MovePageAction;
use Nvl\Pages\Tests\Fixtures\Consumer\Actions\PreviewPageAction;
use Nvl\Pages\Tests\Fixtures\Consumer\Actions\ResolvePageAction;
use Nvl\Pages\Tests\Fixtures\Consumer\Actions\RestorePageAction;
use Nvl\Pages\Tests\Fixtures\Consumer\Actions\SyncSeoProfileAction;
use Nvl\Pages\Tests\Fixtures\Consumer\Actions\UpdatePageAction;
use Nvl\Pages\Tests\Fixtures\Consumer\Contracts\PageRequestContextResolver;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\Mutations\CreatePageData;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\Mutations\DeletePageData;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\Mutations\MovePageData;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\Mutations\RestorePageData;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\Mutations\UpdatePageData;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\PageActorData;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\PageData;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\PageKeyAvailabilityData;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\PageOptionData;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\PageRequestContextData;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\PageResourceData;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\PublicPageData;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\Queries\PageIndexQueryData;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\ResolvedPageData;
use Nvl\Pages\Tests\Fixtures\Consumer\PreviewActor;
use Nvl\Pages\Tests\Fixtures\RecordingPageAuthorization;
use Nvl\Pages\Tests\Fixtures\TestPageResource;
use Nvl\Seo\Contracts\SeoAuthorization;
use Nvl\Seo\Enums\SitemapChangeFrequency;
use Nvl\Seo\Services\SitemapGenerator;
use Nvl\Seo\Support\SeoAuthorizationContext;
use Nvl\Support\Enums\PublicActor;
use Nvl\Support\Enums\SystemActor;
use Nvl\Translatable\Exceptions\InvalidLocaleException;
use Nvl\Translatable\Services\TranslationResourceRegistry;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

function createTestPage(
    string $key,
    string $slug,
    ?string $parentId = null,
    PageKind $kind = PageKind::Static,
    ?string $resource = null,
): Page {
    return app(CreatePageAction::class)->execute(
        new CreatePageData(
            key: $key,
            slug: $slug,
            parentId: $parentId,
            kind: $kind,
            resource: $resource,
            status: PageStatus::Published,
            translations: [
                'en' => [
                    'title' => ucfirst($slug),
                    'navigationLabel' => ucfirst($slug),
                ],
            ],
        ),
        PageActorData::system(),
    );
}

function allowPageEditorPackageReads(): void
{
    app()->instance(SeoAuthorization::class, new class implements SeoAuthorization
    {
        public function authorize(SeoAuthorizationContext $context): void {}
    });

    if (! interface_exists(MetafieldAuthorization::class)) {
        return;
    }

    app()->instance(MetafieldAuthorization::class, new class implements MetafieldAuthorization
    {
        public function authorizeDefinition(
            Authenticatable|SystemActor $actor,
            MetafieldAbility $ability,
            ?MetafieldDefinition $definition = null,
        ): void {}

        public function authorizeOwner(
            Authenticatable|SystemActor $actor,
            MetafieldAbility $ability,
            ?Model $owner = null,
            ?MetafieldDefinition $definition = null,
        ): void {}
    });
}

function createPageTestContentBlock(string $key): ContentBlock
{
    config()->set([
        'nvl-content.locales.available' => ['en', 'bg'],
        'nvl-content.locales.required_on_publish' => ['en'],
    ]);
    app(ContentDefinitionRegistry::class)->register(new ContentDefinitionSource(
        key: 'website.section',
        name: 'Website section',
        description: null,
        category: 'website',
        version: 1,
        view: null,
        schema: [
            'fields' => [[
                'key' => 'title',
                'type' => 'text',
                'label' => 'Title',
                'localized' => true,
                'required' => true,
            ]],
        ],
        allowedScopes: ['global'],
        allowedRegions: ['main'],
    ));
    app(ContentDefinitions::class)->sync(SystemActor::Background);
    $blocks = app(ContentBlocks::class);
    $block = $blocks->create(['definition' => 'website.section', 'key' => $key,
        'translations' => ['en' => ['title' => 'Editor section']]], SystemActor::Background);

    return $blocks->publish($block, $block->revision, SystemActor::Background);
}

/** @return array<int,array<string,mixed>> */
function pageDataQueries(): array
{
    return array_values(array_filter(DB::getQueryLog(), static fn (array $query): bool => preg_match('/(?:from|join) ["`]?nvl_pages(?:_|["`])/', $query['query']) === 1));
}

it('installs standalone with disabled routes and healthy diagnostics', function (): void {
    expect(Route::has('nvl.pages.public.resolve'))->toBeFalse()
        ->and(Route::has('nvl.pages.public.navigation'))->toBeFalse()
        ->and(Route::has('nvl.pages.management.index'))->toBeFalse()
        ->and(Route::has('nvl.pages.management.preview'))->toBeFalse()
        ->and(Route::has('nvl.pages.management.restore'))->toBeFalse()
        ->and(app(TranslationResourceRegistry::class)->keys())->toContain('pages.pages');

    $exit = Artisan::call('nvl:pages:doctor', [
        '--strict' => true,
        '--format' => 'json',
    ]);
    $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($exit)->toBe(0)
        ->and($report['healthy'])->toBeTrue()
        ->and($report['resources'])->toBe(['records.detail']);
});

it('resolves a published static page with content and seo projections', function (): void {
    $page = createTestPage('pages.about', 'about');
    $resolved = app(ResolvePageAction::class)->execute(
        'about',
        'default',
        'en',
        PageActorData::anonymous(),
    );

    expect($resolved->page->id)->toBe($page->id)
        ->and($resolved->page->title)->toBe('About')
        ->and($resolved->page->titleLocale)->toBe('en')
        ->and($resolved->page->url)->toBe('https://pages.test/about')
        ->and($resolved->content['ownerId'])->toBe($page->id)
        ->and($resolved->content['blocks'])->toBe([])
        ->and($resolved->seo['locale'])->toBe('en')
        ->and($resolved->resource)->toBeNull();
});

it('returns localized nested navigation without exposing management state', function (): void {
    $parent = createTestPage('pages.navigation', 'navigation');
    $child = createTestPage('pages.navigation-child', 'child', $parent->id);
    $navigation = app(GetNavigationAction::class)->execute(
        'default',
        'en',
        PageActorData::anonymous(),
    );

    expect($navigation->items)->toHaveCount(1)
        ->and($navigation->items[0]->id)->toBe($parent->id)
        ->and($navigation->items[0]->label)->toBe('Navigation')
        ->and($navigation->items[0]->children)->toHaveCount(1)
        ->and($navigation->items[0]->children[0]->id)->toBe($child->id);
});

it('previews drafts and restores deleted pages through explicit capabilities', function (): void {
    $draft = app(CreatePageAction::class)->execute(
        new CreatePageData(
            key: 'pages.draft',
            slug: 'draft',
            translations: ['en' => ['title' => 'Draft']],
        ),
        PageActorData::system(),
    );
    $preview = app(PreviewPageAction::class)->execute(
        'draft',
        'default',
        'en',
        PageActorData::system(),
    );

    expect($preview->page->id)->toBe($draft->id)
        ->and($preview->page->status)->toBe(PageStatus::Draft);

    $deleted = app(DeletePageAction::class)->execute(
        $draft,
        new DeletePageData($draft->revision),
        PageActorData::system(),
    );
    $restored = app(RestorePageAction::class)->execute(
        $draft->id,
        new RestorePageData($draft->revision),
        PageActorData::system(),
    );

    expect($deleted)->toBeTrue()
        ->and($restored->trashed())->toBeFalse()
        ->and($restored->revision)->toBe($draft->revision + 1);
});

it('derives public site context from configuration and validates the locale', function (): void {
    $request = Request::create('/pages/about', 'GET', [
        'site' => 'untrusted',
        'locale' => 'bg',
    ]);
    $context = app(PageRequestContextResolver::class)->resolve($request);

    expect($context->site)->toBe('default')
        ->and($context->locale)->toBe('bg');
});

it('enforces the four-level hierarchy and rebuilds descendant paths', function (): void {
    $one = createTestPage('pages.one', 'one');
    $two = createTestPage('pages.two', 'two', $one->id);
    $three = createTestPage('pages.three', 'three', $two->id);
    $four = createTestPage('pages.four', 'four', $three->id);

    expect(fn (): Page => createTestPage('pages.five', 'five', $four->id))
        ->toThrow(PageHierarchyException::class);

    $moved = app(MovePageAction::class)->execute(
        $two,
        new MovePageData(null, 5, $two->revision),
        PageActorData::system(),
    );

    expect($moved->path)->toBe('two')
        ->and($three->refresh()->path)->toBe('two/three')
        ->and($four->refresh()->path)->toBe('two/three/four');
});

it('serializes tree mutations and authorizes only real lifecycle transitions', function (): void {
    $authorization = new RecordingPageAuthorization;
    app()->instance(PageAuthorization::class, $authorization);

    $page = createTestPage('pages.lifecycle', 'lifecycle');

    expect($authorization->abilities)->toBe([
        PageAbility::Create,
        PageAbility::Publish,
    ])->and(
        DB::table((string) config('nvl-pages.tables.tree_locks'))
            ->where('site', 'default')
            ->count(),
    )->toBe(1);

    $authorization->abilities = [];
    $updated = app(UpdatePageAction::class)->execute(
        $page,
        new UpdatePageData(
            slug: 'lifecycle',
            kind: PageKind::Static,
            resource: null,
            status: PageStatus::Published,
            expectedRevision: $page->revision,
            translations: ['en' => ['title' => 'Lifecycle updated']],
        ),
        PageActorData::system(),
    );

    expect($authorization->abilities)->toBe([PageAbility::Update]);

    $authorization->abilities = [];
    app(UpdatePageAction::class)->execute(
        $updated,
        new UpdatePageData(
            slug: 'lifecycle',
            kind: PageKind::Static,
            resource: null,
            status: PageStatus::Archived,
            expectedRevision: $updated->revision,
            translations: ['en' => ['title' => 'Lifecycle archived']],
        ),
        PageActorData::system(),
    );

    expect($authorization->abilities)->toBe([
        PageAbility::Update,
        PageAbility::Archive,
    ]);
});

it('rewrites descendant paths only when the canonical parent path changes', function (): void {
    $parent = createTestPage('pages.parent', 'parent');
    $child = createTestPage('pages.child', 'child', $parent->id);
    $childRevision = $child->revision;

    $parent = app(UpdatePageAction::class)->execute(
        $parent,
        new UpdatePageData(
            slug: 'parent',
            kind: PageKind::Static,
            resource: null,
            status: PageStatus::Published,
            expectedRevision: $parent->revision,
            translations: ['en' => ['title' => 'Renamed copy']],
        ),
        PageActorData::system(),
    );

    expect($child->refresh()->revision)->toBe($childRevision);

    app(UpdatePageAction::class)->execute(
        $parent,
        new UpdatePageData(
            slug: 'renamed-parent',
            kind: PageKind::Static,
            resource: null,
            status: PageStatus::Published,
            expectedRevision: $parent->revision,
            translations: ['en' => ['title' => 'Renamed path']],
        ),
        PageActorData::system(),
    );

    expect($child->refresh()->path)->toBe('renamed-parent/child')
        ->and($child->revision)->toBe($childRevision + 1);
});

it('restores a child using its current parent path after a rename', function (): void {
    $parent = createTestPage('pages.restore-parent', 'parent');
    $child = createTestPage('pages.restore-child', 'child', $parent->id);
    app(DeletePageAction::class)->execute($child, new DeletePageData($child->revision), PageActorData::system());
    app(UpdatePageAction::class)->execute(
        $parent,
        new UpdatePageData(
            slug: 'renamed',
            kind: PageKind::Static,
            resource: null,
            status: PageStatus::Published,
            expectedRevision: $parent->revision,
            translations: ['en' => ['title' => 'Renamed']],
        ),
        PageActorData::system(),
    );
    $deleted = Page::withTrashed()->findOrFail($child->id);
    $restored = app(RestorePageAction::class)->execute(
        $deleted,
        new RestorePageData($deleted->revision),
        PageActorData::system(),
    );

    expect($restored->path)->toBe('renamed/child')
        ->and(app(ResolvePageAction::class)->execute(
            'renamed/child', 'default', 'en', PageActorData::anonymous(),
        )->page->id)->toBe($child->id);
});

it('authorizes the destination parent before moving a page', function (): void {
    $page = createTestPage('pages.move-source', 'source');
    $destination = createTestPage('pages.move-forbidden', 'forbidden');
    app()->instance(PageAuthorization::class, new class($destination->id) implements PageAuthorization
    {
        public function __construct(private readonly string $forbiddenParent) {}

        public function authorize(
            PageAbility $ability,
            Authenticatable|SystemActor|PublicActor $actor,
            ?Page $page = null,
            array $context = [],
        ): void {
            if ($ability === PageAbility::Move && ($context['parentId'] ?? null) === $this->forbiddenParent) {
                throw new AuthorizationException('The destination parent is forbidden.');
            }
        }
    });

    expect(fn () => app(MovePageAction::class)->execute(
        $page,
        new MovePageData(parentId: $destination->id, position: 0, expectedRevision: $page->revision),
        PageActorData::anonymous(),
    ))->toThrow(AuthorizationException::class);
    expect($page->refresh()->parent_id)->toBeNull()
        ->and($page->path)->toBe('source');
});

it('rejects stale updates', function (): void {
    $page = createTestPage('pages.contact', 'contact');
    $payload = new UpdatePageData(
        slug: 'contact',
        kind: PageKind::Static,
        resource: null,
        status: PageStatus::Published,
        expectedRevision: $page->revision,
        translations: ['en' => ['title' => 'Contact']],
    );
    app(UpdatePageAction::class)->execute($page, $payload, PageActorData::system());

    expect(fn (): Page => app(UpdatePageAction::class)->execute(
        $page,
        $payload,
        PageActorData::system(),
    ))->toThrow(StalePageException::class);
});

it('translates unique database violations into stable page conflicts', function (): void {
    createTestPage('pages.unique', 'unique');

    expect(fn (): Page => createTestPage('pages.unique', 'another-slug'))
        ->toThrow(PageConflictException::class);
});

it('always scopes management lists to one explicit site', function (): void {
    $default = createTestPage('pages.default-site', 'default-site');
    app(CreatePageAction::class)->execute(
        new CreatePageData(
            key: 'pages.other-site',
            slug: 'other-site',
            site: 'other',
            status: PageStatus::Published,
            translations: ['en' => ['title' => 'Other site']],
        ),
        PageActorData::system(),
    );
    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        $pages = app(ListPagesAction::class)->execute(
            [],
            'default',
            PageActorData::system(),
            perPage: 1,
        );
        $queryCount = count(pageDataQueries());
        $item = $pages->items()[0] ?? null;
        $serialized = $item?->toArray();

        expect(pageDataQueries())->toHaveCount($queryCount)
            ->and($queryCount)->toBeLessThanOrEqual(3)
            ->and($pages->total())->toBe(1)
            ->and($pages->currentPage())->toBe(1)
            ->and($pages->perPage())->toBe(1)
            ->and($pages->getOptions()['path'] ?? null)->toBe($pages->path())
            ->and($pages->appends(['site' => 'default'])->url(2))->toContain(
                'page=2',
                'site=default',
            )
            ->and($item)->toBeInstanceOf(PageData::class)
            ->and($item?->id)->toBe($default->id)
            ->and($item?->translations['en']['title'] ?? null)->toBe('Default-site')
            ->and(array_keys($serialized ?? []))->toBe([
                'id',
                'parentId',
                'key',
                'site',
                'slug',
                'path',
                'kind',
                'resource',
                'status',
                'position',
                'isNavigable',
                'sitemapIncluded',
                'sitemapPriority',
                'sitemapChangeFrequency',
                'publishedAt',
                'expiresAt',
                'revision',
                'translations',
                'createdAt',
                'updatedAt',
            ]);
    } finally {
        DB::disableQueryLog();
    }

    $page = app(GetPageAction::class)->execute($default, PageActorData::system());

    expect($page)->toBeInstanceOf(PageData::class)
        ->and($page->id)->toBe($default->id)
        ->and($page->translations['en']['title'] ?? null)->toBe('Default-site');
});

it('loads only relationships consumed by the management page projection', function (): void {
    $parent = createTestPage('pages.management-parent', 'management-parent');
    $child = createTestPage(
        'pages.management-child',
        'management-child',
        $parent->id,
    );
    $page = Page::query()->findOrFail($child->id);
    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        $canonical = app(PageReader::class)->get($page, SystemActor::Background);
        $projection = PageData::fromModel($canonical);

        expect($projection->parentId)->toBe($parent->id)
            ->and(pageDataQueries())->toHaveCount(2)
            ->and($canonical->relationLoaded('translations'))->toBeTrue()
            ->and($page->relationLoaded('parent'))->toBeFalse()
            ->and($page->relationLoaded('children'))->toBeFalse();
    } finally {
        DB::disableQueryLog();
    }
});

it('finds site-scoped page keys and reports the real global key constraint', function (): void {
    $actor = PageActorData::system();
    $page = createTestPage('pages.lookup', 'lookup');
    $otherSite = app(CreatePageAction::class)->execute(
        new CreatePageData(
            key: 'pages.other-lookup',
            slug: 'other-lookup',
            site: 'other',
            status: PageStatus::Published,
            translations: ['en' => ['title' => 'Other lookup']],
        ),
        $actor,
    );
    $found = app(FindPageByKeyAction::class)->execute(
        ' default ',
        ' pages.lookup ',
        $actor,
    );
    $conflict = app(CheckPageKeyAvailabilityAction::class)->execute(
        'default',
        'pages.lookup',
        $actor,
    );
    $unchanged = app(CheckPageKeyAvailabilityAction::class)->execute(
        'default',
        'pages.lookup',
        $actor,
        strtoupper($page->id),
    );
    $foreignConflict = app(CheckPageKeyAvailabilityAction::class)->execute(
        'default',
        'pages.other-lookup',
        $actor,
    );
    $available = app(CheckPageKeyAvailabilityAction::class)->execute(
        'default',
        'pages.available',
        $actor,
    );

    expect($found)->toBeInstanceOf(PageData::class)
        ->and($found->id)->toBe($page->id)
        ->and($found->translations['en']['title'] ?? null)->toBe('Lookup')
        ->and($conflict)->toBeInstanceOf(PageKeyAvailabilityData::class)
        ->and($conflict->toArray())->toBe([
            'site' => 'default',
            'key' => 'pages.lookup',
            'available' => false,
            'conflictingPageId' => $page->id,
        ])
        ->and($unchanged->available)->toBeTrue()
        ->and($unchanged->conflictingPageId)->toBeNull()
        ->and($foreignConflict->available)->toBeFalse()
        ->and($foreignConflict->conflictingPageId)->toBeNull()
        ->and($available->available)->toBeTrue()
        ->and($otherSite->site)->toBe('other')
        ->and(fn () => app(FindPageByKeyAction::class)->execute(
            'other',
            'pages.lookup',
            $actor,
        ))->toThrow(ModelNotFoundException::class)
        ->and(fn () => app(FindPageByKeyAction::class)->execute(
            'default',
            'pages.missing',
            $actor,
        ))->toThrow(ModelNotFoundException::class)
        ->and(fn () => app(CheckPageKeyAvailabilityAction::class)->execute(
            'default',
            'Invalid Key',
            $actor,
        ))->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(CheckPageKeyAvailabilityAction::class)->execute(
            'default',
            'pages.lookup',
            $actor,
            'not-a-uuid',
        ))->toThrow(InvalidArgumentException::class);
});

it('returns bounded localized page option DTOs with portable search behavior', function (): void {
    $actor = PageActorData::system();
    $alpha = app(CreatePageAction::class)->execute(
        new CreatePageData(
            key: 'pages.alpha-option',
            slug: 'alpha-option',
            status: PageStatus::Published,
            translations: ['en' => ['title' => 'Alpha fallback']],
        ),
        $actor,
    );
    $beta = app(CreatePageAction::class)->execute(
        new CreatePageData(
            key: 'pages.beta-option',
            slug: 'beta-option',
            status: PageStatus::Draft,
            translations: [
                'en' => ['title' => 'Beta'],
                'bg' => ['title' => 'Бета резултат'],
            ],
        ),
        $actor,
    );
    $options = app(ListPageOptionsAction::class)->execute(
        'default',
        'bg',
        $actor,
    );
    $translatedSearch = app(ListPageOptionsAction::class)->execute(
        'default',
        'bg',
        $actor,
        'резултат',
    );
    $keySearch = app(ListPageOptionsAction::class)->execute(
        'default',
        'en',
        $actor,
        'alpha-option',
    );

    expect($options)->toBeInstanceOf(Collection::class)
        ->and($options)->toHaveCount(2)
        ->and($options->every(
            static fn (mixed $option): bool => $option instanceof PageOptionData,
        ))->toBeTrue()
        ->and($options->pluck('id')->all())->toBe([$alpha->id, $beta->id])
        ->and($options[0]->toArray())->toBe([
            'id' => $alpha->id,
            'key' => 'pages.alpha-option',
            'label' => 'Alpha fallback',
            'path' => 'alpha-option',
            'kind' => 'static',
            'status' => 'published',
            'revision' => 1,
        ])
        ->and($translatedSearch->pluck('id')->all())->toBe([$beta->id])
        ->and($keySearch->pluck('id')->all())->toBe([$alpha->id])
        ->and(app(ListPageOptionsAction::class)->execute(
            'default',
            'en',
            $actor,
            null,
            1,
        ))->toHaveCount(1)
        ->and(fn () => app(ListPageOptionsAction::class)->execute(
            'default',
            'de',
            $actor,
        ))->toThrow(InvalidLocaleException::class)
        ->and(fn () => app(ListPageOptionsAction::class)->execute(
            'default',
            'en',
            $actor,
            str_repeat('a', 161),
        ))->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(ListPageOptionsAction::class)->execute(
            'default',
            'en',
            $actor,
            "invalid\0search",
        ))->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(ListPageOptionsAction::class)->execute(
            'default',
            'en',
            $actor,
            "\xC3\x28",
        ))->toThrow(InvalidArgumentException::class);

    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        $oneCharacter = app(ListPageOptionsAction::class)->execute(
            'default',
            'en',
            $actor,
            'a',
        );

        expect($oneCharacter)->toBeEmpty()
            ->and(DB::getQueryLog())->toBe([]);
    } finally {
        DB::disableQueryLog();
    }
});

it('authorizes page availability and options before querying storage', function (): void {
    $page = createTestPage('pages.secure-find', 'secure-find');

    app()->instance(PageAuthorization::class, new class implements PageAuthorization
    {
        public function authorize(
            PageAbility $ability,
            Authenticatable|SystemActor|PublicActor $actor,
            ?Page $page = null,
            array $context = [],
        ): void {
            throw new AuthorizationException;
        }
    });

    foreach ([
        static fn () => app(CheckPageKeyAvailabilityAction::class)->execute(
            'default',
            'pages.secure',
            PageActorData::anonymous(),
        ),
        static fn () => app(ListPageOptionsAction::class)->execute(
            'default',
            'en',
            PageActorData::anonymous(),
        ),
    ] as $read) {
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            expect($read)->toThrow(AuthorizationException::class)
                ->and(DB::getQueryLog())->toBe([]);
        } finally {
            DB::disableQueryLog();
        }
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        expect(fn () => app(FindPageByKeyAction::class)->execute(
            'default',
            $page->key,
            PageActorData::anonymous(),
        ))->toThrow(AuthorizationException::class)
            ->and(pageDataQueries())->toHaveCount(1)
            ->and(collect(DB::getQueryLog())->contains(
                static fn (array $query): bool => str_contains(
                    $query['query'],
                    'pages_i18n',
                ),
            ))->toBeFalse();
    } finally {
        DB::disableQueryLog();
    }
});

it('rejects non-portable option search bytes before querying storage', function (): void {
    foreach (["invalid\0search", "\xC3\x28"] as $search) {
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            expect(fn () => app(ListPageOptionsAction::class)->execute(
                'default',
                'en',
                PageActorData::system(),
                $search,
            ))->toThrow(InvalidArgumentException::class)
                ->and(DB::getQueryLog())->toBe([]);
        } finally {
            DB::disableQueryLog();
        }
    }
});

it('caps page options at the configured and absolute one-hundred entry limit', function (): void {
    $actor = PageActorData::system();

    foreach (range(1, 101) as $index) {
        createTestPage("pages.option-cap-{$index}", "option-cap-{$index}");
    }

    $absolute = app(ListPageOptionsAction::class)->execute(
        'default',
        'en',
        $actor,
        limit: 1000,
    );
    config()->set('nvl-pages.limits.maximum_page_options', 3);
    $configured = app(ListPageOptionsAction::class)->execute(
        'default',
        'en',
        $actor,
        limit: 100,
    );

    expect($absolute)->toHaveCount(100)
        ->and($configured)->toHaveCount(3);
});

it('lists only visible children of one visible parent in sibling order', function (): void {
    $now = Carbon::parse('2026-08-29 12:00:00 UTC');
    Carbon::setTestNow($now);

    try {
        $actor = PageActorData::system();
        $parent = createTestPage('pages.public-parent', 'public-parent');
        $scheduled = app(CreatePageAction::class)->execute(
            new CreatePageData(
                key: 'pages.public-scheduled',
                slug: 'public-scheduled',
                parentId: $parent->id,
                status: PageStatus::Scheduled,
                position: 10,
                publishedAt: $now->copy()->subHour()->toISOString(),
                translations: ['en' => ['title' => 'Scheduled fallback']],
            ),
            $actor,
        );
        $published = app(CreatePageAction::class)->execute(
            new CreatePageData(
                key: 'pages.public-published',
                slug: 'public-published',
                parentId: $parent->id,
                status: PageStatus::Published,
                position: 20,
                translations: ['en' => ['title' => 'Published fallback']],
            ),
            $actor,
        );
        app(CreatePageAction::class)->execute(
            new CreatePageData(
                key: 'pages.public-future',
                slug: 'public-future',
                parentId: $parent->id,
                status: PageStatus::Scheduled,
                position: 0,
                publishedAt: $now->copy()->addHour()->toISOString(),
                translations: ['en' => ['title' => 'Future']],
            ),
            $actor,
        );
        app(CreatePageAction::class)->execute(
            new CreatePageData(
                key: 'pages.public-expired',
                slug: 'public-expired',
                parentId: $parent->id,
                status: PageStatus::Published,
                position: 0,
                publishedAt: $now->copy()->subHours(2)->toISOString(),
                expiresAt: $now->copy()->subHour()->toISOString(),
                translations: ['en' => ['title' => 'Expired']],
            ),
            $actor,
        );
        app(CreatePageAction::class)->execute(
            new CreatePageData(
                key: 'pages.public-draft',
                slug: 'public-draft',
                parentId: $parent->id,
                status: PageStatus::Draft,
                translations: ['en' => ['title' => 'Draft']],
            ),
            $actor,
        );
        $authorization = new class implements PageAuthorization
        {
            public ?PageAbility $ability = null;

            public ?Page $page = null;

            public array $context = [];

            public function authorize(
                PageAbility $ability,
                Authenticatable|SystemActor|PublicActor $actor,
                ?Page $page = null,
                array $context = [],
            ): void {
                $this->ability = $ability;
                $this->page = $page;
                $this->context = $context;
            }
        };
        app()->instance(PageAuthorization::class, $authorization);
        $children = app(ListPublicChildPagesAction::class)->execute(
            strtoupper($parent->id),
            new PageRequestContextData('default', 'bg'),
        );

        expect($children)->toBeInstanceOf(Collection::class)
            ->and($children)->toHaveCount(2)
            ->and($children->every(
                static fn (mixed $child): bool => $child instanceof PublicPageData,
            ))->toBeTrue()
            ->and($children->pluck('id')->all())->toBe([$scheduled->id, $published->id])
            ->and($children[0]->title)->toBe('Scheduled fallback')
            ->and($children[0]->titleLocale)->toBe('en')
            ->and($children[0]->publishedAt)->toBe('2026-08-29T11:00:00+00:00')
            ->and($children[0]->url)->toBe('https://pages.test/public-parent/public-scheduled')
            ->and($authorization->ability)->toBe(PageAbility::ViewNavigation)
            ->and($authorization->page?->id)->toBe($parent->id)
            ->and(($authorization->context['site'] ?? null))->toBe('default')
            ->and(($authorization->context['locale'] ?? null))->toBe('bg');
    } finally {
        Carbon::setTestNow();
    }
});

it('filters and orders public children before applying the requested limit', function (): void {
    $now = Carbon::parse('2026-08-29 12:00:00 UTC');
    Carbon::setTestNow($now);

    try {
        $actor = PageActorData::system();
        $parent = createTestPage('pages.news-parent', 'news-parent');
        createTestPage(
            'pages.news-resource',
            'news-resource',
            $parent->id,
            PageKind::Resource,
            'records.detail',
        );
        $older = app(CreatePageAction::class)->execute(
            new CreatePageData(
                key: 'pages.news-older',
                slug: 'news-older',
                parentId: $parent->id,
                status: PageStatus::Published,
                position: 1,
                publishedAt: $now->copy()->subHours(2)->toISOString(),
                translations: ['en' => ['title' => 'Older news']],
            ),
            $actor,
        );
        $newer = app(CreatePageAction::class)->execute(
            new CreatePageData(
                key: 'pages.news-newer',
                slug: 'news-newer',
                parentId: $parent->id,
                status: PageStatus::Published,
                position: 2,
                publishedAt: $now->copy()->subHour()->toISOString(),
                translations: ['en' => ['title' => 'Newer news']],
            ),
            $actor,
        );

        $children = app(ListPublicChildPagesAction::class)->execute(
            $parent->id,
            new PageRequestContextData('default', 'en'),
            limit: 1,
            kind: PageKind::Static,
            order: PublicChildPageOrder::Newest,
        );

        expect($children)->toHaveCount(1)
            ->and($children[0]->id)->toBe($newer->id)
            ->and($children[0]->id)->not->toBe($older->id);
    } finally {
        Carbon::setTestNow();
    }
});

it('rejects hidden foreign and unauthorized public parents before child disclosure', function (): void {
    $actor = PageActorData::system();
    $visible = createTestPage('pages.visible-parent', 'visible-parent');
    createTestPage('pages.visible-child', 'visible-child', $visible->id);
    $hidden = app(CreatePageAction::class)->execute(
        new CreatePageData(
            key: 'pages.hidden-parent',
            slug: 'hidden-parent',
            status: PageStatus::Draft,
            translations: ['en' => ['title' => 'Hidden parent']],
        ),
        $actor,
    );

    expect(fn () => app(ListPublicChildPagesAction::class)->execute(
        $hidden->id,
        new PageRequestContextData('default', 'en'),
    ))->toThrow(ModelNotFoundException::class)
        ->and(fn () => app(ListPublicChildPagesAction::class)->execute(
            $visible->id,
            new PageRequestContextData('other', 'en'),
        ))->toThrow(ModelNotFoundException::class)
        ->and(fn () => app(ListPublicChildPagesAction::class)->execute(
            'not-a-uuid',
            new PageRequestContextData('default', 'en'),
        ))->toThrow(InvalidArgumentException::class);

    app()->instance(PageAuthorization::class, new class implements PageAuthorization
    {
        public function authorize(
            PageAbility $ability,
            Authenticatable|SystemActor|PublicActor $actor,
            ?Page $page = null,
            array $context = [],
        ): void {
            throw new AuthorizationException;
        }
    });
    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        expect(fn () => app(ListPublicChildPagesAction::class)->execute(
            $visible->id,
            new PageRequestContextData('default', 'en'),
        ))->toThrow(AuthorizationException::class)
            ->and(collect(DB::getQueryLog())->contains(
                static fn (array $query): bool => str_contains(
                    $query['query'],
                    '"parent_id" = ?',
                ),
            ))->toBeFalse();
    } finally {
        DB::disableQueryLog();
    }
});

it('builds static publication projections by ID and rejects ineligible pages', function (): void {
    $actor = PageActorData::system();
    $page = createTestPage('pages.publication', 'publication');
    $block = createPageTestContentBlock('publication-section');
    app(PlaceContentBlockAction::class)->place(
        $block,
        $page,
        Page::CONTENT_GROUP,
        ['key' => 'publication-main'],
        $actor->contentActor(),
    );
    app(SyncSeoProfileAction::class)->execute(
        $page,
        [
            'translations' => [
                'en' => [
                    'path' => '/publication',
                    'title' => 'Publication SEO',
                ],
            ],
        ],
        'default',
    );
    $resource = createTestPage(
        'pages.publication-resource',
        'publication-resource',
        kind: PageKind::Resource,
        resource: 'records.detail',
    );
    $draft = app(CreatePageAction::class)->execute(
        new CreatePageData(
            key: 'pages.publication-draft',
            slug: 'publication-draft',
            translations: ['en' => ['title' => 'Publication draft']],
        ),
        $actor,
    );
    $publication = app(GetPagePublicationProjectionAction::class)->execute(
        strtoupper($page->id),
        'en',
        $actor,
    );

    expect($publication)->toBeInstanceOf(ResolvedPageData::class)
        ->and($publication->page->id)->toBe($page->id)
        ->and($publication->content['blocks'])->toHaveCount(1)
        ->and($publication->seo['title'])->toBe('Publication SEO | Laravel')
        ->and($publication->resource)->toBeNull()
        ->and(fn () => app(GetPagePublicationProjectionAction::class)->execute(
            $draft->id,
            'en',
            $actor,
        ))->toThrow(ModelNotFoundException::class)
        ->and(fn () => app(GetPagePublicationProjectionAction::class)->execute(
            $resource->id,
            'en',
            $actor,
        ))->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(GetPagePublicationProjectionAction::class)->execute(
            $page->id,
            'de',
            $actor,
        ))->toThrow(InvalidLocaleException::class);
});

it('does not compose publication data after page authorization denial', function (): void {
    $page = createTestPage('pages.publication-denied', 'publication-denied');
    app()->instance(PageAuthorization::class, new class implements PageAuthorization
    {
        public function authorize(
            PageAbility $ability,
            Authenticatable|SystemActor|PublicActor $actor,
            ?Page $page = null,
            array $context = [],
        ): void {
            throw new AuthorizationException;
        }
    });
    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        expect(fn () => app(GetPagePublicationProjectionAction::class)->execute(
            $page->id,
            'en',
            PageActorData::anonymous(),
        ))->toThrow(AuthorizationException::class)
            ->and(collect(DB::getQueryLog())->contains(
                static fn (array $query): bool => str_contains(
                    $query['query'],
                    'content_placements',
                ),
            ))->toBeFalse();
    } finally {
        DB::disableQueryLog();
    }
});

it('keeps option and public-child queries constant and enforces child caps', function (): void {
    $actor = PageActorData::system();
    $parent = createTestPage('pages.query-parent', 'query-parent');
    createTestPage('pages.query-child-1', 'query-child-1', $parent->id);
    $measure = static function () use ($actor, $parent): array {
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $options = app(ListPageOptionsAction::class)->execute(
                'default',
                'en',
                $actor,
            );
            $optionQueries = count(pageDataQueries());
            $options->toArray();

            expect(pageDataQueries())->toHaveCount($optionQueries);
            DB::flushQueryLog();
            $children = app(ListPublicChildPagesAction::class)->execute(
                $parent->id,
                new PageRequestContextData('default', 'en'),
            );
            $childQueries = count(pageDataQueries());
            $children->toArray();

            expect(pageDataQueries())->toHaveCount($childQueries);

            return [$optionQueries, $childQueries];
        } finally {
            DB::disableQueryLog();
        }
    };
    [$singleOptionQueries, $singleChildQueries] = $measure();

    foreach (range(2, 101) as $index) {
        createTestPage(
            "pages.query-child-{$index}",
            "query-child-{$index}",
            $parent->id,
        );
    }

    [$populatedOptionQueries, $populatedChildQueries] = $measure();
    $absolute = app(ListPublicChildPagesAction::class)->execute(
        $parent->id,
        new PageRequestContextData('default', 'en'),
        1000,
    );
    config()->set('nvl-pages.limits.maximum_public_children', 4);
    $configured = app(ListPublicChildPagesAction::class)->execute(
        $parent->id,
        new PageRequestContextData('default', 'en'),
        100,
    );

    expect($singleOptionQueries)->toBeLessThanOrEqual(2)
        ->and($singleChildQueries)->toBeLessThanOrEqual(3)
        ->and($populatedOptionQueries)->toBe($singleOptionQueries)
        ->and($populatedChildQueries)->toBe($singleChildQueries)
        ->and($absolute)->toHaveCount(100)
        ->and($configured)->toHaveCount(4);
});

it('keeps localized navigation queries independent of page count', function (): void {
    $measure = static function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $navigation = app(GetNavigationAction::class)->execute(
            'default',
            'en',
            PageActorData::anonymous(),
        );
        $queryCount = count(pageDataQueries());
        $navigation->toArray();

        expect(pageDataQueries())->toHaveCount($queryCount);
        DB::disableQueryLog();

        return $queryCount;
    };

    createTestPage('pages.query-1', 'query-1');
    $singleQueryCount = $measure();

    foreach (range(2, 25) as $index) {
        createTestPage("pages.query-{$index}", "query-{$index}");
    }

    $populatedQueryCount = $measure();

    expect($singleQueryCount)->toBeLessThanOrEqual(2)
        ->and($populatedQueryCount)->toBe($singleQueryCount);
});

it('resolves only resources admitted by the handler query', function (): void {
    $page = createTestPage(
        'pages.records',
        'records',
        kind: PageKind::Resource,
        resource: 'records.detail',
    );
    $public = TestPageResource::query()->create(['name' => 'Visible', 'is_public' => true]);
    $private = TestPageResource::query()->create(['name' => 'Hidden', 'is_public' => false]);
    $resolved = app(ResolvePageAction::class)->execute(
        "records/{$public->id}",
        'default',
        'en',
        PageActorData::anonymous(),
    );

    expect($resolved->page->id)->toBe($page->id)
        ->and($resolved->resource?->id)->toBe($public->id)
        ->and($resolved->resource?->payload)->toBe(['name' => 'Visible']);

    expect(fn () => app(ResolvePageAction::class)->execute(
        "records/{$private->id}",
        'default',
        'en',
        PageActorData::anonymous(),
    ))->toThrow(NotFoundHttpException::class);

    expect(fn () => app(ResolvePageAction::class)->execute(
        'records/not-a-uuid',
        'default',
        'en',
        PageActorData::anonymous(),
    ))->toThrow(NotFoundHttpException::class);
});

it('projects sitemap urls only from a matching SEO profile that has a route', function (): void {
    config()->set('nvl-seo.site.base_url', 'https://pages.test');
    $static = createTestPage('pages.info', 'info');
    createTestPage(
        'pages.records-map',
        'records-map',
        kind: PageKind::Resource,
        resource: 'records.detail',
    );
    $resource = TestPageResource::query()->create(['name' => 'Mapped', 'is_public' => true]);
    $entries = iterator_to_array(app(PageSitemapSource::class)->entries('default'));
    $urls = array_map(static fn ($entry): string => $entry->url, $entries);

    expect($urls)->toContain('https://pages.test/info')
        ->and($urls)->toContain("https://pages.test/records-map/{$resource->id}");

    app(SyncSeoProfileAction::class)->execute(
        $static,
        [
            'translations' => ['en' => ['path' => '/other/info']],
        ],
        'other',
    );
    $urls = array_map(
        static fn ($entry): string => $entry->url,
        iterator_to_array(app(PageSitemapSource::class)->entries('default')),
    );

    expect($urls)->toContain('https://pages.test/info');

    app(SyncSeoProfileAction::class)->execute(
        $static,
        ['isIndexable' => true, 'sitemapIncluded' => true],
        'default',
    );
    $urls = array_map(
        static fn ($entry): string => $entry->url,
        iterator_to_array(app(PageSitemapSource::class)->entries('default')),
    );

    expect($urls)->toContain('https://pages.test/info');

    app(SyncSeoProfileAction::class)->execute(
        $static,
        [
            'translations' => ['en' => ['path' => '/localized-info']],
        ],
        'default',
    );
    $urls = array_map(
        static fn ($entry): string => $entry->url,
        iterator_to_array(app(PageSitemapSource::class)->entries('default')),
    );

    expect($urls)->not->toContain('https://pages.test/info')
        ->and($urls)->toContain('https://pages.test/localized-info')
        ->and($urls)->toContain("https://pages.test/records-map/{$resource->id}");
});

it('applies Page eligibility to SEO backed sitemap entries', function (string $state): void {
    config()->set(['nvl-seo.site.base_url' => 'https://pages.test', 'nvl-seo.sitemap.cache_seconds' => 0]);
    $page = createTestPage('pages.sitemap-eligibility', 'eligibility');
    app(SyncSeoProfileAction::class)->execute($page, [
        'translations' => ['en' => ['path' => '/eligibility']],
    ], 'default');

    expect(app(SitemapGenerator::class)->generate('default'))->toContain('https://pages.test/eligibility');

    if ($state === 'deleted') {
        app(DeletePageAction::class)->execute($page, new DeletePageData($page->revision), PageActorData::system());
    } else {
        app(UpdatePageAction::class)->execute($page, new UpdatePageData(
            slug: 'eligibility',
            kind: PageKind::Static,
            resource: null,
            status: match ($state) {
                'draft' => PageStatus::Draft,
                'archived' => PageStatus::Archived,
                'scheduled' => PageStatus::Scheduled,
                default => PageStatus::Published,
            },
            expectedRevision: $page->revision,
            sitemapIncluded: $state !== 'excluded',
            publishedAt: in_array($state, ['scheduled', 'future-published'], true) ? now()->addDay()->toAtomString() : null,
            expiresAt: $state === 'expired' ? now()->subMinute()->toAtomString() : null,
            translations: ['en' => ['title' => 'Eligibility']],
        ), PageActorData::system());
    }

    expect(app(SitemapGenerator::class)->generate('default'))->not->toContain('https://pages.test/eligibility');
})->with(['deleted', 'draft', 'archived', 'scheduled', 'future-published', 'expired', 'excluded']);

it('honors explicit SEO exclusions instead of emitting Page fallback urls', function (array $policy): void {
    config()->set(['nvl-seo.site.base_url' => 'https://pages.test', 'nvl-seo.sitemap.cache_seconds' => 0]);
    $page = createTestPage('pages.sitemap-excluded', 'excluded');
    app(SyncSeoProfileAction::class)->execute($page, [
        ...$policy,
        'translations' => ['en' => ['path' => '/excluded']],
    ], 'default');

    expect(app(SitemapGenerator::class)->generate('default'))->not->toContain('https://pages.test/excluded');
})->with([
    'noindex' => [['isIndexable' => false]],
    'sitemap exclusion' => [['sitemapIncluded' => false]],
]);

it('keeps Page sitemap profile projection localized scoped and bounded', function (): void {
    config()->set(['nvl-seo.site.base_url' => 'https://pages.test', 'nvl-seo.sitemap.cache_seconds' => 0]);
    $first = createTestPage('pages.sitemap-first', 'first');
    app(SyncSeoProfileAction::class)->execute($first, [
        'translations' => ['en' => ['path' => '/first'], 'bg' => ['path' => '/bg/first']],
    ], 'default');
    DB::enableQueryLog();
    $single = app(SitemapGenerator::class)->generate('default');
    $singleQueries = count(pageDataQueries());
    DB::disableQueryLog();
    DB::flushQueryLog();

    foreach (range(2, 25) as $index) {
        $page = createTestPage('pages.sitemap-'.$index, 'page-'.$index);
        app(SyncSeoProfileAction::class)->execute($page, [
            'translations' => ['en' => ['path' => '/page-'.$index]],
        ], 'default');
    }

    DB::enableQueryLog();
    $populated = app(SitemapGenerator::class)->generate('default');
    $populatedQueries = count(pageDataQueries());
    DB::disableQueryLog();
    DB::flushQueryLog();

    expect($single)->toContain('hreflang="bg"', 'https://pages.test/bg/first')
        ->and(substr_count($single, '<loc>https://pages.test/first</loc>'))->toBe(1)
        ->and($populated)->toContain('https://pages.test/page-25')
        ->and($populatedQueries)->toBe($singleQueries)
        ->and($populatedQueries)->toBeLessThanOrEqual(5)
        ->and(app(SitemapGenerator::class)->generate('other'))->not->toContain('https://pages.test/first');
});

it('preserves sitemap ownership across a host authored Page morph change', function (bool $createdBeforeMap): void {
    config()->set(['nvl-seo.site.base_url' => 'https://pages.test', 'nvl-seo.sitemap.cache_seconds' => 0]);
    $originalMap = Relation::morphMap();

    try {
        if (! $createdBeforeMap) {
            Relation::morphMap(['late-page' => Page::class]);
        }
        $page = createTestPage('pages.late-morph', 'late-morph');
        $profile = app(SyncSeoProfileAction::class)->execute($page, [
            'translations' => ['en' => ['path' => '/late-morph']],
        ], 'default');
        $storedType = $profile->seoable_type;
        expect($storedType)->toBe($page->getMorphClass())
            ->and(app(SitemapGenerator::class)->generate('default'))->toContain('https://pages.test/late-morph');

        if ($createdBeforeMap) {
            Relation::morphMap(['late-page' => Page::class]);
        }
        expect($page->getMorphClass())->toBe('late-page')
            ->and($profile->refresh()->seoable_type)->toBe($storedType);
        app(DeletePageAction::class)->execute($page, new DeletePageData($page->revision), PageActorData::system());

        expect(app(SitemapGenerator::class)->generate('default'))->not->toContain('https://pages.test/late-morph');
    } finally {
        Relation::morphMap($originalMap, false);
    }
})->with(['profile before host alias' => true, 'profile after host alias' => false]);

it('guards rollback when Laravel omits the migration event name', function (): void {
    config()->set([
        'database.connections.pages_rollback' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
        'nvl-pages.connection' => 'pages_rollback',
        'nvl-pages.tables.pages' => 'rollback_pages',
    ]);
    $migration = require __DIR__.'/../../database/migrations/2026_07_28_100001_nvl_pages_create_pages_table.php';
    $migration->up();
    $now = now();
    $parentId = (string) Str::uuid();
    $childId = (string) Str::uuid();
    DB::connection('pages_rollback')->table('rollback_pages')->insert([
        [
            'id' => $parentId,
            'parent_id' => null,
            'key' => 'rollback.parent',
            'slug' => 'parent',
            'path' => 'parent',
            'path_hash' => hash('sha256', 'parent'),
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'id' => $childId,
            'parent_id' => $parentId,
            'key' => 'rollback.child',
            'slug' => 'child',
            'path' => 'parent/child',
            'path_hash' => hash('sha256', 'parent/child'),
            'created_at' => $now,
            'updated_at' => $now,
        ],
    ]);

    $started = new MigrationStarted(
        $migration,
        'down',
    );
    Event::dispatch($started);
    $migration->down();

    expect(Schema::connection('pages_rollback')->hasTable('rollback_pages'))->toBeFalse()
        ->and((bool) DB::connection('pages_rollback')->scalar('PRAGMA foreign_keys'))->toBeTrue();

    DB::purge('pages_rollback');
});

it('preserves SQLite protection when host tables still reference Pages', function (): void {
    config()->set([
        'database.connections.pages_rollback_external' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
        'nvl-pages.connection' => 'pages_rollback_external',
        'nvl-pages.tables.pages' => 'guarded_pages',
    ]);
    $schema = Schema::connection('pages_rollback_external');
    $migration = require __DIR__.'/../../database/migrations/2026_07_28_100001_nvl_pages_create_pages_table.php';
    $migration->up();
    $pageId = (string) Str::uuid();
    $now = now();

    DB::connection('pages_rollback_external')->table('guarded_pages')->insert([
        'id' => $pageId,
        'parent_id' => null,
        'key' => 'guarded.page',
        'slug' => 'guarded',
        'path' => 'guarded',
        'path_hash' => hash('sha256', 'guarded'),
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $schema->create('host_page_links', function (Blueprint $table): void {
        $table->id();
        $table->uuid('page_id');
        $table->foreign('page_id')->references('id')->on('guarded_pages')->restrictOnDelete();
    });
    $started = new MigrationStarted(
        $migration,
        'down',
        '2026_07_28_100001_nvl_pages_create_pages_table',
    );
    expect(fn () => Event::dispatch($started))->toThrow(LogicException::class)
        ->and(DB::connection('pages_rollback_external')->scalar('PRAGMA foreign_keys'))->toBe(1)
        ->and($schema->hasTable('guarded_pages'))->toBeTrue()
        ->and($schema->hasTable('host_page_links'))->toBeTrue();

    $schema->withoutForeignKeyConstraints(function () use ($schema): void {
        $schema->dropIfExists('host_page_links');
        $schema->dropIfExists('guarded_pages');
    });
    DB::purge('pages_rollback_external');
});

it('does not mutate Pages for an unrelated migration with the same conventional name', function (): void {
    config()->set([
        'database.connections.pages_rollback_unrelated' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
        'nvl-pages.connection' => 'pages_rollback_unrelated',
        'nvl-pages.tables.pages' => 'unrelated_guard_pages',
    ]);
    $packageMigration = require __DIR__.'/../../database/migrations/2026_07_28_100001_nvl_pages_create_pages_table.php';
    $packageMigration->up();
    $parentId = (string) Str::uuid();
    $childId = (string) Str::uuid();
    $now = now();

    DB::connection('pages_rollback_unrelated')->table('unrelated_guard_pages')->insert([
        [
            'id' => $parentId,
            'parent_id' => null,
            'key' => 'unrelated.parent',
            'slug' => 'parent',
            'path' => 'parent',
            'path_hash' => hash('sha256', 'parent'),
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'id' => $childId,
            'parent_id' => $parentId,
            'key' => 'unrelated.child',
            'slug' => 'child',
            'path' => 'parent/child',
            'path_hash' => hash('sha256', 'parent/child'),
            'created_at' => $now,
            'updated_at' => $now,
        ],
    ]);
    $unrelatedMigration = new class extends Migration {};

    Event::dispatch(new MigrationStarted(
        $unrelatedMigration,
        'down',
        '2026_08_29_100001_create_pages_table',
    ));

    expect(DB::connection('pages_rollback_unrelated')
        ->table('unrelated_guard_pages')
        ->where('id', $childId)
        ->value('parent_id'))->toBe($parentId);

    Schema::connection('pages_rollback_unrelated')->withoutForeignKeyConstraints(
        fn () => Schema::connection('pages_rollback_unrelated')->dropIfExists('unrelated_guard_pages'),
    );
    DB::purge('pages_rollback_unrelated');
});

it('guards Pages rollback on prefixed SQLite connections', function (): void {
    config()->set([
        'database.connections.pages_rollback_prefixed' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => 'pre_',
            'foreign_key_constraints' => true,
        ],
        'nvl-pages.connection' => 'pages_rollback_prefixed',
        'nvl-pages.tables.pages' => 'prefixed_pages',
    ]);
    $schema = Schema::connection('pages_rollback_prefixed');
    $migration = require __DIR__.'/../../database/migrations/2026_07_28_100001_nvl_pages_create_pages_table.php';
    $migration->up();
    $now = now();
    $parentId = (string) Str::uuid();
    $childId = (string) Str::uuid();

    DB::connection('pages_rollback_prefixed')->table('prefixed_pages')->insert([
        [
            'id' => $parentId,
            'parent_id' => null,
            'key' => 'prefixed.parent',
            'slug' => 'parent',
            'path' => 'parent',
            'path_hash' => hash('sha256', 'parent'),
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'id' => $childId,
            'parent_id' => $parentId,
            'key' => 'prefixed.child',
            'slug' => 'child',
            'path' => 'parent/child',
            'path_hash' => hash('sha256', 'parent/child'),
            'created_at' => $now,
            'updated_at' => $now,
        ],
    ]);
    $schema->create('host_page_links', function (Blueprint $table): void {
        $table->id();
        $table->uuid('page_id');
        $table->foreign('page_id')->references('id')->on('prefixed_pages')->restrictOnDelete();
    });
    $started = new MigrationStarted(
        $migration,
        'down',
        '2026_07_28_100001_nvl_pages_create_pages_table',
    );

    expect(fn () => Event::dispatch($started))->toThrow(LogicException::class)
        ->and($schema->hasTable('prefixed_pages'))->toBeTrue()
        ->and($schema->hasTable('host_page_links'))->toBeTrue()
        ->and(DB::connection('pages_rollback_prefixed')->scalar('PRAGMA foreign_keys'))->toBe(1);

    $schema->dropIfExists('host_page_links');
    Event::dispatch($started);
    $migration->down();

    expect($schema->hasTable('prefixed_pages'))->toBeFalse()
        ->and(DB::connection('pages_rollback_prefixed')->scalar('PRAGMA foreign_keys'))->toBe(1);

    DB::purge('pages_rollback_prefixed');
});

it('guards prefixed Pages from case-insensitive unprefixed host references in the same SQLite database', function (): void {
    $databasePath = tempnam(sys_get_temp_dir(), 'nvl-pages-rollback-');

    expect($databasePath)->toBeString();

    config()->set([
        'database.connections.pages_rollback_mixed_prefix' => [
            'driver' => 'sqlite',
            'database' => $databasePath,
            'prefix' => 'pre_',
            'foreign_key_constraints' => true,
        ],
        'database.connections.pages_rollback_mixed_host' => [
            'driver' => 'sqlite',
            'database' => $databasePath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
        'nvl-pages.connection' => 'pages_rollback_mixed_prefix',
        'nvl-pages.tables.pages' => 'mixed_pages',
    ]);
    $pagesSchema = Schema::connection('pages_rollback_mixed_prefix');
    $hostSchema = Schema::connection('pages_rollback_mixed_host');
    $migration = require __DIR__.'/../../database/migrations/2026_07_28_100001_nvl_pages_create_pages_table.php';
    $migration->up();
    $hostSchema->create('host_page_links', function (Blueprint $table): void {
        $table->id();
        $table->uuid('page_id');
        $table->foreign('page_id')->references('id')->on('PRE_MIXED_PAGES')->restrictOnDelete();
    });
    $started = new MigrationStarted(
        $migration,
        'down',
        '2026_07_28_100001_nvl_pages_create_pages_table',
    );

    try {
        expect(fn () => Event::dispatch($started))->toThrow(LogicException::class)
            ->and($pagesSchema->hasTable('mixed_pages'))->toBeTrue()
            ->and($hostSchema->hasTable('host_page_links'))->toBeTrue();

        $hostSchema->dropIfExists('host_page_links');
        Event::dispatch($started);
        $migration->down();

        expect($pagesSchema->hasTable('mixed_pages'))->toBeFalse();
    } finally {
        $hostSchema->withoutForeignKeyConstraints(function () use ($hostSchema): void {
            $hostSchema->dropIfExists('host_page_links');
            $hostSchema->dropIfExists('pre_mixed_pages');
        });
        DB::purge('pages_rollback_mixed_prefix');
        DB::purge('pages_rollback_mixed_host');

        if (is_file($databasePath)) {
            unlink($databasePath);
        }
    }
});

it('does not grant default page management authority to background identity', function (PageAbility $ability): void {
    $authorization = new ConfiguredPageAuthorization;

    expect(fn () => $authorization->authorize($ability, SystemActor::Background))
        ->toThrow(AuthorizationException::class);
})->with(array_filter(PageAbility::cases(), static fn (PageAbility $ability): bool => ! in_array($ability, [PageAbility::View, PageAbility::ViewNavigation], true)));

it('reads canonical published pages anonymously by identity and denies drafts', function (): void {
    $published = createTestPage('native-public-read', 'native-public-read');
    $draft = app(PageLifecycle::class)->create(['key' => 'native-draft-read', 'slug' => 'native-draft-read', 'status' => PageStatus::Draft], SystemActor::Background);
    $policy = new class implements PageAuthorization
    {
        public int $views = 0;

        public function authorize(PageAbility $ability, Authenticatable|SystemActor|PublicActor $actor, ?Page $page = null, array $context = []): void
        {
            if ($ability === PageAbility::View) {
                $this->views++;
            }
        }
    };
    app()->instance(PageAuthorization::class, $policy);
    $persistedSite = $published->site;
    $published->site = 'forged-site';

    $reader = app(PageReader::class);
    expect($reader->get($published, PublicActor::Anonymous)->site)->toBe($persistedSite)
        ->and($policy->views)->toBe(1)
        ->and(fn () => $reader->get($draft, PublicActor::Anonymous))->toThrow(ModelNotFoundException::class)
        ->and($policy->views)->toBe(1)
        ->and($reader->get($draft, SystemActor::Background)->status)->toBe(PageStatus::Draft);
});

it('offers a reusable public authorization policy over canonical page storage', function (): void {
    $page = createTestPage('public-policy-storage', 'public-policy-storage');
    $policy = new ConfiguredPageAuthorization;
    $policy->authorize(PageAbility::View, PublicActor::Anonymous, $page);
    $page->exists = false;
    expect(fn () => $policy->authorize(PageAbility::View, PublicActor::Anonymous, $page))
        ->toThrow(AuthorizationException::class);
    expect(fn () => $policy->authorize(PageAbility::Update, PublicActor::Anonymous, $page))
        ->toThrow(AuthorizationException::class);
});

it('rejects private page identities substituted with a public page key at the policy boundary', function (): void {
    $public = createTestPage('public-policy-key', 'public-policy-key');
    $private = app(PageLifecycle::class)->create(['key' => 'private-policy-key', 'slug' => 'private-policy-key', 'status' => PageStatus::Draft], SystemActor::Background);
    $private->id = $public->id;

    expect(fn () => (new ConfiguredPageAuthorization)->authorize(PageAbility::View, PublicActor::Anonymous, $private))
        ->toThrow(AuthorizationException::class);
});

it('validates only the prospective ancestors instead of hydrating the whole site', function (): void {
    $parent = createTestPage('bounded-parent', 'bounded-parent');
    $unrelated = createTestPage('bounded-unrelated', 'bounded-unrelated');
    $seen = [];
    Page::retrieved(function (Page $page) use (&$seen): void {
        $seen[] = $page->id;
    });
    DB::transaction(function () use ($parent): void {
        app(PageTreeLock::class)->acquire('default');
        app(PageHierarchy::class)->assertValid('default', $parent->id);
    });

    expect($seen)->toBe([$parent->id])
        ->and($seen)->not->toContain($unrelated->id);
});

it('reads descendant paths in batches per level while retaining each revision', function (): void {
    $parent = createTestPage('batch-parent', 'batch-parent');
    $descendants = [];
    for ($index = 0; $index < 6; $index++) {
        $child = createTestPage('batch-child-'.$index, 'child-'.$index, $parent->id);
        $descendants[] = $child;
        $descendants[] = createTestPage('batch-grandchild-'.$index, 'grandchild', $child->id);
    }
    DB::enableQueryLog();
    DB::flushQueryLog();
    try {
        DB::transaction(function () use ($parent): void {
            app(PageTreeLock::class)->acquire('default');
            $parent->path = 'renamed-batch-parent';
            $parent->save();
            expect(app(PageHierarchy::class)->rebuildDescendantPaths($parent))->toHaveCount(12);
        });
        $childReads = array_filter(DB::getQueryLog(), static fn (array $query): bool => str_starts_with(strtolower($query['query']), 'select')
            && preg_match('/["`]?parent_id["`]? in /', $query['query']) === 1);
        expect($childReads)->toHaveCount(3);
    } finally {
        DB::disableQueryLog();
    }
    foreach ($descendants as $descendant) {
        $revision = $descendant->revision;
        expect($descendant->refresh()->path)->toStartWith('renamed-batch-parent/')
            ->and($descendant->revision)->toBe($revision + 1);
    }
});

it('denies page authorization before resolving either present or missing dynamic resources', function (bool $preview, bool $exists): void {
    createTestPage('guarded-resource', 'guarded-resource', kind: PageKind::Resource, resource: 'records.detail');
    $resourceId = $exists ? TestPageResource::query()->create(['name' => 'Private resource'])->id : (string) Str::uuid();
    $actor = PreviewActor::query()->create();
    app()->instance(PageAuthorization::class, new class implements PageAuthorization
    {
        public function authorize(PageAbility $ability, Authenticatable|SystemActor|PublicActor $actor, ?Page $page = null, array $context = []): void
        {
            throw new AuthorizationException('This page is private.');
        }
    });
    DB::enableQueryLog();
    DB::flushQueryLog();
    try {
        $path = 'guarded-resource/'.$resourceId;
        expect(fn () => $preview
            ? app(PageResolver::class)->preview($path, 'default', 'en', $actor)
            : app(PageResolver::class)->resolve($path, 'default', 'en'))
            ->toThrow(AuthorizationException::class);
        expect(array_filter(DB::getQueryLog(), static fn (array $query): bool => str_contains($query['query'], 'page_test_resources')))->toBe([]);
    } finally {
        DB::disableQueryLog();
    }
})->with([[true, true], [true, false], [false, true], [false, false]]);

it('requires publication authority to withdraw a published page into draft', function (): void {
    $page = createTestPage('protected-publication', 'protected-publication');
    app()->instance(PageAuthorization::class, new class implements PageAuthorization
    {
        public function authorize(PageAbility $ability, Authenticatable|SystemActor|PublicActor $actor, ?Page $page = null, array $context = []): void
        {
            if ($ability === PageAbility::Publish) {
                throw new AuthorizationException('Publication is protected.');
            }
        }
    });

    expect(fn () => app(PageLifecycle::class)->update($page, [
        'slug' => $page->slug, 'kind' => $page->kind, 'status' => PageStatus::Draft,
    ], $page->revision, SystemActor::Background))->toThrow(AuthorizationException::class);
    expect($page->refresh()->status)->toBe(PageStatus::Published);
});

it('validates native page inputs before explicitly mapping enums and scalar values', function (): void {
    $create = CreatePageData::fromInput([
        'key' => 'native-input', 'slug' => 'native-input',
        'kind' => 'static', 'status' => 'draft', 'position' => '2',
        'isNavigable' => '0', 'sitemapIncluded' => false,
        'sitemapPriority' => 0.5, 'sitemapChangeFrequency' => 'weekly',
        'translations' => ['en' => ['title' => 'Native input', 'summary' => null]],
    ]);
    $query = PageIndexQueryData::fromInput(['site' => 'default', 'per_page' => '2']);
    $move = MovePageData::fromInput(['parentId' => null, 'position' => '0', 'expected_revision' => '3']);

    expect($create->kind)->toBe(PageKind::Static)
        ->and($create->status)->toBe(PageStatus::Draft)
        ->and($create->position)->toBe(2)
        ->and($create->isNavigable)->toBeFalse()
        ->and($create->sitemapIncluded)->toBeFalse()
        ->and($create->sitemapPriority)->toBe('0.5')
        ->and($create->sitemapChangeFrequency)->toBe(SitemapChangeFrequency::Weekly)
        ->and($create->translations)->toBe(['en' => ['title' => 'Native input', 'summary' => null]])
        ->and($query->perPage)->toBe(2)
        ->and($move->parentId)->toBeNull()
        ->and($move->expectedRevision)->toBe(3)
        ->and(fn () => CreatePageData::fromInput(['key' => 'invalid-enum', 'slug' => 'invalid-enum', 'kind' => 'unknown']))
        ->toThrow(ValidationException::class);
});

it('serializes page actor identity without its authenticated subject', function (): void {
    $authenticated = new GenericUser(['id' => 'operator', 'email' => 'private@example.test', 'password' => 'private-hash']);
    $actor = PageActorData::fromAuthenticatable($authenticated);
    $publicIdentity = ['type' => GenericUser::class, 'id' => 'operator', 'system' => false];

    expect($actor->native())->toBe($authenticated)
        ->and($actor->contentActor())->toBe($authenticated)
        ->and($actor->toArray())->toBe($publicIdentity)
        ->and(json_decode(json_encode($actor, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR))->toBe($publicIdentity)
        ->and(PageActorData::system()->native())->toBe(SystemActor::Background);
});

it('preserves absent and explicitly null publication timestamps in public page JSON', function (): void {
    $attributes = [
        'id' => 'page-id', 'key' => 'public-page', 'slug' => 'public-page', 'path' => 'public-page',
        'kind' => PageKind::Static, 'url' => '/public-page', 'locale' => 'en', 'title' => 'Public page',
        'navigationLabel' => null, 'summary' => null, 'titleLocale' => 'en',
        'navigationLabelLocale' => null, 'summaryLocale' => null,
    ];
    $omitted = new PublicPageData(...$attributes);
    $cleared = new PublicPageData(...[...$attributes, 'publishedAt' => null]);
    $published = new PublicPageData(...[...$attributes, 'publishedAt' => '2026-11-10T12:00:00+00:00']);

    expect($omitted->toArray())->not->toHaveKey('publishedAt')
        ->and($cleared->toArray())->toHaveKey('publishedAt', null)
        ->and($published->toArray())->toHaveKey('publishedAt', '2026-11-10T12:00:00+00:00')
        ->and(json_decode(json_encode($omitted, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR))->not->toHaveKey('publishedAt')
        ->and(json_decode(json_encode($cleared, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR))->toHaveKey('publishedAt', null);
});

it('preserves nested maps, lists, enums and dates in native page display JSON', function (): void {
    $resource = new PageResourceData('records.detail', 'record-id', [
        'translations' => ['en' => ['summary' => null]],
        'items' => [], 'emptyObject' => (object) [], 'status' => PageStatus::Draft,
        'publishedAt' => new DateTimeImmutable('2026-11-10T12:00:00+00:00'),
    ]);
    $json = json_decode(json_encode($resource, JSON_THROW_ON_ERROR), flags: JSON_THROW_ON_ERROR);

    expect($json->payload->translations)->toBeInstanceOf(stdClass::class)
        ->and($json->payload->translations->en->summary)->toBeNull()
        ->and($json->payload->items)->toBe([])
        ->and($json->payload->emptyObject)->toBeInstanceOf(stdClass::class)
        ->and($json->payload->status)->toBe('draft')
        ->and($json->payload->publishedAt)->toBe('2026-11-10T12:00:00+00:00');
});
