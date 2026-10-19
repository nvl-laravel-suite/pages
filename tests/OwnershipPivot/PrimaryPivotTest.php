<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Nvl\Pages\Actions\CreatePageAction;
use Nvl\Pages\Data\Mutations\CreatePageData;
use Nvl\Pages\Data\PageActorData;
use Nvl\Pages\Tests\Fixtures\PivotPage;

it('uses native primary pivots across duplicate owner-local identities and inherited copy', function (): void {
    $roots = [];
    foreach ([7, 8] as $id) {
        $roots[$id] = $this->owned($id, fn () => app(CreatePageAction::class)->execute(new CreatePageData(key: 'same-key', slug: 'same'), PageActorData::system()));
    }
    $this->owned(7, function () use ($roots): void {
        expect(PivotPage::count())->toBe(1)->and(PivotPage::firstOrFail()->id)->toBe($roots[7]->id);
        expect(fn () => $roots[7]->getConnection()->table('pages_owned_primary')->where('page_id', $roots[7]->id)->update(['company_id' => 8]))->toThrow(QueryException::class);
    });
});
