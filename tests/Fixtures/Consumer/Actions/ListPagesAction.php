<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Actions;

use Illuminate\Pagination\LengthAwarePaginator;
use Nvl\Pages\Models\Page;
use Nvl\Pages\Services\PageReader;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\PageActorData;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\PageData;

final readonly class ListPagesAction
{
    public function __construct(private PageReader $pages) {}

    /**
     * @param  array<string,mixed>  $filters
     * @return LengthAwarePaginator<int,PageData>
     */
    public function execute(
        array $filters,
        string $site,
        PageActorData $actor,
        int $perPage = 25,
    ): LengthAwarePaginator {
        /** @var array{status?:string|list<string>,kind?:string|list<string>,parent?:?string,navigable?:bool,sort?:string} $filters */
        $paginator = $this->pages->paginate($site, $actor->native(), $filters, $perPage);

        return $paginator->through(fn (Page $page): PageData => PageData::fromModel($page));
    }
}
