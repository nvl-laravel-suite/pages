<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer;

use Illuminate\Contracts\Auth\Authenticatable;
use Nvl\Pages\Contracts\PageAuthorization;
use Nvl\Pages\Enums\PageAbility;
use Nvl\Pages\Models\Page;
use Nvl\Pages\Services\ConfiguredPageAuthorization;
use Nvl\Support\Enums\PublicActor;
use Nvl\Support\Enums\SystemActor;
use Nvl\Support\Ownership\Services\OwnerSiteContext;

final class ConsumerPageAuthorization implements PageAuthorization
{
    public function __construct(private bool $allowPreview = true) {}

    public function authorize(PageAbility $ability, Authenticatable|SystemActor|PublicActor $actor, ?Page $page = null, array $context = []): void
    {
        if (OwnerSiteContext::current() !== null) {
            (new ConfiguredPageAuthorization)->authorize($ability, $actor, $page, $context);

            return;
        }
        if ($actor === SystemActor::Background) {
            return;
        }
        if ($this->allowPreview && $ability === PageAbility::Preview && $actor instanceof PreviewActor && $actor->exists) {
            return;
        }
        (new ConfiguredPageAuthorization)->authorize($ability, $actor, $page, $context);
    }
}
