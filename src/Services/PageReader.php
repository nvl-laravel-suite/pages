<?php

declare(strict_types=1);

namespace Nvl\Pages\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Nvl\Pages\Contracts\PageAuthorization;
use Nvl\Pages\Enums\PageAbility;
use Nvl\Pages\Enums\PageKind;
use Nvl\Pages\Enums\PageStatus;
use Nvl\Pages\Enums\PublicChildPageOrder;
use Nvl\Pages\Models\Page;
use Nvl\Pages\Support\PagesConfiguration;
use Nvl\Support\Auth\CanonicalActorResolver;
use Nvl\Support\Contracts\LocaleCatalog;
use Nvl\Support\Enums\PublicActor;
use Nvl\Support\Enums\SystemActor;
use Nvl\Support\Ownership\Exceptions\OwnershipViolation;
use Nvl\Support\Ownership\Services\OwnerSiteContext;

/**
 * Owns authorized Page model reads and public navigation.
 *
 * @api
 */
final readonly class PageReader
{
    public function __construct(private CanonicalActorResolver $actors, private PageAuthorization $authorization, private PageIdentityGuard $identities,
        private LocaleCatalog $locales, private PageNavigationBuilder $builder) {}

    /** Return the canonical authorized Page with localized copy, including eligible anonymous reads. */
    public function get(Page|string $page, Authenticatable|SystemActor|PublicActor $actor): Page
    {
        if ($actor instanceof Authenticatable) {
            $actor = $this->actors->resolve($actor);
        }
        $query = PageModelRegistry::query()->with('translations');
        if ($actor instanceof PublicActor) {
            $query->publiclyVisible();
            $site = OwnerSiteContext::current();
            if ($site !== null) {
                $query->where('site', $site->site);
            }
        }
        $page = $query->findOrFail($page instanceof Page ? $page->id : $this->identities->id($page));
        $this->authorization->authorize(PageAbility::View, $actor, $page);

        return $page;
    }

    /**
     * Return the existing bounded management paginator using native equality filters.
     *
     * @param  array{status?:PageStatus|string|list<string>,kind?:PageKind|string|list<string>,parent?:?string,navigable?:bool,sort?:string}  $filters
     * @return LengthAwarePaginator<int,Page>
     */
    public function paginate(string $site, Authenticatable|SystemActor $actor, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        if ($actor instanceof Authenticatable) {
            $actor = $this->actors->resolve($actor);
        }
        $site = $this->identities->site($site);
        $filters = $this->validateFilters($filters);
        $this->authorization->authorize(PageAbility::List, $actor, context: ['site' => $site]);
        $query = PageModelRegistry::query()->where('site', $site)->with('translations');
        foreach (['status', 'kind', 'parent', 'navigable'] as $name) {
            if (! array_key_exists($name, $filters)) {
                continue;
            }
            $value = $filters[$name];
            $column = ['status' => 'status', 'kind' => 'kind', 'parent' => 'parent_id', 'navigable' => 'is_navigable'][$name];
            if ($value instanceof \BackedEnum) {
                $value = $value->value;
            }
            is_array($value) ? $query->whereIn($column, $value) : $query->where($column, $value);
        }
        $sort = $filters['sort'] ?? 'position';
        $column = ['position' => 'position', 'path' => 'path', 'created' => 'created_at', 'updated' => 'updated_at', 'id' => 'id'][ltrim($sort, '-')]
            ?? throw new InvalidArgumentException('Unsupported Page sort.');
        $query->orderBy($column, str_starts_with($sort, '-') ? 'desc' : 'asc')->orderBy('path')->orderBy('id');

        return $query->paginate(max(1, min(PagesConfiguration::limit('maximum_per_page', 100), $perPage)));
    }

    /** @return Collection<int,Page> */
    public function children(string $parentId, string $site, string $locale, int $limit = 50, ?PageKind $kind = null, PublicChildPageOrder $order = PublicChildPageOrder::Sibling): Collection
    {
        $parentId = $this->identities->id($parentId);
        $site = $this->publicSite($site);
        $locale = $this->locales->assertSupported($locale);
        $at = now();
        $parent = PageModelRegistry::query()
            ->where('site', $site)
            ->publiclyVisible($at)
            ->findOrFail($parentId);
        $authorizationContext = ['site' => $site, 'locale' => $locale];
        $this->authorization->authorize(
            PageAbility::ViewNavigation,
            PublicActor::Anonymous,
            $parent,
            $authorizationContext,
        );
        $maximum = min(
            100,
            PagesConfiguration::limit('maximum_public_children', 100),
        );

        $query = PageModelRegistry::query()
            ->where('site', $site)
            ->where('parent_id', $parent->id)
            ->publiclyVisible($at)
            ->with('translations');

        if ($kind instanceof PageKind) {
            $query->where('kind', $kind);
        }

        match ($order) {
            PublicChildPageOrder::Sibling => $query->ordered(),
            PublicChildPageOrder::Newest => $query
                ->orderByRaw('COALESCE(published_at, created_at) DESC')
                ->orderBy('position')
                ->orderBy('id'),
        };

        return $query
            ->limit(max(1, min($maximum, $limit)))
            ->get();
    }

    /** @return array{site:string,locale:string,items:list<array{id:string,key:string,path:string,url:string,label:string,children:list<array<string,mixed>>}>} */
    public function navigation(string $site, string $locale): array
    {
        $site = $this->publicSite($site);
        $locale = $this->locales->assertSupported($locale);
        $this->authorization->authorize(
            PageAbility::ViewNavigation,
            PublicActor::Anonymous,
            context: ['site' => $site, 'locale' => $locale],
        );
        $pages = PageModelRegistry::query()
            ->where('site', $site)
            ->where('kind', PageKind::Static)
            ->publiclyVisible()
            ->with('translations')
            ->ordered()
            ->get();

        return ['site' => $site, 'locale' => $locale, 'items' => $this->builder->build($pages, $locale)];
    }

    /** @return array{site:string,key:string,available:bool,conflictingPageId:?string} */
    public function keyAvailable(string $site, string $key, Authenticatable|SystemActor $actor, ?string $exceptId = null): array
    {
        if ($actor instanceof Authenticatable) {
            $actor = $this->actors->resolve($actor);
        }
        $site = $this->identities->site($site);
        $key = $this->identities->key($key);
        $exceptId = $exceptId !== null ? $this->identities->id($exceptId) : null;
        $this->authorization->authorize(
            PageAbility::List,
            $actor,
            context: ['site' => $site],
        );
        $conflict = PageModelRegistry::query()
            ->withTrashed()
            ->where('key', $key)
            ->first(['id', 'site']);
        $exceptsSameSiteConflict = $conflict instanceof Page
            && $conflict->site === $site
            && $conflict->id === $exceptId;
        $available = ! ($conflict instanceof Page) || $exceptsSameSiteConflict;
        $conflictingPageId = null;

        if (! $available && $conflict->site === $site) {
            $conflictingPageId = $conflict->id;
        }

        return ['site' => $site, 'key' => $key, 'available' => $available, 'conflictingPageId' => $conflictingPageId];
    }

    /** Normalize a public selector without letting a host policy broaden verified site admission. */
    private function publicSite(string $site): string
    {
        $site = $this->identities->site($site);
        $verified = OwnerSiteContext::current();
        if ($verified !== null && $verified->site !== $site) {
            throw new OwnershipViolation('The Page site differs from the verified public site.');
        }

        return $site;
    }

    /**
     * @param  array<string,mixed>  $filters
     * @return array{status?:PageStatus|string|list<string>,kind?:PageKind|string|list<string>,parent?:?string,navigable?:bool,sort?:string}
     */
    private function validateFilters(array $filters): array
    {
        if (array_diff(array_keys($filters), ['status', 'kind', 'parent', 'navigable', 'sort']) !== []) {
            throw new InvalidArgumentException('Unknown Page filters.');
        }
        foreach (['status' => PageStatus::class, 'kind' => PageKind::class] as $field => $enum) {
            if (! array_key_exists($field, $filters)) {
                continue;
            }
            foreach (is_array($filters[$field]) ? $filters[$field] : [$filters[$field]] as $value) {
                if ($value instanceof \BackedEnum) {
                    $value = $value->value;
                }
                if (! is_string($value) || ! in_array($value, array_column($enum::cases(), 'value'), true)) {
                    throw new InvalidArgumentException('Invalid Page filter value.');
                }
            }
        }
        if (array_key_exists('navigable', $filters) && ! is_bool($filters['navigable'])) {
            throw new InvalidArgumentException('The navigable Page filter must be boolean.');
        }
        if (array_key_exists('parent', $filters) && $filters['parent'] !== null) {
            if (! is_string($filters['parent'])) {
                throw new InvalidArgumentException('The parent Page filter must be a canonical identifier.');
            }
            $filters['parent'] = $this->identities->id($filters['parent']);
        }

        /** @var array{status?:PageStatus|string|list<string>,kind?:PageKind|string|list<string>,parent?:?string,navigable?:bool,sort?:string} $filters */
        return $filters;
    }
}
