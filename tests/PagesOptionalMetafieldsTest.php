<?php

declare(strict_types=1);

use Nvl\Data\Services\TypeScriptSourceInspector;
use Nvl\Metafields\Providers\MetafieldsServiceProvider;
use Nvl\Pages\Actions\CreatePageAction;
use Nvl\Pages\Actions\GetPageEditorBootstrapAction;
use Nvl\Pages\Contracts\PageMetafields;
use Nvl\Pages\Data\Mutations\CreatePageData;
use Nvl\Pages\Data\PageActorData;
use Nvl\Pages\Models\Page;
use Nvl\Pages\Tests\StandalonePagesTestCase;
use Nvl\Seo\Contracts\SeoAuthorization;

uses(StandalonePagesTestCase::class);

it('boots Pages and resolves an empty metafields section without the optional provider', function (): void {
    expect(app()->getLoadedProviders()[MetafieldsServiceProvider::class] ?? false)->toBeFalse()
        ->and(app(PageMetafields::class)->fields(new Page, 'en'))->toBe([])
        ->and(class_uses_recursive(Page::class))->not->toContain('Nvl\\Metafields\\Traits\\HasMetafields')
        ->and(fn () => (new Page)->metafields())->toThrow(InvalidArgumentException::class, 'requires the loaded provider');
});

it('keeps the editor DTO independent of optional Metafields display classes', function (): void {
    $symbols = array_column(app(TypeScriptSourceInspector::class)->symbols(), 'phpType');
    expect($symbols)->toContain('Nvl\\Pages\\Data\\PageEditorBootstrapData', 'Nvl\\Pages\\Data\\PageMetafieldFieldData')
        ->not->toContain('Nvl\\Metafields\\Data\\OwnerMetafieldField');
});

it('serializes a complete editor bootstrap with an empty Metafields section', function (): void {
    $authorization = Mockery::mock(SeoAuthorization::class);
    $authorization->shouldReceive('authorize')->once();
    app()->instance(SeoAuthorization::class, $authorization);
    $page = app(CreatePageAction::class)->execute(new CreatePageData('independent-editor', 'independent-editor'), PageActorData::system());
    $editor = app(GetPageEditorBootstrapAction::class)->execute($page->id, 'en', PageActorData::system());

    expect($editor->toArray()['metafields'])->toBe([])
        ->and($editor->page->id)->toBe($page->id)
        ->and($editor->content->placements)->toBe([]);
});

it('rejects explicitly requiring an unavailable Metafields adapter', function (): void {
    config()->set('pages.integrations.metafields', true);
    expect(fn () => app(PageMetafields::class))->toThrow(InvalidArgumentException::class, 'requires the loaded provider');
});
