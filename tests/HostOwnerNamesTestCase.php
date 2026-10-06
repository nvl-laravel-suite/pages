<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Nvl\Pages\Models\Page;
use Nvl\Pages\Tests\Fixtures\TestPageResource;

/** Boots Pages while a foreign host model owns the old generic page alias. */
abstract class HostOwnerNamesTestCase extends TestCase
{
    /** @var array<string, class-string<Model>> */
    private array $originalMorphMap = [];

    /** Reserve the host alias before any package provider is booted. */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $this->originalMorphMap = Relation::morphMap();
        $map = array_filter($this->originalMorphMap, static fn (string $model): bool => $model !== Page::class);
        Relation::morphMap(['page' => TestPageResource::class] + $map, false);
    }

    /** Restore the host map even when provider boot fails. */
    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            Relation::morphMap($this->originalMorphMap, false);
        }
    }
}
