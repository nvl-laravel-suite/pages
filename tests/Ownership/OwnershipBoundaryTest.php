<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Nvl\Pages\Actions\CreatePageAction;
use Nvl\Pages\Data\Mutations\CreatePageData;
use Nvl\Pages\Data\PageActorData;
use Nvl\Pages\Definitions\Tables\PagesTables;
use Nvl\Pages\Exceptions\PageHierarchyException;
use Nvl\Pages\Services\PageModelRegistry;
use Nvl\Pages\Tests\Fixtures\OwnershipPage;
use Nvl\Support\Ownership\Exceptions\OwnershipViolation;

it('partitions equal page keys slugs paths and inherited copy while rejecting foreign parents', function (): void {
    $pages = [];
    foreach ([7, 8] as $id) {
        $pages[$id] = $this->owned($id, fn () => app(CreatePageAction::class)->execute(new CreatePageData(key: 'about', slug: 'about', translations: ['en' => ['title' => 'Owner '.$id]]), PageActorData::system()));
    }
    $this->owned(7, function () use ($pages): void {
        expect(OwnershipPage::count())->toBe(1)->and(OwnershipPage::firstOrFail()->displayTitle('en'))->toBe('Owner 7');
        expect(fn () => $pages[8]->delete())->toThrow(OwnershipViolation::class);
        expect(fn () => app(CreatePageAction::class)->execute(new CreatePageData(key: 'child', slug: 'child', parentId: $pages[8]->id), PageActorData::system()))->toThrow(PageHierarchyException::class);
    });
});

it('keeps ownership tree locks independent from released tenant lock rows', function (): void {
    $table = PagesTables::get(PagesTables::TreeLocks);
    $schema = PageModelRegistry::newInstance()->getConnection()->getSchemaBuilder();
    $schema->drop($table);
    $schema->create($table, static function (Blueprint $blueprint): void {
        $blueprint->uuid('tenant_id');
        $blueprint->string('site', 64);
        $blueprint->primary(['tenant_id', 'site']);
    });
    $this->owned(7, function (): void {
        expect(app(CreatePageAction::class)->execute(new CreatePageData(key: 'legacy-lock', slug: 'legacy-lock'), PageActorData::system())->key)->toBe('legacy-lock');
    });
});

it('enforces owner and site on raw native tree references', function (): void {
    $a = $this->owned(7, fn () => app(CreatePageAction::class)->execute(new CreatePageData(key: 'raw-a', slug: 'a'), PageActorData::system()));
    $b = $this->owned(8, fn () => app(CreatePageAction::class)->execute(new CreatePageData(key: 'raw-b', slug: 'b'), PageActorData::system()));
    $otherSite = $this->owned(7, fn () => app(CreatePageAction::class)->execute(new CreatePageData(key: 'raw-site', slug: 'site', site: 'another'), PageActorData::system()));
    foreach ([$b, $otherSite] as $parent) {
        expect(fn () => $a->getConnection()->table($a->getTable())->where('id', $a->id)->update(['parent_id' => $parent->id]))->toThrow(QueryException::class);
    }
});
