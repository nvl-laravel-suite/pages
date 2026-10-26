<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Nvl\Pages\Tests\Fixtures\Consumer\Actions\ResolvePageAction;
use Nvl\Pages\Tests\Fixtures\Consumer\Contracts\PageRequestContextResolver;
use Nvl\Pages\Tests\Fixtures\Consumer\Support\PageActorFactory;

/**
 * Thin optional HTTP adapter for resolving one headless page.
 */
final class PublicPagesController extends Controller
{
    /**
     * Resolve one public page using trusted site and locale context.
     */
    public function __invoke(
        Request $request,
        string $path,
        ResolvePageAction $action,
        PageActorFactory $actors,
        PageRequestContextResolver $contextResolver,
    ): JsonResponse {
        $context = $contextResolver->resolve($request);

        return response()->json([
            'data' => $action->execute(
                $path,
                $context->site,
                $context->locale,
                $actors->fromRequest($request),
            )->toArray(),
        ]);
    }
}
