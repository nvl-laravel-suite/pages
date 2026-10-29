<?php

declare(strict_types=1);

namespace Nvl\Pages\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Nvl\Pages\Contracts\PageAuthorization;
use Nvl\Pages\Enums\PageAbility;
use Nvl\Pages\Enums\PageChangeOperation;
use Nvl\Pages\Enums\PageKind;
use Nvl\Pages\Enums\PageStatus;
use Nvl\Pages\Events\PageChanged;
use Nvl\Pages\Exceptions\InvalidPageMutationException;
use Nvl\Pages\Exceptions\PageHierarchyException;
use Nvl\Pages\Exceptions\StalePageException;
use Nvl\Pages\Models\Page;
use Nvl\Pages\Support\PagesConfiguration;
use Nvl\Support\Auth\CanonicalActorResolver;
use Nvl\Support\Enums\SystemActor;
use Nvl\Support\Events\DomainEventDispatcher;
use Nvl\Translatable\Rules\SupportedLocaleMapRule;
use Nvl\Translatable\Services\RelatedTranslationStore;

/**
 * Owns complete serialized Page tree and publication mutations.
 *
 * @api
 *
 * @phpstan-type PageMutation array{key?:string,slug:string,parentId:?string,site:string,kind:PageKind,resource:?string,status:PageStatus,position:int,isNavigable:bool,sitemapIncluded:bool,sitemapPriority:?string,sitemapChangeFrequency:?string,publishedAt:?string,expiresAt:?string,translations:array<string,array{title:string,navigationLabel?:?string,summary?:?string}>}
 */
