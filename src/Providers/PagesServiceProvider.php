<?php

declare(strict_types=1);

namespace Nvl\Pages\Providers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Events\MigrationStarted;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Nvl\Content\Contracts\ContentOwnerRegistrar;
use Nvl\Data\Services\TypeScriptSourceRegistry;
use Nvl\Metafields\Providers\MetafieldsServiceProvider;
use Nvl\Metafields\Support\MetafieldOwnerRegistry;
use Nvl\Pages\Console\PagesDoctorCommand;
use Nvl\Pages\Contracts\PageAuthorization;
use Nvl\Pages\Contracts\PageMetafields;
use Nvl\Pages\Contracts\PageRequestContextResolver;
use Nvl\Pages\Contracts\PageResourceHandler;
use Nvl\Pages\Contracts\PageUrlGenerator;
use Nvl\Pages\Events\PageChanged;
use Nvl\Pages\Integrations\EmptyPageMetafields;
use Nvl\Pages\Integrations\MetafieldsPageAdapter;
use Nvl\Pages\Listeners\InvalidatePageSitemap;
use Nvl\Pages\Models\Page;
use Nvl\Pages\Seo\PageSitemapSource;
use Nvl\Pages\Services\ConfiguredPageAuthorization;
use Nvl\Pages\Services\ConfiguredPageRequestContextResolver;
use Nvl\Pages\Services\ConfiguredPageUrlGenerator;
use Nvl\Pages\Services\PageResourceRegistry;
use Nvl\Pages\Services\PagesDoctor;
use Nvl\Pages\Support\PagesConfiguration;
use Nvl\Pages\Support\PagesMigrationRollbackGuard;
use Nvl\Pages\Tenancy\PagesResourceRegistrar;
use Nvl\Seo\Services\SeoOwnerRegistry;
use Nvl\Seo\Services\SitemapRegistry;
use Nvl\Support\Doctor\PackageDoctorContributor;
use Nvl\Support\Integrations\OptionalIntegration;
use Nvl\Support\Providers\SupportServiceProvider;
use Nvl\Support\Providers\TenantServiceProvider;
use Nvl\Support\Tenancy\Services\TenantResourceRegistry;
use Nvl\Support\Traits\MergesPackageConfiguration;
use Nvl\Tenancy\Services\TenantAdoptionRegistry;
use Nvl\Translatable\Services\TranslationResourceRegistry;

/**
 * Registers the standalone Pages runtime and its package integrations.
 */
final class PagesServiceProvider extends ServiceProvider
{
    use MergesPackageConfiguration;

    /**
     * Register validated Pages contracts and singleton registries.
     */
    public function register(): void
    {
        $this->app->register(SupportServiceProvider::class);
        PackageDoctorContributor::register($this->app, 'nvl/pages', fn (): array => PackageDoctorContributor::reportChecks($this->app->make(PagesDoctor::class)->inspect(), 'nvl:pages:doctor'));

        $this->mergePackageConfiguration(__DIR__.'/../../config/pages.php', 'pages');
        $this->app->register(TenantServiceProvider::class);
        (new PagesResourceRegistrar)->register($this->app->make(TenantResourceRegistry::class));
        $this->app->booted(function (): void {
            if ($this->app->bound(TenantAdoptionRegistry::class)) {
                (new PagesResourceRegistrar)->register($this->app->make(TenantResourceRegistry::class), $this->app->make(TenantAdoptionRegistry::class));
            }
        });
        $authorization = config(
            'pages.authorization.class',
            ConfiguredPageAuthorization::class,
        );
        $urlGenerator = config(
            'pages.urls.generator',
            ConfiguredPageUrlGenerator::class,
        );
        $contextResolver = config(
            'pages.public.context_resolver',
            ConfiguredPageRequestContextResolver::class,
        );

        if (! is_string($authorization)
            || ! is_a($authorization, PageAuthorization::class, true)) {
            throw new InvalidArgumentException(
                'pages.authorization.class must implement PageAuthorization.',
            );
        }

        if (! is_string($urlGenerator)
            || ! is_a($urlGenerator, PageUrlGenerator::class, true)) {
            throw new InvalidArgumentException(
                'pages.urls.generator must implement PageUrlGenerator.',
            );
        }

        if (! is_string($contextResolver)
            || ! is_a($contextResolver, PageRequestContextResolver::class, true)) {
            throw new InvalidArgumentException(
                'pages.public.context_resolver must implement PageRequestContextResolver.',
            );
        }

        $this->app->bindIf(PageMetafields::class, static function (Application $app): PageMetafields {
            $enabled = $app->make(OptionalIntegration::class)->enabled('pages.integrations.metafields', MetafieldsServiceProvider::class);

            return $app->make($enabled ? MetafieldsPageAdapter::class : EmptyPageMetafields::class);
        });
        $this->app->booting(function (): void {
            if ($this->app->make(OptionalIntegration::class)->enabled('pages.integrations.metafields', MetafieldsServiceProvider::class)) {
                $this->app->make(TenantResourceRegistry::class)->requireCompatible('pages', 'metafields');
            }
        });
        $this->app->bindIf(PageAuthorization::class, $authorization);
        $this->app->bindIf(PageRequestContextResolver::class, $contextResolver);
        $this->app->bindIf(PageUrlGenerator::class, $urlGenerator);
        $this->app->singleton(PageResourceRegistry::class);
        $this->app->singleton(PagesMigrationRollbackGuard::class);
    }

