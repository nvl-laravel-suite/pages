<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Nvl\Pages\Models\Page;
use Nvl\Pages\Tests\Fixtures\Consumer\Actions\CreatePageAction;
use Nvl\Pages\Tests\Fixtures\Consumer\Actions\DeletePageAction;
use Nvl\Pages\Tests\Fixtures\Consumer\Actions\GetPageAction;
use Nvl\Pages\Tests\Fixtures\Consumer\Actions\ListPagesAction;
use Nvl\Pages\Tests\Fixtures\Consumer\Actions\MovePageAction;
use Nvl\Pages\Tests\Fixtures\Consumer\Actions\PreviewPageAction;
use Nvl\Pages\Tests\Fixtures\Consumer\Actions\RestorePageAction;
use Nvl\Pages\Tests\Fixtures\Consumer\Actions\UpdatePageAction;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\Mutations\CreatePageData;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\Mutations\DeletePageData;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\Mutations\MovePageData;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\Mutations\RestorePageData;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\Mutations\UpdatePageData;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\PageData;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\Queries\PageIndexQueryData;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\Queries\PagePreviewQueryData;
use Nvl\Pages\Tests\Fixtures\Consumer\Support\PageActorFactory;
use Nvl\Support\Contracts\LocaleCatalog;

/**
 * Thin opt-in management endpoints over page Actions and DTOs.
 */
final class PagesManagementController extends Controller
{
    /**
     * Return a filtered site-scoped page management paginator.
     */
    public function index(
        Request $request,
        PageActorFactory $actors,
        ListPagesAction $action,
    ): JsonResponse {
        $data = PageIndexQueryData::fromInput($this->requestInput($request, queryOnly: true));
        $query = [];

        foreach ($request->query() as $key => $value) {
            if (is_string($key)) {
                $query[$key] = $value;
            }
        }

        unset($query['site'], $query['per_page']);
        $pages = $action->execute(
            $query,
            $data->site,
            $actors->fromRequest($request),
            $data->perPage,
        );

        return response()->json([
            'data' => array_map(
                static fn (PageData $page): array => $page->toArray(),
                $pages->items(),
            ),
            'meta' => [
                'current_page' => $pages->currentPage(),
                'last_page' => $pages->lastPage(),
                'per_page' => $pages->perPage(),
                'total' => $pages->total(),
            ],
        ]);
    }

    /**
     * Create one page from the validated mutation DTO.
     */
    public function store(
        Request $request,
        CreatePageAction $action,
        PageActorFactory $actors,
    ): JsonResponse {
        $page = $action->execute(
            CreatePageData::fromInput($this->requestInput($request)),
            $actors->fromRequest($request),
        );

        return response()->json(['data' => PageData::fromModel($page)->toArray()], 201);
    }

    /**
     * Return one authorized page management projection.
     */
    public function show(
        Request $request,
        Page $page,
        GetPageAction $action,
        PageActorFactory $actors,
    ): JsonResponse {
        return response()->json([
            'data' => $action
                ->execute($page, $actors->fromRequest($request))
                ->toArray(),
        ]);
    }

    /**
     * Replace one page from the validated mutation DTO.
     */
    public function update(
        Request $request,
        Page $page,
        UpdatePageAction $action,
        PageActorFactory $actors,
    ): JsonResponse {
        $page = $action->execute(
            $page,
            UpdatePageData::fromInput($this->requestInput($request)),
            $actors->fromRequest($request),
        );

        return response()->json(['data' => PageData::fromModel($page)->toArray()]);
    }

    /**
     * Reparent or reorder one page from the validated mutation DTO.
     */
    public function move(
        Request $request,
        Page $page,
        MovePageAction $action,
        PageActorFactory $actors,
    ): JsonResponse {
        $page = $action->execute(
            $page,
            MovePageData::fromInput($this->requestInput($request)),
            $actors->fromRequest($request),
        );

        return response()->json(['data' => PageData::fromModel($page)->toArray()]);
    }

    /**
     * Soft-delete one page using its exact revision.
     */
    public function destroy(
        Request $request,
        Page $page,
        DeletePageAction $action,
        PageActorFactory $actors,
    ): JsonResponse {
        return response()->json([
            'data' => [
                'deleted' => $action->execute(
                    $page,
                    DeletePageData::fromInput($this->requestInput($request)),
                    $actors->fromRequest($request),
                ),
            ],
        ]);
    }

    /**
     * Restore one soft-deleted page using its exact revision.
     */
    public function restore(
        Request $request,
        string $page,
        RestorePageAction $action,
        PageActorFactory $actors,
    ): JsonResponse {
        $restored = $action->execute(
            $page,
            RestorePageData::fromInput($this->requestInput($request)),
            $actors->fromRequest($request),
        );

        return response()->json(['data' => PageData::fromModel($restored)->toArray()]);
    }

    /**
     * Resolve an authorized preview for a draft or public page.
     */
    public function preview(
        Request $request,
        string $path,
        PreviewPageAction $action,
        PageActorFactory $actors,
        LocaleCatalog $locales,
    ): JsonResponse {
        $data = PagePreviewQueryData::fromInput($this->requestInput($request, queryOnly: true));
        $locale = $locales->assertSupported($data->locale);

        return response()->json([
            'data' => $action->execute(
                $path,
                $data->site,
                $locale,
                $actors->fromRequest($request),
            )->toArray(),
        ]);
    }

    /** @return array<string, mixed> */
    private function requestInput(Request $request, bool $queryOnly = false): array
    {
        $payload = [];
        foreach ($queryOnly ? $request->query() : $request->all() as $key => $value) {
            if (is_string($key)) {
                $payload[$key] = $value;
            }
        }

        return $payload;
    }
}
