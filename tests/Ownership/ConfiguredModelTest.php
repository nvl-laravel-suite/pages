<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\Relation;
use Nvl\Pages\Actions\CreatePageAction;
use Nvl\Pages\Data\Mutations\CreatePageData;
use Nvl\Pages\Data\PageActorData;
use Nvl\Pages\Models\Page;
use Nvl\Pages\Tests\Fixtures\OwnershipPage;
use Nvl\Support\Ownership\Exceptions\OwnershipViolation;

it('uses the configured subclass throughout writes translations tree relations and native identity', function (): void {
    $this->owned(7, function (): void {
        $parent = app(CreatePageAction::class)->execute(new CreatePageData(key: 'home', slug: 'home', translations: ['en' => ['title' => 'Home']]), PageActorData::system());
        $child = app(CreatePageAction::class)->execute(new CreatePageData(key: 'about', slug: 'about', parentId: $parent->id, translations: ['en' => ['title' => 'About']]), PageActorData::system());
        expect($parent)->toBeInstanceOf(OwnershipPage::class)
            ->and($child->parent)->toBeInstanceOf(OwnershipPage::class)
            ->and($parent->children->first())->toBeInstanceOf(OwnershipPage::class)
            ->and($child->translations->first()->page)->toBeInstanceOf(OwnershipPage::class)
            ->and(Relation::getMorphedModel($parent->getMorphClass()))->toBe(OwnershipPage::class);
        expect(fn () => Page::query()->count())->toThrow(OwnershipViolation::class);
    });
});

it('creates the configured Page through the ordinary shipped factory', function (): void {
    $this->owned(7, function (): void {
        $page = OwnershipPage::factory()->create();
        expect($page)->toBeInstanceOf(OwnershipPage::class)->and($page->fresh()->company_id)->toBe(7);
    });
});
