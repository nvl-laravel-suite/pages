<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Nvl\Pages\Contracts\PageAuthorization;
use Nvl\Pages\Enums\PageAbility;
use Nvl\Pages\Enums\PublicChildPageOrder;
use Nvl\Pages\Events\PageChanged;
use Nvl\Pages\Exceptions\InvalidPageMutationException;
use Nvl\Pages\Models\Page;
use Nvl\Pages\Services\PageLifecycle;
use Nvl\Pages\Services\PageReader;
use Nvl\Pages\Services\PageResolver;
use Nvl\Pages\Services\PagesDoctor;
use Nvl\Support\Enums\PublicActor;
use Nvl\Support\Enums\SystemActor;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

afterEach(function (): void {
    Carbon::setTestNow();
    config(['app.timezone' => 'UTC']);
    date_default_timezone_set('UTC');
});

/** Bind a host policy that grants every page ability except publication. */
function denyPagePublication(): void
{
    app()->instance(PageAuthorization::class, new class implements PageAuthorization
    {
        public function authorize(PageAbility $ability, Authenticatable|SystemActor|PublicActor $actor, ?Page $page = null, array $context = []): void
        {
            if ($ability === PageAbility::Publish) {
                throw new AuthorizationException('Publication is denied.');
            }
        }
    });
}

/** @param array<string, mixed> $changes */
function pageEdit(Page $page, array $changes = []): array
{
    return $changes + [
        'slug' => $page->slug,
        'kind' => $page->kind,
        'status' => $page->status,
        'publishedAt' => $page->published_at?->toIso8601String(),
        'expiresAt' => $page->expires_at?->toIso8601String(),
        'translations' => ['en' => ['title' => $page->translated('title', 'en')]],
    ];
}

it('requires publication authority to move a live or scheduled publication window without a status change', function (string $status, array $changes): void {
    Carbon::setTestNow('2030-01-01 12:00:00');
    $page = app(PageLifecycle::class)->create([
        'key' => 'window', 'slug' => 'window', 'status' => $status,
        'publishedAt' => $status === 'scheduled' ? '2030-02-01T00:00:00Z' : '2029-12-01T00:00:00Z',
        'translations' => ['en' => ['title' => 'Window']],
    ], SystemActor::Background);
    denyPagePublication();
    Event::fake([PageChanged::class]);

    expect(fn () => app(PageLifecycle::class)->update($page, pageEdit($page, $changes), $page->revision, SystemActor::Background))
        ->toThrow(AuthorizationException::class);

    $stored = Page::query()->findOrFail($page->id);
    expect($stored->revision)->toBe($page->revision)
        ->and($stored->published_at?->getTimestamp())->toBe($page->published_at?->getTimestamp())
        ->and($stored->expires_at)->toBeNull();
    Event::assertNotDispatched(PageChanged::class);

    if ($status === 'scheduled') {
        expect(fn () => app(PageResolver::class)->resolve('window', 'default', 'en'))->toThrow(NotFoundHttpException::class);
    }
})->with([
    'scheduled publication moved into the past' => ['scheduled', ['publishedAt' => '2029-12-31T00:00:00Z']],
    'scheduled expiration added' => ['scheduled', ['expiresAt' => '2030-03-01T00:00:00Z']],
    'published expiration added' => ['published', ['expiresAt' => '2030-01-01T12:00:01Z']],
    'published publication moved' => ['published', ['publishedAt' => '2029-11-01T00:00:00Z']],
]);

it('lets update authority resave a live page whose publication window is unchanged', function (): void {
    Carbon::setTestNow('2030-01-01 12:00:00');
    $page = app(PageLifecycle::class)->create([
        'key' => 'unchanged', 'slug' => 'unchanged', 'status' => 'scheduled',
        'publishedAt' => '2030-02-01T00:00:00+02:00', 'expiresAt' => '2030-03-01T00:00:00Z',
        'translations' => ['en' => ['title' => 'Unchanged']],
    ], SystemActor::Background);
    denyPagePublication();

    $updated = app(PageLifecycle::class)->update($page, pageEdit($page, ['translations' => ['en' => ['title' => 'Renamed']]]), $page->revision, SystemActor::Background);

    expect($updated->translated('title', 'en'))->toBe('Renamed')
        ->and($updated->published_at?->getTimestamp())->toBe($page->published_at?->getTimestamp());
});

