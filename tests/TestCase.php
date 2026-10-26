<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Nvl\Content\Providers\ContentServiceProvider;
use Nvl\Filterable\Providers\FilterableServiceProvider;
use Nvl\Media\Providers\MediaServiceProvider;
use Nvl\Metafields\Providers\MetafieldsServiceProvider;
use Nvl\Pages\Contracts\PageAuthorization;
use Nvl\Pages\Providers\PagesServiceProvider;
use Nvl\Pages\Tests\Fixtures\Consumer\ConsumerPageAuthorization;
use Nvl\Pages\Tests\Fixtures\Consumer\Contracts\PageRequestContextResolver;
use Nvl\Pages\Tests\Fixtures\Consumer\Services\ConfiguredPageRequestContextResolver;
use Nvl\Pages\Tests\Fixtures\TestPageResourceHandler;
use Nvl\Seo\Contracts\SeoAuthorization;
use Nvl\Seo\Providers\SeoServiceProvider;
use Nvl\Seo\Support\SeoAuthorizationContext;
use Nvl\Support\Providers\LocaleServiceProvider;
use Nvl\Support\Providers\SupportServiceProvider;
use Nvl\Translatable\Providers\TranslatableServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\LaravelData\LaravelDataServiceProvider;

/**
 * Boots Pages with only its declared runtime dependency graph.
 */
abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LaravelDataServiceProvider::class,
            LocaleServiceProvider::class,
            SupportServiceProvider::class,
            FilterableServiceProvider::class,
            TranslatableServiceProvider::class,
            MediaServiceProvider::class,
            ContentServiceProvider::class,
            ...(class_exists(MetafieldsServiceProvider::class) ? [MetafieldsServiceProvider::class] : []),
            SeoServiceProvider::class,
            PagesServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(PageAuthorization::class, new ConsumerPageAuthorization);
        $this->app->instance(SeoAuthorization::class, new class implements SeoAuthorization
        {
            public function authorize(SeoAuthorizationContext $context): void {}
        });
    }

    protected function defineRoutes($router): void
    {
        require __DIR__.'/Fixtures/Consumer/routes.php';
    }

    protected function defineEnvironment($app): void
    {
        $app->bind(PageRequestContextResolver::class,
            ConfiguredPageRequestContextResolver::class);
        $app['config']->set([
            'nvl-pages.authorization.class' => ConsumerPageAuthorization::class,
            'app.locale' => 'en',
            'app.fallback_locale' => 'en',
            'app.url' => 'https://pages.test',
            'cache.default' => 'array',
            'filesystems.default' => 'local',
            'nvl-media.disk' => 'local',
            'nvl-media.routes.assets_enabled' => false,
            'nvl-content.authorization.callback' => static fn (): bool => true,
            'nvl-translatable.locales' => ['en', 'bg'],
            'nvl-translatable.fallback_locales' => ['en'],
            'nvl-pages.urls.base_url' => 'https://pages.test',
            'nvl-seo.routes.sitemap_path' => 'sitemap.xml',
            'nvl-seo.routes.sitemap_chunk_path' => 'sitemap-{chunk}.xml',
            'nvl-pages.resources' => [
                'records.detail' => TestPageResourceHandler::class,
            ],
        ]);
    }

    protected function defineDatabaseMigrationsAfterDatabaseRefreshed(): void
    {
        Schema::create('page_test_actors', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });
        Schema::create('page_test_resources', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->boolean('is_public')->default(true);
            $table->timestamps();
        });
    }
}
