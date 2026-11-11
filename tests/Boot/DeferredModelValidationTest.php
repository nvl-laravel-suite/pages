<?php

declare(strict_types=1);

use Nvl\Pages\Services\PageModelRegistry;
use Nvl\Pages\Tests\Fixtures\RecastPagesTestCase;
use Nvl\Support\Ownership\Exceptions\OwnershipConfigurationInvalid;

uses(RecastPagesTestCase::class);

it('validates the configured page root on first use of the model registry instead of on every boot', function (): void {
    expect(app()->isBooted())->toBeTrue()
        ->and(fn () => PageModelRegistry::modelClass())->toThrow(OwnershipConfigurationInvalid::class, 'required package casts')
        ->and(fn () => PageModelRegistry::query())->toThrow(OwnershipConfigurationInvalid::class);
});
