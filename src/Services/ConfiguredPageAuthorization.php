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
 * Allows public reads of eligible pages and requires an explicit policy for mutations.
 *
 * @internal
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
            $visible = $page->newQuery()
                ->whereKey($page->getKey())
                ->publiclyVisible()
                ->exists();

            if ($visible) {
                return;
            }
        }

        if ($ability === PageAbility::ViewNavigation) {
            return;
        }

        throw new AuthorizationException(
            "Page ability [{$ability->value}] requires a consumer authorization binding.",
        );
    }
}
