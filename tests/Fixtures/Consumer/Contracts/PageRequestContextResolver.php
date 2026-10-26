<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Contracts;

use Illuminate\Http\Request;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\PageRequestContextData;

/**
 * Resolves trusted public site and locale context at the HTTP boundary.
 *
 * @api
 */
interface PageRequestContextResolver
{
    /**
     * Resolve trusted public page context from one request.
     */
    public function resolve(Request $request): PageRequestContextData;
}
