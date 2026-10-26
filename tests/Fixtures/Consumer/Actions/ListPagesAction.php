<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Actions;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Nvl\Pages\Models\Page;
use Nvl\Pages\Services\PageReader;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\PageActorData;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\PageData;

final readonly class ListPagesAction
{
    public function __construct(private PageReader $pages) {}

    public function execute(
        array $filters,
        string $site,
        PageActorData $actor,
        int $perPage = 25,
    ): LengthAwarePaginator {
        return $this->pages->paginate($site, $actor->native(), $filters, $perPage)->through(fn (Page $page): PageData => PageData::fromModel($page));
    }
}
