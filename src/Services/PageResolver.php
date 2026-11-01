<?php

declare(strict_types=1);

namespace Nvl\Pages\Services;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Nvl\Pages\Contracts\PageAuthorization;
use Nvl\Pages\Enums\PageAbility;
use Nvl\Pages\Models\Page;
use Nvl\Pages\Ownership\PagesOwnership;
use Nvl\Support\Auth\CanonicalActorResolver;
use Nvl\Support\Contracts\LocaleCatalog;
use Nvl\Support\Enums\PublicActor;
use Nvl\Support\Ownership\Services\OwnerSiteContext;

/**
 * Resolves canonical published pages or authorized private previews.
 *
 * @api
 */
final readonly class PageResolver
{
    public function __construct(private PageMatcher $matcher, private PageAuthorization $authorization,
        private PageIdentityGuard $identities, private LocaleCatalog $locales, private CanonicalActorResolver $actors) {}

    /**
     * Resolve a public path under explicit public authorization.
     *
     * @param  array<string,mixed>  $context
     * @return array{page:Page,resource:array{type:string,id:string,payload:array<string,mixed>}|null}
     */
    public function resolve(string $path, string $site, string $locale, array $context = []): array
    {
        $site = $this->identities->site($site);
        $locale = $this->locales->assertSupported($locale);
        if (PagesOwnership::declared() && OwnerSiteContext::require()->site !== $site) {
            throw PagesOwnership::violation('The Page site differs from the verified public site.');
        }
        $match = $this->matcher->resolve($path, $site, $locale, authorizePage: function (Page $page) use ($context, $site, $path, $locale): void {
            $this->authorization->authorize(PageAbility::View, PublicActor::Anonymous, $page, [
                'parentId' => $page->parent_id, 'site' => $site, 'path' => $path, 'locale' => $locale, 'resourceId' => null,
            ] + $context);
        });
        /** @var array{site:string,parentId:?string,path:string,locale:string,resourceId:?string,...} $facts */
        $facts = [...$context, 'parentId' => $match['page']->parent_id, 'site' => $site, 'path' => $path, 'locale' => $locale, 'resourceId' => $match['resource']['id'] ?? null];
        if ($match['resource'] !== null) {
            $this->authorization->authorize(PageAbility::View, PublicActor::Anonymous, $match['page'], $facts);
        }

        return $match;
    }

    /**
     * Resolve a private preview only for an explicitly supplied persisted actor.
     *
     * @param  array<string,mixed>  $context
     * @return array{page:Page,resource:array{type:string,id:string,payload:array<string,mixed>}|null}
     */
    public function preview(string $path, string $site, string $locale, Authenticatable $actor, array $context = []): array
    {
        if (! $actor instanceof Model) {
            throw new AuthorizationException('Page previews require a persisted actor.');
        }
        $actor = $this->actors->resolve($actor);
        $site = $this->identities->site($site);
        $locale = $this->locales->assertSupported($locale);
        $match = $this->matcher->resolve($path, $site, $locale, publicOnly: false, authorizePage: function (Page $page) use ($actor, $context, $site, $path, $locale): void {
            $this->authorization->authorize(PageAbility::Preview, $actor, $page, [
                'parentId' => $page->parent_id, 'site' => $site, 'path' => $path, 'locale' => $locale, 'resourceId' => null,
            ] + $context);
        });
        /** @var array{site:string,parentId:?string,path:string,locale:string,resourceId:?string,...} $facts */
        $facts = [...$context, 'parentId' => $match['page']->parent_id, 'site' => $site, 'path' => $path, 'locale' => $locale, 'resourceId' => $match['resource']['id'] ?? null];
        if ($match['resource'] !== null) {
            $this->authorization->authorize(PageAbility::Preview, $actor, $match['page'], $facts);
        }

        return $match;
    }
}