final readonly class PageLifecycle
{
    public function __construct(private CanonicalActorResolver $actors, private PageAuthorization $authorization, private PageDatabaseConflict $conflicts,
        private PageHierarchy $hierarchy, private PageMutationValues $values, private PageTreeLock $treeLock,
        private RelatedTranslationStore $translations, private DomainEventDispatcher $domainEvents) {}

    /** Create a draft by default and return fresh state with translations.
     *
     * The explicit actor must pass create and any requested publication ability.
     * Writes use the configured Page connection; domain events follow its outer commit.
     * Unknown keys, protected identity fields and invalid hierarchy are rejected.
     *
     * @param  array{key:string,slug:string,parentId?:?string,site?:string,kind?:PageKind|string,resource?:?string,status?:PageStatus|string,position?:int,isNavigable?:bool,sitemapIncluded?:bool,sitemapPriority?:?string,sitemapChangeFrequency?:?string,publishedAt?:?string,expiresAt?:?string,translations?:array<string,array{title:string,navigationLabel?:?string,summary?:?string}>}  $attributes
     *
     * @throws InvalidPageMutationException
     * @throws PageHierarchyException
     */
    public function create(array $attributes, Authenticatable|SystemActor $actor): Page
    {
        if ($actor instanceof Authenticatable) {
            $actor = $this->actors->resolve($actor);
        }
        /** @var PageMutation&array{key:string} $data */
        $data = $this->attributes($attributes, true);
        $context = ['site' => $data['site'], 'parentId' => $data['parentId']];
        $this->authorization->authorize(
            PageAbility::Create,
            $actor,
            context: $context,
        );
        $lifecycleAbility = $this->ability(null, $data['status']);

        if ($lifecycleAbility instanceof PageAbility) {
            $this->authorization->authorize($lifecycleAbility, $actor, context: $context);
        }

        $this->values->assertKind($data['kind'], $data['resource']);
        $dates = $this->values->dates(
            $data['status'],
            $data['publishedAt'],
            $data['expiresAt'],
        );

        try {
            return PageModelRegistry::newInstance()->getConnection()
                ->transaction(function () use ($actor, $data, $dates): Page {
                    $this->treeLock->acquire($data['site']);
                    $this->hierarchy->assertValid($data['site'], $data['parentId']);
                    $page = PageModelRegistry::create([
                        'parent_id' => $data['parentId'],
                        'key' => $data['key'],
                        'site' => $data['site'],
                        'slug' => $data['slug'],
                        'path' => $this->hierarchy->path(
                            $data['site'],
                            $data['parentId'],
                            $data['slug'],
                        ),
                        'kind' => $data['kind'],
                        'resource' => $data['resource'],
                        'status' => $data['status'],
                        'position' => $data['position'],
                        'is_navigable' => $data['isNavigable'],
                        'sitemap_included' => $data['sitemapIncluded'],
                        'sitemap_priority' => $data['sitemapPriority'],
                        'sitemap_change_frequency' => $data['sitemapChangeFrequency'],
                        'published_at' => $dates['published_at'],
                        'expires_at' => $dates['expires_at'],
                    ]);
                    $this->replaceTranslations(
                        $page,
                        $this->values->translations($data['translations']),
                    );
                    $this->domainEvents->dispatch(new PageChanged(
                        $page->id,
                        $page->site,
                        PageChangeOperation::Created,
                        $page->revision,
                        $this->actorFacts($actor),
                        [$page->id],
                    ), $page->getConnection());

                    return $page->refresh()->load('translations');
                }, attempts: PagesConfiguration::transactionAttempts());
        } catch (QueryException $exception) {
            $this->conflicts->rethrow($exception);
        }
    }

    /** Replace editable page state using the exact expected revision.
     *
     * Slug, kind and status are required. Other fields follow the documented defaults;
     * this operation is a complete edit, rather than a generic presence-based patch.
     * Identity, site and parent are managed through their dedicated operations.
     *
     * @param  array{slug:string,kind:PageKind|string,resource?:?string,status:PageStatus|string,position?:int,isNavigable?:bool,sitemapIncluded?:bool,sitemapPriority?:?string,sitemapChangeFrequency?:?string,publishedAt?:?string,expiresAt?:?string,translations?:array<string,array{title:string,navigationLabel?:?string,summary?:?string}>}  $attributes
     *
     * @throws StalePageException
     * @throws InvalidPageMutationException
     * @throws PageHierarchyException
     */
    public function update(Page|string $page, array $attributes, int $expectedRevision, Authenticatable|SystemActor $actor): Page
    {
        if ($actor instanceof Authenticatable) {
            $actor = $this->actors->resolve($actor);
        }
        $data = $this->attributes($attributes, false) + ['expected_revision' => $expectedRevision];
        $this->assertRevision($expectedRevision);
        $pageId = $page instanceof Page ? $page->id : $page;
        $page = PageModelRegistry::query()->useWritePdo()->findOrFail($pageId);
        $site = $page->site;

        try {
            return PageModelRegistry::newInstance()->getConnection()
                ->transaction(function () use ($actor, $data, $pageId, $site): Page {
                    $this->treeLock->acquire($site);
                    $page = PageModelRegistry::query()->lockForUpdate()->findOrFail($pageId);

                    if ($page->site !== $site) {
                        throw new StalePageException('The page site changed during the operation.');
                    }

                    if ($page->revision !== $data['expected_revision']) {
                        throw StalePageException::forPage($page->id);
                    }

                    $this->authorization->authorize(PageAbility::Update, $actor, $page);
                    $lifecycleAbility = $this->ability($page->status, $data['status']);

                    if ($lifecycleAbility instanceof PageAbility) {
                        $this->authorization->authorize($lifecycleAbility, $actor, $page);
                    }

                    $this->values->assertKind($data['kind'], $data['resource']);
                    $this->hierarchy->assertValid($page->site, $page->parent_id, $pageId);
                    $dates = $this->values->dates(
                        $data['status'],
                        $data['publishedAt'],
                        $data['expiresAt'],
                    );
                    $originalPath = $page->path;
                    $page->fill([
                        'slug' => $data['slug'],
                        'path' => $this->hierarchy->path(
                            $page->site,
                            $page->parent_id,
                            $data['slug'],
                        ),
                        'kind' => $data['kind'],
                        'resource' => $data['resource'],
                        'status' => $data['status'],
                        'position' => $data['position'],
                        'is_navigable' => $data['isNavigable'],
                        'sitemap_included' => $data['sitemapIncluded'],
                        'sitemap_priority' => $data['sitemapPriority'],
                        'sitemap_change_frequency' => $data['sitemapChangeFrequency'],
                        'published_at' => $dates['published_at'],
                        'expires_at' => $dates['expires_at'],
                    ])->save();
                    $descendants = $page->path !== $originalPath
                        ? $this->hierarchy->rebuildDescendantPaths($page)
                        : [];
                    $this->replaceTranslations(
                        $page,
                        $this->values->translations($data['translations']),
                    );
                    $this->domainEvents->dispatch(new PageChanged(
                        $page->id,
                        $page->site,
                        PageChangeOperation::Updated,
                        $page->revision,
                        $this->actorFacts($actor),
                        [
                            $page->id,
                            ...array_map(
                                static fn (Page $descendant): string => $descendant->id,
                                $descendants,
                            ),
                        ],
                    ), $page->getConnection());

                    return $page->refresh()->load('translations');
                }, attempts: PagesConfiguration::transactionAttempts());
        } catch (QueryException $exception) {
            $this->conflicts->rethrow($exception);
        }
    }

    /** Move one Page with its original ownership, revision and transaction guarantees.
     */
    public function move(Page|string $page, ?string $parentId, int $position, int $expectedRevision, Authenticatable|SystemActor $actor): Page
    {
        if ($actor instanceof Authenticatable) {
            $actor = $this->actors->resolve($actor);
        }
        $data = ['parentId' => $parentId, 'position' => $position, 'expected_revision' => $expectedRevision];
        Validator::make($data, ['parentId' => ['nullable', 'uuid'], 'position' => ['required', 'integer', 'min:0', 'max:2147483647'], 'expected_revision' => ['required', 'integer', 'min:1']])->validate();
        $pageId = $page instanceof Page ? $page->id : $page;
        $page = PageModelRegistry::query()->useWritePdo()->findOrFail($pageId);
        $site = $page->site;

        try {
            return PageModelRegistry::newInstance()->getConnection()
                ->transaction(function () use ($actor, $data, $pageId, $site): Page {
                    $this->treeLock->acquire($site);
                    $page = PageModelRegistry::query()->lockForUpdate()->findOrFail($pageId);

                    if ($page->site !== $site) {
                        throw new StalePageException('The page site changed during the operation.');
                    }

                    if ($page->revision !== $data['expected_revision']) {
                        throw StalePageException::forPage($page->id);
                    }

                    $this->authorization->authorize(
                        PageAbility::Move,
                        $actor,
                        $page,
                        ['site' => $site, 'parentId' => $data['parentId']],
                    );
                    $this->hierarchy->assertValid($page->site, $data['parentId'], $page->id);
                    $originalPath = $page->path;
                    $page->parent_id = $data['parentId'];
                    $page->position = $data['position'];
                    $page->path = $this->hierarchy->path(
                        $page->site,
                        $data['parentId'],
                        $page->slug,
                    );
                    $page->save();
                    $descendants = $page->path !== $originalPath
                        ? $this->hierarchy->rebuildDescendantPaths($page)
                        : [];
                    $this->domainEvents->dispatch(new PageChanged(
                        $page->id,
                        $page->site,
                        PageChangeOperation::Moved,
                        $page->revision,
                        $this->actorFacts($actor),
                        [
                            $page->id,
                            ...array_map(
                                static fn (Page $descendant): string => $descendant->id,
                                $descendants,
                            ),
                        ],
                    ), $page->getConnection());

                    return $page->refresh()->load('translations');
                }, attempts: PagesConfiguration::transactionAttempts());
        } catch (QueryException $exception) {
            $this->conflicts->rethrow($exception);
        }
    }

    /** Delete one Page with its original ownership, revision and transaction guarantees.
     */
    public function delete(Page|string $page, int $expectedRevision, Authenticatable|SystemActor $actor): bool
    {
        if ($actor instanceof Authenticatable) {
            $actor = $this->actors->resolve($actor);
        }
        $data = ['expected_revision' => $expectedRevision];
        $this->assertRevision($expectedRevision);
        $pageId = $page instanceof Page ? $page->id : $page;
        $page = PageModelRegistry::query()->useWritePdo()->findOrFail($pageId);
        $site = $page->site;

        return PageModelRegistry::newInstance()->getConnection()
            ->transaction(function () use ($actor, $data, $pageId, $site): bool {
                $this->treeLock->acquire($site);
                $page = PageModelRegistry::query()->lockForUpdate()->findOrFail($pageId);

                if ($page->site !== $site) {
                    throw new StalePageException('The page site changed during the operation.');
                }

                if ($page->revision !== $data['expected_revision']) {
                    throw StalePageException::forPage($page->id);
                }

                $this->authorization->authorize(PageAbility::Delete, $actor, $page);

                if ($page->children()->exists()) {
                    throw new PageHierarchyException(
                        'A page with child pages cannot be deleted.',
                    );
                }

                $deleted = $page->delete() === true;
                $this->domainEvents->dispatch(new PageChanged(
                    $page->id,
                    $page->site,
                    PageChangeOperation::Deleted,
                    $page->revision,
                    $this->actorFacts($actor),
                    [$page->id],
                ), $page->getConnection());

                return $deleted;
            }, attempts: PagesConfiguration::transactionAttempts());
    }

    /** Restore one Page with its original ownership, revision and transaction guarantees.
     */
    public function restore(Page|string $page, int $expectedRevision, Authenticatable|SystemActor $actor): Page
    {
        if ($actor instanceof Authenticatable) {
            $actor = $this->actors->resolve($actor);
        }
        $data = ['expected_revision' => $expectedRevision];
        $this->assertRevision($expectedRevision);
        $pageId = $page instanceof Page ? $page->id : $page;
        $resolved = PageModelRegistry::query()->withTrashed()->useWritePdo()->findOrFail($pageId);
        $site = $resolved->site;

        try {
            return PageModelRegistry::newInstance()->getConnection()
                ->transaction(function () use ($actor, $data, $pageId, $site): Page {
                    $this->treeLock->acquire($site);
                    $page = PageModelRegistry::query()
                        ->withTrashed()
                        ->lockForUpdate()
                        ->findOrFail($pageId);

                    if ($page->site !== $site) {
                        throw new StalePageException('The page site changed during the operation.');
                    }

                    if (! $page->trashed()) {
                        throw new InvalidPageMutationException(
                            'Only a deleted page may be restored.',
                        );
                    }

                    if ($page->revision !== $data['expected_revision']) {
                        throw StalePageException::forPage($page->id);
                    }

                    $this->authorization->authorize(PageAbility::Restore, $actor, $page);
                    $this->hierarchy->assertValid($page->site, $page->parent_id, $page->id);
                    $page->path = $this->hierarchy->path($page->site, $page->parent_id, $page->slug);
                    $page->restore();
                    $this->domainEvents->dispatch(new PageChanged(
                        $page->id,
                        $page->site,
                        PageChangeOperation::Restored,
                        $page->revision,
                        $this->actorFacts($actor),
                        [$page->id],
                    ), $page->getConnection());

                    return $page->refresh()->load('translations');
                }, attempts: PagesConfiguration::transactionAttempts());
        } catch (QueryException $exception) {
            $this->conflicts->rethrow($exception);
        }
    }

    private function ability(?PageStatus $current, PageStatus $target): ?PageAbility
    {
        if ($current === $target) {
            return null;
        }

        return match ($target) {
            PageStatus::Published, PageStatus::Scheduled => PageAbility::Publish,
            PageStatus::Archived => PageAbility::Archive,
            PageStatus::Draft => null,
        };
    }

    /** @param array<string,mixed> $attributes
     * @return PageMutation
     */
    private function attributes(array $attributes, bool $creating): array
    {
        $rules = [
            'key' => ['required', 'string', 'max:191', 'regex:/^[a-z0-9][a-z0-9._-]*$/'],
            'slug' => ['required', 'string', 'max:191', 'regex:/^[a-z0-9](?:[a-z0-9_-]*[a-z0-9])?$/'],
            'parentId' => ['nullable', 'uuid'],
            'site' => ['sometimes', 'string', 'max:64', 'regex:/^[a-z0-9][a-z0-9._-]*$/'],
            'kind' => ['sometimes', Rule::enum(PageKind::class)],
            'resource' => ['nullable', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_.-]*$/'],
            'status' => ['sometimes', Rule::enum(PageStatus::class)],
            'position' => ['integer', 'min:0', 'max:2147483647'],
            'isNavigable' => ['boolean'],
            'sitemapIncluded' => ['boolean'],
            'sitemapPriority' => ['nullable', 'numeric', 'between:0,1'],
            'sitemapChangeFrequency' => ['nullable', Rule::in(['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'])],
            'publishedAt' => ['nullable', 'date'],
            'expiresAt' => ['nullable', 'date'],
            'translations' => ['array', new SupportedLocaleMapRule],
            'translations.*' => ['array:title,navigationLabel,summary'],
            'translations.*.title' => ['required', 'string', 'max:255'],
            'translations.*.navigationLabel' => ['nullable', 'string', 'max:255'],
            'translations.*.summary' => ['nullable', 'string', 'max:2000'],
        ];
        if (! $creating) {
            unset($rules['key'], $rules['site'], $rules['parentId']);
            $rules['kind'] = ['required', Rule::enum(PageKind::class)];
            $rules['status'] = ['required', Rule::enum(PageStatus::class)];
        }
        if (array_diff(array_keys($attributes), array_keys($rules)) !== []) {
            throw new InvalidArgumentException('Unknown or protected Page mutation fields.');
        }
        if (($attributes['sitemapChangeFrequency'] ?? null) instanceof \BackedEnum) {
            $attributes['sitemapChangeFrequency'] = $attributes['sitemapChangeFrequency']->value;
        }
        Validator::make($attributes, $rules)->validate();
        $attributes += ['parentId' => null, 'site' => 'default', 'kind' => PageKind::Static, 'resource' => null,
            'status' => PageStatus::Draft, 'position' => 0, 'isNavigable' => true, 'sitemapIncluded' => true,
            'sitemapPriority' => null, 'sitemapChangeFrequency' => null, 'publishedAt' => null, 'expiresAt' => null, 'translations' => []];
        /** @var array{key?:string,slug:string,parentId:?string,site:string,kind:PageKind|string,resource:?string,status:PageStatus|string,position:int,isNavigable:bool,sitemapIncluded:bool,sitemapPriority:?string,sitemapChangeFrequency:?string,publishedAt:?string,expiresAt:?string,translations:array<string,array{title:string,navigationLabel?:?string,summary?:?string}>} $attributes */
        $attributes['kind'] = $attributes['kind'] instanceof PageKind ? $attributes['kind'] : PageKind::from($attributes['kind']);
        $attributes['status'] = $attributes['status'] instanceof PageStatus ? $attributes['status'] : PageStatus::from($attributes['status']);

        /** @var PageMutation $attributes */
        return $attributes;
    }

    /** @param array<string,array<string,mixed>> $translations */
    private function replaceTranslations(Page $page, array $translations): void
    {
        $definition = $page->translationDefinition();
        foreach ($translations as $locale => $values) {
            $this->translations->upsert($page, $definition, $locale, $values);
        }
        $this->translations->deleteExcept($page, $definition, array_keys($translations));
    }

    private function assertRevision(int $revision): void
    {
        if ($revision < 1) {
            throw new InvalidArgumentException('A positive expected Page revision is required.');
        }
    }

    /** @return array{type:?string,id:int|string|null,system:bool} */
    private function actorFacts(Authenticatable|SystemActor $actor): array
    {
        if ($actor instanceof SystemActor) {
            return ['type' => 'system', 'id' => null, 'system' => true];
        }
        $identifier = $actor->getAuthIdentifier();

        return ['type' => $actor instanceof Model ? $actor->getMorphClass() : $actor::class,
            'id' => is_int($identifier) || is_string($identifier) ? $identifier : null, 'system' => false];
    }
}