it('rolls back an update, move, restore or creation that a model observer vetoes', function (string $operation): void {
    $lifecycle = app(PageLifecycle::class);
    $actor = SystemActor::Background;
    $parent = $lifecycle->create(['key' => 'before', 'slug' => 'before', 'translations' => ['en' => ['title' => 'Before']]], $actor);
    $child = $lifecycle->create(['key' => 'child', 'slug' => 'child', 'parentId' => $parent->id], $actor);
    $target = $lifecycle->create(['key' => 'target', 'slug' => 'target'], $actor);
    $deleted = $lifecycle->create(['key' => 'deleted', 'slug' => 'deleted'], $actor);
    $lifecycle->delete($deleted, $deleted->revision, $actor);
    $deleted = Page::query()->withTrashed()->findOrFail($deleted->id);
    Event::fake([PageChanged::class]);
    $vetoedId = $operation === 'descendant update' ? $child->id : $parent->id;
    Page::updating(static fn (Page $page): ?bool => $page->id === $vetoedId ? false : null);
    Page::restoring(static fn (): bool => false);
    Page::creating(static fn (): bool => false);

    $attempt = match ($operation) {
        'update', 'descendant update' => fn () => $lifecycle->update($parent, pageEdit($parent, ['slug' => 'after', 'translations' => ['en' => ['title' => 'After']]]), $parent->revision, $actor),
        'move' => fn () => $lifecycle->move($parent, $target->id, 0, $parent->revision, $actor),
        'restore' => fn () => $lifecycle->restore($deleted, $deleted->revision, $actor),
        'create' => fn () => $lifecycle->create(['key' => 'vetoed', 'slug' => 'vetoed', 'translations' => ['en' => ['title' => 'Vetoed']]], $actor),
    };

    expect($attempt)->toThrow(InvalidPageMutationException::class);

    $storedParent = Page::query()->findOrFail($parent->id);
    expect($storedParent->path)->toBe('before')
        ->and($storedParent->parent_id)->toBeNull()
        ->and($storedParent->revision)->toBe($parent->revision)
        ->and($storedParent->translated('title', 'en'))->toBe('Before')
        ->and(Page::query()->findOrFail($child->id)->path)->toBe('before/child')
        ->and(Page::query()->withTrashed()->findOrFail($deleted->id)->trashed())->toBeTrue()
        ->and(Page::query()->withTrashed()->where('key', 'vetoed')->exists())->toBeFalse()
        ->and(app(PagesDoctor::class)->inspect()['data.parent_path_drift'])->toBeFalse();
    Event::assertNotDispatched(PageChanged::class);
})->with(['update', 'descendant update', 'move', 'restore', 'create']);

it('retains both supplied instants inside a daylight-saving overlap under a non-UTC application timezone', function (): void {
    config(['app.timezone' => 'Europe/Sofia']);
    date_default_timezone_set('Europe/Sofia');
    Carbon::setTestNow('2030-10-01T00:00:00Z');
    $lifecycle = app(PageLifecycle::class);
    $first = $lifecycle->create(['key' => 'first', 'slug' => 'first', 'status' => 'scheduled',
        'publishedAt' => '2030-10-27T03:30:00+03:00', 'translations' => ['en' => ['title' => 'First']]], SystemActor::Background);
    $second = $lifecycle->create(['key' => 'second', 'slug' => 'second', 'status' => 'scheduled',
        'publishedAt' => '2030-10-27T03:30:00+02:00', 'expiresAt' => '2030-10-27T03:30:01+02:00',
        'translations' => ['en' => ['title' => 'Second']]], SystemActor::Background);

    expect(Page::query()->findOrFail($first->id)->published_at?->getTimestamp())->toBe(Carbon::parse('2030-10-27T00:30:00Z')->getTimestamp())
        ->and(Page::query()->findOrFail($second->id)->published_at?->getTimestamp())->toBe(Carbon::parse('2030-10-27T01:30:00Z')->getTimestamp())
        ->and(Page::query()->findOrFail($second->id)->expires_at?->getTimestamp())->toBe(Carbon::parse('2030-10-27T01:30:01Z')->getTimestamp());

    Carbon::setTestNow('2030-10-27T01:00:00Z');

    expect(Page::query()->publiclyVisible()->pluck('key')->all())->toBe(['first'])
        ->and(app(PageResolver::class)->resolve('first', 'default', 'en')['page']->id)->toBe($first->id)
        ->and(fn () => app(PageResolver::class)->resolve('second', 'default', 'en'))->toThrow(NotFoundHttpException::class);

    Carbon::setTestNow('2030-10-27T01:30:00Z');

    expect(Page::query()->publiclyVisible()->orderBy('key')->pluck('key')->all())->toBe(['first', 'second']);
});

