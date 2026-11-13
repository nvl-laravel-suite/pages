<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Nvl\Ownership\Tests\Fixtures\OptsIntoAdoptedSchemaReuse;
use Nvl\Pages\Contracts\PageAuthorization;
use Nvl\Pages\Enums\PageAbility;
use Nvl\Pages\Events\PageChanged;
use Nvl\Pages\Models\Page;
use Nvl\Pages\Services\PageLifecycle;
use Nvl\Pages\Services\PageReader;
use Nvl\Pages\Tests\Fixtures\Consumer\Actions\CreatePageAction;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\Mutations\CreatePageData;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\PageActorData;
use Nvl\Pages\Tests\Fixtures\OwnershipPage;
use Nvl\Support\Enums\PublicActor;
use Nvl\Support\Enums\SystemActor;
use Nvl\Support\Ownership\Exceptions\OwnershipViolation;

uses(OptsIntoAdoptedSchemaReuse::class);

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

it('composes native operations with a host policy and rolls back the shared source on denial', function (): void {
    $this->owned(7, function (): void {
        $actor = SystemActor::Background;
        app()->instance(PageAuthorization::class, new class implements PageAuthorization
        {
            public function authorize(
                PageAbility $ability,
                Authenticatable|SystemActor|PublicActor $actor,
                ?Page $page = null,
                array $context = [],
            ): void {
                if ($ability === PageAbility::Create && ($context['parentId'] ?? null) !== null) {
                    throw new AuthorizationException('Child creation is denied by the host.');
                }
            }
        });
        $lifecycle = app(PageLifecycle::class);
        $reader = app(PageReader::class);
        $connection = (new OwnershipPage)->getConnection();
        $events = [];
        Event::listen(PageChanged::class, function ($event) use (&$events): void {
            $events[] = $event;
        });

        expect(fn () => $connection->transaction(function () use ($actor, $lifecycle, $reader): void {
            $parent = $lifecycle->create(['key' => 'composed-parent', 'slug' => 'composed-parent'], $actor);
            expect($reader->get($parent, $actor))->toBeInstanceOf(OwnershipPage::class);
            $lifecycle->create(['key' => 'composed-child', 'slug' => 'composed-child', 'parentId' => $parent->id], $actor);
        }))->toThrow(AuthorizationException::class, 'Child creation is denied');
        expect(OwnershipPage::query()->whereIn('key', ['composed-parent', 'composed-child'])->count())->toBe(0)
            ->and($events)->toBe([]);
    });
});
