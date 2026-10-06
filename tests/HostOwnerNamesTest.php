<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\Relation;
use Nvl\Pages\Models\Page;
use Nvl\Pages\Tests\Fixtures\TestPageResource;
use Nvl\Pages\Tests\HostOwnerNamesTestCase;
use Nvl\Support\Globals\GlobalNames;

uses(HostOwnerNamesTestCase::class);

it('boots default Pages without reclaiming a host generic page alias', function (): void {
    expect(config('nvl-core.compatibility.global_aliases'))->toBe([])
        ->and(Relation::getMorphedModel('page'))->toBe(TestPageResource::class)
        ->and(Relation::getMorphedModel('nvl-page'))->toBe(Page::class)
        ->and((new Page)->getMorphClass())->toBe('nvl-page')
        ->and(collect(app(GlobalNames::class)->diagnostics())->contains(static fn ($check): bool => str_contains($check->message, '[page]')))->toBeTrue();
});
