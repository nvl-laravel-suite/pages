<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Nvl\Ownership\Services\PublicOwnerSiteRunner;
use Nvl\Support\Ownership\Exceptions\OwnershipViolation;

final readonly class ResolvePublicOwnerSite
{
    public function __construct(private PublicOwnerSiteRunner $sites) {}

    public function handle(Request $request, Closure $next): mixed
    {
        try {
            return $this->sites->run($request->getSchemeAndHttpHost(), fn () => $next($request));
        } catch (OwnershipViolation) {
            abort(404);
        }
    }
}
