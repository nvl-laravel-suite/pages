<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures;

use Illuminate\Contracts\Auth\Authenticatable;
use Nvl\Pages\Contracts\PageAuthorization;
use Nvl\Pages\Enums\PageAbility;
use Nvl\Pages\Models\Page;
use Nvl\Support\Enums\PublicActor;
use Nvl\Support\Enums\SystemActor;

/**
 * Records page authorization checks for package action tests.
 */
final class RecordingPageAuthorization implements PageAuthorization
{
    /** @var list<PageAbility> */
    public array $abilities = [];

    /**
     * Record one authorized package capability.
     */
    public function authorize(
        PageAbility $ability,
        Authenticatable|SystemActor|PublicActor $actor,
        ?Page $page = null,
        array $context = [],
    ): void {
        $this->abilities[] = $ability;
    }
}
