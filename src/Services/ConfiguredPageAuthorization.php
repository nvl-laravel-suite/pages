<?php

declare(strict_types=1);

namespace Nvl\Pages\Services;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Nvl\Pages\Contracts\PageAuthorization;
use Nvl\Pages\Enums\PageAbility;
use Nvl\Pages\Models\Page;
use Nvl\Support\Enums\PublicActor;
use Nvl\Support\Enums\SystemActor;
use Nvl\Support\Ownership\Services\OwnerSiteContext;

/**
 * Reusable public Page policy; compose it with host policy for non-public capabilities.
 *
 * @api
 */
final class ConfiguredPageAuthorization implements PageAuthorization
{
    /**
     * Allow eligible public reads while requiring consumer policy for management.
     */
    public function authorize(
        PageAbility $ability,
        Authenticatable|SystemActor|PublicActor $actor,
        ?Page $page = null,
        array $context = [],
    ): void {
        if (OwnerSiteContext::current() !== null
            && ! in_array($ability, [PageAbility::View, PageAbility::ViewNavigation], true)) {
            throw new AuthorizationException('Public admission does not grant Page management rights.');
        }
        if ($ability === PageAbility::View && $page instanceof Page) {
            $prototype = PageModelRegistry::newInstance();
            $key = $prototype->getKeyName();
            if (! $page->exists || $page::class !== $prototype::class
                || $page->getKey() === null || $page->isDirty($key)
                || $page->getRawOriginal($key) !== $page->getKey()
                || $page->getKeyName() !== $key || $page->getTable() !== $prototype->getTable()
                || $page->getConnection() !== $prototype->getConnection()) {
                throw new AuthorizationException('Public Page policy requires an unchanged canonical persisted identity.');
            }
            $query = PageModelRegistry::query()
                ->whereKey($page->getKey())
                ->publiclyVisible();
            $site = OwnerSiteContext::current();
            if ($site !== null) {
                $query->where('site', $site->site);
            }
            $visible = $query->exists();

            if ($visible) {
                return;
            }
        }

        if ($ability === PageAbility::ViewNavigation) {
            $verified = OwnerSiteContext::current();
            if ($verified !== null && ($context['site'] ?? $page?->site) !== $verified->site) {
                throw new AuthorizationException('Page navigation requires its verified public site.');
            }

            return;
        }

        throw new AuthorizationException(
            "Page ability [{$ability->value}] requires a consumer authorization binding.",
        );
    }
}