    /**
     * Boot package integrations, resources, migrations, routes, and commands.
     */
    public function boot(
        TypeScriptSourceRegistry $typeScriptSources,
        TranslationResourceRegistry $translationResources,
        ContentOwnerRegistrar $contentOwners,
        SitemapRegistry $sitemaps,
        PageResourceRegistry $resources,
        SeoOwnerRegistry $seoOwners,
    ): void {
        $migrationRollbackGuard = $this->app->make(PagesMigrationRollbackGuard::class);
        $typeScriptSources->register(__DIR__.'/..', 'nvl/pages');
        $this->registerResources($resources);
        Page::resolveRelationUsing('metafields', function (Page $page): MorphMany {
            $this->app->make(OptionalIntegration::class)->enabled('pages.integrations.metafields', MetafieldsServiceProvider::class, requested: true);

            return MetafieldsPageAdapter::relation($page);
        });
        $seoOwners->register(PagesConfiguration::alias('seo_owner_alias', 'page'), Page::class);
        if ($this->app->make(OptionalIntegration::class)->enabled('pages.integrations.metafields', MetafieldsServiceProvider::class)) {
            $sections = config('pages.integrations.metafield_sections', ['general']);
            $this->app->make(MetafieldOwnerRegistry::class)->register(
                PagesConfiguration::alias('metafield_owner_alias', 'page'), Page::class, 'Pages',
                is_array($sections) ? array_values(array_filter($sections, 'is_string')) : ['general'],
            );
        }
        $contentAlias = Page::CONTENT_OWNER_TYPE;

        $registeredContentOwner = $contentOwners->registered($contentAlias);

        if ($registeredContentOwner === null) {
            $contentOwners->register($contentAlias, Page::class);
        } elseif ($registeredContentOwner !== Page::class) {
            throw new InvalidArgumentException(
                "Content owner alias [{$contentAlias}] must resolve to Page.",
            );
        }

        $translationResources->register(
            key: 'pages.pages',
            modelClass: Page::class,
            label: 'Pages',
            searchableColumns: ['key', 'site', 'slug', 'path', 'status', 'resource'],
            displayColumns: ['key', 'site', 'path', 'kind', 'status', 'revision'],
            orderColumn: 'path',
        );
        $sitemaps->registerType(PageSitemapSource::class, 'nvl/pages', [Page::class]);
        Event::listen(PageChanged::class, InvalidatePageSitemap::class);
        Event::listen(MigrationStarted::class, $migrationRollbackGuard->before(...));

        if ((bool) config('pages.migrations.enabled', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        }

        $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->commands([PagesDoctorCommand::class]);
        }

        $this->publishes([
            __DIR__.'/../../config/pages.php' => config_path('pages.php'),
        ], 'pages-config');
        $this->publishesMigrations([
            __DIR__.'/../../database/migrations' => database_path('migrations'),
        ], 'pages-migrations');
        $this->publishes([
            __DIR__.'/../../resources/boost/skills' => base_path('.agents/skills'),
        ], 'pages-skills');
    }

    private function registerResources(PageResourceRegistry $registry): void
    {
        $configured = config('pages.resources', []);

        if (! is_array($configured)) {
            throw new InvalidArgumentException('pages.resources must be an alias-to-handler map.');
        }

        foreach ($configured as $alias => $handler) {
            $owner = null;

            if (is_array($handler)) {
                $owner = $handler['owner'] ?? null;
                $handler = $handler['handler'] ?? null;

                if (! is_string($owner)) {
                    throw new InvalidArgumentException('Every pages identity reference must declare an owner alias.');
                }
            }

            if (! is_string($alias)
                || ! is_string($handler)
                || ! is_a($handler, PageResourceHandler::class, true)) {
                throw new InvalidArgumentException(
                    'Every configured page resource handler is invalid.',
                );
            }

            $registry->register($alias, $handler, $owner);
        }
    }
}