it('rejects a lifecycle window that collapses at the stored second precision', function (): void {
    expect(fn () => app(PageLifecycle::class)->create([
        'key' => 'fraction', 'slug' => 'fraction', 'status' => 'scheduled',
        'publishedAt' => '2030-01-01T10:00:00.100000Z', 'expiresAt' => '2030-01-01T10:00:00.900000Z',
    ], SystemActor::Background))->toThrow(InvalidPageMutationException::class);

    expect(Page::query()->withTrashed()->count())->toBe(0)
        ->and(app(PagesDoctor::class)->inspect()['data.invalid_lifecycle_dates'])->toBeFalse();
});

it('fails strict diagnostics when a required page identity unique constraint is missing', function (array $columns): void {
    $table = (new Page)->getTable();
    $index = collect(Schema::getIndexes($table))->first(static fn (array $index): bool => $index['unique'] && $index['columns'] === $columns);
    expect($index)->not->toBeNull();
    Schema::table($table, static fn ($blueprint) => $blueprint->dropUnique($index['name']));

    $exit = Artisan::call('nvl:pages:doctor', ['--strict' => true, '--format' => 'json']);
    $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($exit)->toBe(1)
        ->and($report['healthy'])->toBeFalse()
        ->and($report['index.pages.'.implode('_', $columns)])->toBeFalse();
})->with([
    'key' => [['key']],
    'sibling slug' => [['site', 'parent_key', 'slug']],
    'path hash' => [['site', 'path_hash']],
]);

it('fails strict diagnostics when the translation locale unique constraint is missing', function (): void {
    $table = app(Page::class)->translations()->getRelated()->getTable();
    Schema::table($table, static fn ($blueprint) => $blueprint->dropUnique('pages_i18n_owner_locale_unique'));

    expect(app(PagesDoctor::class)->inspect())
        ->toMatchArray(['index.pages_i18n.page_id_locale' => false, 'healthy' => false]);
});

it('orders newest public children by comparable instants under a non-UTC application timezone', function (): void {
    config(['app.timezone' => 'Europe/Sofia']);
    date_default_timezone_set('Europe/Sofia');
    $lifecycle = app(PageLifecycle::class);
    $actor = SystemActor::Background;
    Carbon::setTestNow('2030-01-10T09:00:00Z');
    $parent = $lifecycle->create(['key' => 'news', 'slug' => 'news', 'status' => 'published', 'translations' => ['en' => ['title' => 'News']]], $actor);
    $oldestUndated = $lifecycle->create(['key' => 'oldest', 'slug' => 'oldest', 'parentId' => $parent->id, 'status' => 'published', 'translations' => ['en' => ['title' => 'Oldest']]], $actor);
    Carbon::setTestNow('2030-01-10T10:00:00Z');
    $undated = $lifecycle->create(['key' => 'undated', 'slug' => 'undated', 'parentId' => $parent->id, 'status' => 'published', 'translations' => ['en' => ['title' => 'Undated']]], $actor);
    Carbon::setTestNow('2030-01-10T12:00:00Z');
    $dated = $lifecycle->create(['key' => 'dated', 'slug' => 'dated', 'parentId' => $parent->id, 'status' => 'published',
        'publishedAt' => '2030-01-10T11:00:00Z', 'translations' => ['en' => ['title' => 'Dated']]], $actor);

    $children = app(PageReader::class)->children($parent->id, 'default', 'en', order: PublicChildPageOrder::Newest);

    expect($children->pluck('id')->all())->toBe([$dated->id, $undated->id, $oldestUndated->id]);
});
