<?php

declare(strict_types=1);

use Nvl\Pages\Services\PageModelRegistry;
use Nvl\Pages\Tests\LegacyOwnershipTestCase;

it('preserves reviewed legacy tree IDs paths and copy under a native owner', function (): void {
    $this->owned(7, function (): void {
        $child = PageModelRegistry::query()->findOrFail(LegacyOwnershipTestCase::CHILD);
        expect($child->parent->id)->toBe(LegacyOwnershipTestCase::ROOT)
            ->and($child->path)->toBe('legacy/child')->and($child->displayTitle('en'))->toBe('Legacy child');
    });
    $this->owned(8, fn () => expect(PageModelRegistry::query()->count())->toBe(0));
});
