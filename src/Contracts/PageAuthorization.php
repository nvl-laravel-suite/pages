<?php

declare(strict_types=1);

namespace Nvl\Pages\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Nvl\Pages\Enums\PageAbility;
use Nvl\Pages\Models\Page;
use Nvl\Support\Enums\PublicActor;
use Nvl\Support\Enums\SystemActor;

/**
 * Consumer-owned authorization boundary for every page operation.
 *
 * @api
 */
interface PageAuthorization
{
    /**
     * Authorize one page capability for an actor and optional domain context.
     *
     * @param  array{site?:string,parentId?:?string,path?:string,locale?:string,resourceId?:?string,...}  $context
     */
    public function authorize(
        PageAbility $ability,
        Authenticatable|SystemActor|PublicActor $actor,
        ?Page $page = null,
        array $context = [],
    ): void;
}
