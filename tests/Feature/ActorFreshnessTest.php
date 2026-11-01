<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Nvl\Pages\Contracts\PageAuthorization;
use Nvl\Pages\Enums\PageAbility;
use Nvl\Pages\Enums\PageStatus;
use Nvl\Pages\Models\Page;
use Nvl\Pages\Services\PageLifecycle;
use Nvl\Pages\Services\PageReader;
use Nvl\Pages\Services\PageResolver;
use Nvl\Pages\Tests\Fixtures\Consumer\PreviewActor;
use Nvl\Pages\Tests\Fixtures\RecordingPageAuthorization;
use Nvl\Support\Enums\PublicActor;
use Nvl\Support\Enums\SystemActor;

it('passes canonical persisted preview privileges to the host policy instead of stale or dirty actor facts', function (): void {
    app()->instance(PageAuthorization::class, new RecordingPageAuthorization);
    $page = app(PageLifecycle::class)->create(['key' => 'fresh-actor', 'slug' => 'fresh-actor', 'status' => PageStatus::Draft], SystemActor::Background);
    $actor = PreviewActor::query()->create(['can_preview' => true]);
    $actor->newQuery()->whereKey($actor->getKey())->update(['can_preview' => false]);
    $actor->setRelation('permissions', collect(['preview']));
    $policy = new class implements PageAuthorization
    {
        public ?Authenticatable $seen = null;

        public function authorize(PageAbility $ability, Authenticatable|SystemActor|PublicActor $actor, ?Page $page = null, array $context = []): void
        {
            $this->seen = $actor instanceof Authenticatable ? $actor : null;
            if (! $actor instanceof Model || ! $actor->getAttribute('can_preview')) {
                throw new AuthorizationException;
            }
        }
    };
    app()->instance(PageAuthorization::class, $policy);
    expect(fn () => app(PageResolver::class)->preview('/fresh-actor', 'default', 'en', $actor))->toThrow(AuthorizationException::class)
        ->and($policy->seen)->not->toBe($actor)
        ->and($policy->seen?->getRelations())->toBe([]);
    $actor->newQuery()->whereKey($actor->getKey())->update(['can_preview' => true]);
    expect(app(PageResolver::class)->preview('/fresh-actor', 'default', 'en', $actor)['page']->id)->toBe($page->id);
});

it('retains the persisted Eloquent actor requirement for private previews', function (): void {
    app()->instance(PageAuthorization::class, new RecordingPageAuthorization);
    app(PageLifecycle::class)->create(['key' => 'external-actor', 'slug' => 'external-actor', 'status' => PageStatus::Draft], SystemActor::Background);
    expect(fn () => app(PageResolver::class)->preview('/external-actor', 'default', 'en', new GenericUser(['id' => 'directory-user'])))->toThrow(AuthorizationException::class);
});

it('reloads actor authority for every lifecycle operation and private read', function (string $operation): void {
    app()->instance(PageAuthorization::class, new RecordingPageAuthorization);
    $pages = app(PageLifecycle::class);
    $page = $pages->create(['key' => 'authority', 'slug' => 'authority'], SystemActor::Background);
    if ($operation === 'restore') {
        $pages->delete($page, $page->revision, SystemActor::Background);
    }
    $actor = PreviewActor::query()->create(['can_manage' => false]);
    $actor->can_manage = true;
    $actor->setRelation('permissions', collect(['manage']));
    app()->instance(PageAuthorization::class, new class implements PageAuthorization
    {
        public function authorize(PageAbility $ability, Authenticatable|SystemActor|PublicActor $actor, ?Page $page = null, array $context = []): void
        {
            if (! $actor instanceof Model || ! $actor->getAttribute('can_manage')) {
                throw new AuthorizationException;
            }
        }
    });

    expect(fn () => match ($operation) {
        'create' => app(PageLifecycle::class)->create(['key' => 'other', 'slug' => 'other'], $actor),
        'update' => app(PageLifecycle::class)->update($page, ['slug' => 'changed', 'kind' => 'static', 'status' => 'draft'], $page->revision, $actor),
        'move' => app(PageLifecycle::class)->move($page, null, 2, $page->revision, $actor),
        'delete' => app(PageLifecycle::class)->delete($page, $page->revision, $actor),
        'restore' => app(PageLifecycle::class)->restore($page, $page->revision, $actor),
        'get' => app(PageReader::class)->get($page, $actor),
        'paginate' => app(PageReader::class)->paginate('default', $actor),
        'keyAvailable' => app(PageReader::class)->keyAvailable('default', 'other', $actor),
    })->toThrow(AuthorizationException::class);
})->with(['create', 'update', 'move', 'delete', 'restore', 'get', 'paginate', 'keyAvailable']);

it('takes mutation lock identities from persisted page state', function (string $operation): void {
    app()->instance(PageAuthorization::class, new RecordingPageAuthorization);
    $pages = app(PageLifecycle::class);
    $page = $pages->create(['key' => 'canonical-site', 'slug' => 'canonical-site'], SystemActor::Background);
    if ($operation === 'restore') {
        $pages->delete($page, $page->revision, SystemActor::Background);
    }
    $page->site = 'forged-site';
    $result = match ($operation) {
        'update' => $pages->update($page, ['slug' => 'changed', 'kind' => 'static', 'status' => 'draft'], $page->revision, SystemActor::Background),
        'move' => $pages->move($page, null, 2, $page->revision, SystemActor::Background),
        'restore' => $pages->restore($page, $page->revision, SystemActor::Background),
    };

    expect($result->site)->toBe('default');
})->with(['update', 'move', 'restore']);
