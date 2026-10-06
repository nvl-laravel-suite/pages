<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Nvl\Metafields\Models\MetafieldDefinition;
use Nvl\Metafields\Support\MetafieldOwnerRegistry;
use Nvl\Pages\Actions\CreatePageAction;
use Nvl\Pages\Data\Mutations\CreatePageData;
use Nvl\Pages\Data\PageActorData;
use Nvl\Pages\Models\Page;
use Nvl\Pages\Services\PageResourceRegistry;
use Nvl\Pages\Tests\Fixtures\TestPageResource;
use Nvl\Pages\Tests\Fixtures\TestPageResourceHandler;
use Nvl\Seo\Services\SeoOwnerRegistry;
use Nvl\Support\OwnerRegistry;

beforeEach(function (): void {
    $this->originalOwnerMorphMap = Relation::morphMap();
});

afterEach(function (): void {
    Relation::morphMap($this->originalOwnerMorphMap, false);
});

it('keeps page handlers separate from their shared owner identities', function (): void {
    config()->set('nvl-core.owners', ['record' => TestPageResource::class]);
    $registry = app()->build(PageResourceRegistry::class);
    $registry->register('records.detail', TestPageResourceHandler::class, 'record');

    expect($registry->get('records.detail'))->toBeInstanceOf(TestPageResourceHandler::class)
        ->and($registry->has('record'))->toBeFalse()
        ->and(fn () => $registry->assertOwner('records.detail', new Page))->toThrow(InvalidArgumentException::class);

    $registry->assertOwner('records.detail', new TestPageResource);
});

it('shares one owner across page seo and metafield capabilities without widening their allowlists', function (): void {
    config()->set('nvl-core.owners', ['record' => TestPageResource::class]);
    config()->set('nvl-seo.owners', ['records.seo' => 'record']);
    config()->set('nvl-metafields.owners', ['record' => ['label' => 'Records', 'sections' => ['content']]]);
    $pages = app()->build(PageResourceRegistry::class);
    $pages->register('records.detail', TestPageResourceHandler::class, 'record');

    expect(app(OwnerRegistry::class)->model('record'))->toBe(TestPageResource::class)
        ->and(app(SeoOwnerRegistry::class)->modelClass('records.seo'))->toBe(TestPageResource::class)
        ->and(app(MetafieldOwnerRegistry::class)->configurationForType('record')['model'])->toBe(TestPageResource::class)
        ->and($pages->has('record'))->toBeFalse()
        ->and(fn () => app(MetafieldOwnerRegistry::class)->configurationForType('records.seo'))
        ->toThrow(InvalidArgumentException::class);
});

it('preserves the optional page metafields relation and its registered morph identity', function (): void {
    $page = app(CreatePageAction::class)->execute(new CreatePageData('legacy-relation', 'legacy-relation'), PageActorData::system());
    $definition = MetafieldDefinition::factory()->create();
    $relation = $page->metafields();
    $field = $relation->create(['definition_id' => $definition->id, 'value' => 'legacy-value']);

    expect($relation)->toBeInstanceOf(MorphMany::class)
        ->and($field->metafieldable_type)->toBe($page->getMorphClass())
        ->and($field->metafieldable_id)->toBe($page->id)
        ->and($field->metafieldable?->is($page))->toBeTrue()
        ->and($page->metafields()->sole()->is($field))->toBeTrue();

    config()->set('nvl-pages.integrations.metafields', false);
    expect(fn () => $page->metafields())->toThrow(InvalidArgumentException::class, 'disabled');
});
