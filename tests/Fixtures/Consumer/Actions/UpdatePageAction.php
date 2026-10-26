<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Actions;

use Nvl\Pages\Models\Page;
use Nvl\Pages\Services\PageLifecycle;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\Mutations\UpdatePageData;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\PageActorData;

/** Consumer mapping into the complete native Page mutation. */
final readonly class UpdatePageAction
{
    public function __construct(private PageLifecycle $pages) {}

    public function execute(Page|string $page, UpdatePageData $data, PageActorData $actor): Page
    {
        return $this->pages->update($page, [
            'slug' => $data->slug,
            'kind' => $data->kind,
            'resource' => $data->resource,
            'status' => $data->status,
            'position' => $data->position,
            'isNavigable' => $data->isNavigable,
            'sitemapIncluded' => $data->sitemapIncluded,
            'sitemapPriority' => $data->sitemapPriority,
            'sitemapChangeFrequency' => $data->sitemapChangeFrequency?->value,
            'publishedAt' => $data->publishedAt,
            'expiresAt' => $data->expiresAt,
            'translations' => $data->translations,
        ], $data->expectedRevision, $actor->native());
    }
}
