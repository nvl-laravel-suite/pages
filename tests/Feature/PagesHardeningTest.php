<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Nvl\Pages\Exceptions\PageHierarchyException;
use Nvl\Pages\Models\Page;
use Nvl\Pages\Services\PageLifecycle;
use Nvl\Pages\Services\PageReader;
use Nvl\Pages\Services\PageResolver;
use Nvl\Pages\Services\PagesDoctor;
use Nvl\Support\Enums\SystemActor;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

afterEach(function (): void {
    Carbon::setTestNow();
});

it('stores offset publication timestamps as the same instant instead of their wall clock', function (): void {
    Carbon::setTestNow('2030-01-01 12:00:00');
    $page = app(PageLifecycle::class)->create([
        'key' => 'offset', 'slug' => 'offset', 'status' => 'scheduled',
        'publishedAt' => '2030-01-01T10:00:00-05:00',
        'expiresAt' => '2030-01-02T10:00:00-05:00',
        'translations' => ['en' => ['title' => 'Offset']],
    ], SystemActor::Background);

    expect($page->published_at->equalTo(Carbon::parse('2030-01-01 15:00:00')))->toBeTrue()
        ->and($page->expires_at->equalTo(Carbon::parse('2030-01-02 15:00:00')))->toBeTrue()
        ->and(fn () => app(PageResolver::class)->resolve('offset', 'default', 'en'))->toThrow(NotFoundHttpException::class);

    Carbon::setTestNow('2030-01-01 15:00:01');

    expect(app(PageResolver::class)->resolve('offset', 'default', 'en')['page']->id)->toBe($page->id);
});

it('rebuilds the paths of deleted descendants so a renamed branch releases its former paths', function (): void {
    $lifecycle = app(PageLifecycle::class);
    $actor = SystemActor::Background;
    $parent = $lifecycle->create(['key' => 'products', 'slug' => 'products'], $actor);
    $child = $lifecycle->create(['key' => 'item', 'slug' => 'item', 'parentId' => $parent->id], $actor);
    $lifecycle->delete($child, $child->revision, $actor);
    $parent = $lifecycle->update($parent, ['slug' => 'catalog', 'kind' => 'static', 'status' => 'draft'], $parent->revision, $actor);

    expect(Page::query()->withTrashed()->findOrFail($child->id)->path)->toBe('catalog/item')
        ->and(app(PagesDoctor::class)->inspect()['data.parent_path_drift'])->toBeFalse();

    $replacement = $lifecycle->create(['key' => 'products-new', 'slug' => 'products'], $actor);
    $replacementChild = $lifecycle->create(['key' => 'item-new', 'slug' => 'item', 'parentId' => $replacement->id], $actor);

    expect($replacementChild->path)->toBe('products/item');

    $moved = $lifecycle->move($parent, $replacement->id, 0, $parent->revision, $actor);

    expect($moved->path)->toBe('products/catalog')
        ->and(Page::query()->withTrashed()->findOrFail($child->id)->path)->toBe('products/catalog/item');
});

it('rejects identities that end with a line break instead of storing them', function (array $attributes): void {
    expect(fn () => app(PageLifecycle::class)->create($attributes + ['key' => 'line', 'slug' => 'line'], SystemActor::Background))
        ->toThrow(ValidationException::class)
        ->and(Page::query()->count())->toBe(0);
})->with([
    'key' => [['key' => "line\n"]],
    'slug' => [['slug' => "line\n"]],
    'site' => [['site' => "main\n"]],
    'resource' => [['kind' => 'resource', 'resource' => "records.detail\n"]],
]);

it('rejects a non-string management sort as invalid input', function (mixed $sort): void {
    expect(fn () => app(PageReader::class)->paginate('default', SystemActor::Background, ['sort' => $sort]))
        ->toThrow(InvalidArgumentException::class);
})->with([[['position']], [1]]);

it('loads only navigable pages when building public navigation', function (): void {
    $lifecycle = app(PageLifecycle::class);
    $visible = $lifecycle->create(['key' => 'shown', 'slug' => 'shown', 'status' => 'published',
        'translations' => ['en' => ['title' => 'Shown']]], SystemActor::Background);
    $hidden = $lifecycle->create(['key' => 'landing', 'slug' => 'landing', 'status' => 'published', 'isNavigable' => false,
        'translations' => ['en' => ['title' => 'Landing']]], SystemActor::Background);
    $lifecycle->create(['key' => 'beneath', 'slug' => 'beneath', 'status' => 'published', 'parentId' => $hidden->id,
        'translations' => ['en' => ['title' => 'Beneath']]], SystemActor::Background);
    $retrieved = [];
    Page::retrieved(function (Page $page) use (&$retrieved): void {
        $retrieved[] = $page->key;
    });

    $navigation = app(PageReader::class)->navigation('default', 'en');

    expect(array_column($navigation['items'], 'id'))->toBe([$visible->id])
        ->and($retrieved)->toEqualCanonicalizing(['shown', 'beneath']);
});

it('accepts a parent identifier in another letter case as the same page and never as a new parent of itself', function (): void {
    $lifecycle = app(PageLifecycle::class);
    $actor = SystemActor::Background;
    $parent = $lifecycle->create(['key' => 'parent', 'slug' => 'parent'], $actor);
    $child = $lifecycle->create(['key' => 'child', 'slug' => 'child', 'parentId' => strtoupper($parent->id)], $actor);
    $sibling = $lifecycle->create(['key' => 'sibling', 'slug' => 'sibling'], $actor);

    expect($child->parent_id)->toBe($parent->id)
        ->and($child->path)->toBe('parent/child')
        ->and($lifecycle->move($sibling, strtoupper($parent->id), 1, $sibling->revision, $actor)->parent_id)->toBe($parent->id)
        ->and(fn () => $lifecycle->move($parent, strtoupper($parent->id), 0, $parent->revision, $actor))->toThrow(PageHierarchyException::class)
        ->and(Page::query()->findOrFail($parent->id)->parent_id)->toBeNull();
});

it('treats a malformed page identifier as not found in every lifecycle operation', function (): void {
    $lifecycle = app(PageLifecycle::class);
    $actor = SystemActor::Background;

    expect(fn () => $lifecycle->update('not-a-uuid', ['slug' => 'x', 'kind' => 'static', 'status' => 'draft'], 1, $actor))->toThrow(ModelNotFoundException::class)
        ->and(fn () => $lifecycle->move('not-a-uuid', null, 0, 1, $actor))->toThrow(ModelNotFoundException::class)
        ->and(fn () => $lifecycle->delete('not-a-uuid', 1, $actor))->toThrow(ModelNotFoundException::class)
        ->and(fn () => $lifecycle->restore('not-a-uuid', 1, $actor))->toThrow(ModelNotFoundException::class);
});
