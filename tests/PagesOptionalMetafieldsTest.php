<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\GenericUser;
use Nvl\Pages\Contracts\PageAuthorization;
use Nvl\Pages\Models\Page;
use Nvl\Pages\Providers\PagesServiceProvider;
use Nvl\Pages\Services\PageLifecycle;
use Nvl\Pages\Services\PageReader;
use Nvl\Pages\Services\PageResolver;
use Nvl\Pages\Services\PageResourceRegistry;
use Nvl\Pages\Tests\Fixtures\Consumer\PreviewActor;
use Nvl\Pages\Tests\Fixtures\RecordingPageAuthorization;
use Nvl\Pages\Tests\StandalonePagesTestCase;
use Nvl\Support\Enums\SystemActor;
use Nvl\Translatable\Services\TranslationResourceRegistry;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(StandalonePagesTestCase::class);

it('creates resolves and navigates plain localized Pages without Content or SEO providers', function (): void {
    $page = app(PageLifecycle::class)->create(['key' => 'plain', 'slug' => 'plain', 'status' => 'published',
        'translations' => ['en' => ['title' => 'Plain page']]], SystemActor::Background);
    $resolved = app(PageResolver::class)->resolve('plain', 'default', 'bg');
    expect($resolved['page'])->toBeInstanceOf(Page::class)->and($resolved['page']->id)->toBe($page->id)
        ->and($resolved['page']->displayTitle('bg'))->toBe('Plain page')->and($resolved['resource'])->toBeNull()
        ->and(app(PageReader::class)->navigation('default', 'en')['items'][0]['label'])->toBe('Plain page')
        ->and(app()->getLoadedProviders())->not->toHaveKeys(['Nvl\\Content\\Providers\\ContentServiceProvider', 'Nvl\\Seo\\Providers\\SeoServiceProvider']);
});

it('retains publication windows original not-found failures and explicit private-preview actors', function (): void {
    $page = app(PageLifecycle::class)->create(['key' => 'private', 'slug' => 'private', 'translations' => ['en' => ['title' => 'Draft']]], SystemActor::Background);
    expect(fn () => app(PageResolver::class)->resolve('private', 'default', 'en'))->toThrow(NotFoundHttpException::class)
        ->and(fn () => app(PageResolver::class)->resolve('../private', 'default', 'en'))->toThrow(NotFoundHttpException::class)
        ->and(fn () => app(PageResolver::class)->preview('private', 'default', 'en', new GenericUser(['id' => 1])))->toThrow(AuthorizationException::class);
    $actor = PreviewActor::query()->create();
    expect(fn () => app(PageResolver::class)->preview('private', 'default', 'en', $actor))->toThrow(AuthorizationException::class);
    app()->instance(PageAuthorization::class, new RecordingPageAuthorization);
    expect(app(PageResolver::class)->preview('private', 'default', 'en', $actor)['page']->id)->toBe($page->id);
    app(PageLifecycle::class)->update($page, ['slug' => 'private', 'kind' => 'static', 'status' => 'scheduled',
        'publishedAt' => now()->addHour()->toAtomString()], $page->revision, SystemActor::Background);
    expect(fn () => app(PageResolver::class)->resolve('private', 'default', 'en'))->toThrow(NotFoundHttpException::class);
});

it('validates native mutation fields before persistence and preserves host rollback', function (): void {
    expect(fn () => app(PageLifecycle::class)->create(['key' => 'unsafe', 'slug' => 'unsafe', 'owner_scope_key' => 'caller'], SystemActor::Background))->toThrow(InvalidArgumentException::class);
    $connection = (new Page)->getConnection();
    $connection->beginTransaction();
    app(PageLifecycle::class)->create(['key' => 'rollback', 'slug' => 'rollback'], SystemActor::Background);
    $connection->rollBack();
    expect(Page::query()->count())->toBe(0);
});

it('rejects explicitly enabled missing Content and SEO adapters', function (string $integration): void {
    config(['nvl-pages.integrations.'.$integration => true]);
    expect(fn () => (new PagesServiceProvider(app()))->boot(
        app(TranslationResourceRegistry::class), app(PageResourceRegistry::class)))
        ->toThrow(InvalidArgumentException::class, 'requires the loaded provider');
})->with(['content', 'seo']);
