<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests;

use Nvl\Ownership\Services\OwnershipAdoptionCoordinator;
use Nvl\Ownership\Services\OwnershipRunner;
use Nvl\Ownership\ValueObjects\OwnershipOperation;
use Nvl\Pages\Tests\Fixtures\OwnershipActor;
use Nvl\Pages\Tests\Fixtures\OwnershipCompany;
use Nvl\Pages\Tests\Fixtures\OwnershipContentOwner;
use Nvl\Seo\Providers\SeoServiceProvider;

/** Exercises the complete owned public discovery graph without NVL Auth or Tenancy. */
abstract class CompositionOwnershipTestCase extends ContentOwnershipTestCase
{
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), SeoServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set([
            'app.url' => 'https://seo.public.test',
            'cache.default' => 'array',
            'nvl-seo.migrations.enabled' => false,
            'nvl-seo.routes.sitemap_path' => 'sitemap.xml',
            'nvl-seo.owners' => ['subjects' => OwnershipContentOwner::class],
            'nvl-seo.ownership.owner_resources' => ['fixture.content-owners'],
        ]);
    }

    protected function adoptOwnership(): void
    {
        foreach (glob(__DIR__.'/../database/migrations/*.php') ?: [] as $path) {
            (require $path)->up();
        }
        $operation = new OwnershipOperation('adopt-seo', 'operator', '1');
        $coordinator = $this->app->make(OwnershipAdoptionCoordinator::class);
        $plan = $coordinator->prepare(['media', 'content', 'fixture', 'seo'], $this->reviewedMappings(), $operation);
        $coordinator->backfill($plan, 100, $operation);
        $coordinator->activate($plan, $operation);
    }

    protected function owned(int $id, \Closure $callback): mixed
    {
        return app(OwnershipRunner::class)->run(OwnershipCompany::findOrFail($id), OwnershipActor::firstOrFail(), $callback);
    }
}
