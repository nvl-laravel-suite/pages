<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Nvl\Pages\Contracts\PageAuthorization;
use Nvl\Pages\Enums\PageAbility;
use Nvl\Pages\Enums\PageStatus;
use Nvl\Pages\Models\Page;
use Nvl\Pages\Services\PageLifecycle;
use Nvl\Pages\Services\PageResolver;
use Nvl\Pages\Tests\Fixtures\Consumer\PreviewActor;
use Nvl\Pages\Tests\Fixtures\RecordingPageAuthorization;
use Nvl\Support\Enums\PublicActor;
use Nvl\Support\Enums\SystemActor;

it('passes canonical persisted preview privileges to the host policy instead of stale or dirty actor facts', function (): void {
    Schema::table('page_test_actors', static fn (Blueprint $table) => $table->boolean('can_preview')->default(false));
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
