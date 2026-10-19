<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests;

use Illuminate\Database\Schema\Blueprint;
use Nvl\Ownership\Services\OwnershipAdoptionCoordinator;
use Nvl\Ownership\ValueObjects\OwnershipOperation;
use Nvl\Pages\Providers\PagesServiceProvider;
use Nvl\Pages\Tests\CompositionOwnershipTestCase as SeoOwnershipTestCase;
use Nvl\Pages\Tests\Fixtures\OwnershipPage;
use Nvl\Seo\Providers\SeoServiceProvider;

/** Exercises the real Pages/Content/Media/SEO ownership graph through verified public routes. */
abstract class OwnershipTestCase extends SeoOwnershipTestCase
{
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), PagesServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set([
            'nvl-pages.models.page' => $this->pageClass(),
            'nvl-pages.migrations.enabled' => false,
            'nvl-pages.routes.public.enabled' => true,
            'nvl-pages.integrations.metafields' => false,
            'nvl-seo.ownership.owner_resources' => ['fixture.content-owners', 'pages.pages'],
            'nvl-content.ownership.owner_resources' => ['fixture.content-owners', 'pages.pages'],
        ]);
    }

    /** Install an explicitly selected consumer-native pivot before protocol preparation. */
    protected function prepareOwnershipSchema(): void {}

    protected function pageClass(): string
    {
        return OwnershipPage::class;
    }

    protected function adoptOwnership(): void
    {
        foreach (['seo', 'pages'] as $package) {
            foreach (glob(dirname((new \ReflectionClass($package === 'seo' ? SeoServiceProvider::class : PagesServiceProvider::class))->getFileName(), 3).'/database/migrations/*.php') ?: [] as $path) {
                (require $path)->up();
            }
        }
        $class = $this->pageClass();
        (new $class)->getConnection()->getSchemaBuilder()->table((new $class)->getTable(), static function (Blueprint $table): void {
            $table->unsignedBigInteger('company_id')->nullable();
            $table->foreign('company_id')->references('id')->on('content_owned_companies');
        });
        $this->prepareOwnershipSchema();
        $operation = new OwnershipOperation('adopt-pages', 'operator', '1');
        $coordinator = $this->app->make(OwnershipAdoptionCoordinator::class);
        $plan = $coordinator->prepare(['media', 'content', 'fixture', 'seo', 'pages'], $this->reviewedMappings(), $operation);
        $coordinator->backfill($plan, 100, $operation);
        $coordinator->activate($plan, $operation);
    }
}
